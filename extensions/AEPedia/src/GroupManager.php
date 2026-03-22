<?php

namespace MediaWiki\Extension\AEPedia;

use MediaWiki\Block\BlockUserFactory;
use MediaWiki\Block\UnblockUserFactory;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Central service for managing AEPedia school group assignments.
 *
 * Groups follow the pattern skol_* (one per school). This is the single place
 * that:
 *   1. Writes to aepedia_groups (the DB table, source of truth for pre-registration assignments)
 *   2. Immediately applies MW user groups to existing accounts
 *   3. Blocks accounts that are removed from all school groups
 *   4. Unblocks accounts that are re-added to any school group
 *
 * Called by:
 *   - SpecialAEPediaAdmin (on CSV import)
 *   - UserHooks (on new account creation, to check eligibility and apply pre-existing assignments)
 *   - UserHooks (on UserGroupsChanged, to keep aepedia_groups in sync with MW group assignments)
 */
class GroupManager {

    /**
     * Groups managed by AEPedia. Each entry is a school (skol_*).
     * Add new schools here when you add namespaces in LocalSettings.php.
     */
    public const MANAGED_GROUPS = [
        'skol_naoned',
        'skol_orvez',
    ];

    /**
     * Human-readable display names for each managed group, keyed by language code.
     * Used in the admin UI instead of raw group identifiers.
     */
    public const GROUP_LABELS = [
        'skol_naoned' => [ 'fr' => 'Nantes',    'br' => 'Naoned' ],
        'skol_orvez'  => [ 'fr' => 'Orvault',   'br' => 'Orvez'  ],
    ];

    /** Exact block reason set by group imports. Used to identify blocks we can safely remove. */
    private const BLOCK_REASON = 'Email address removed from all AEPedia school groups.';

    private IConnectionProvider $dbProvider;
    private UserGroupManager $userGroupManager;
    private UserFactory $userFactory;
    private BlockUserFactory $blockUserFactory;
    private UnblockUserFactory $unblockUserFactory;

    public function __construct(
        IConnectionProvider $dbProvider,
        UserGroupManager $userGroupManager,
        UserFactory $userFactory,
        BlockUserFactory $blockUserFactory,
        UnblockUserFactory $unblockUserFactory
    ) {
        $this->dbProvider         = $dbProvider;
        $this->userGroupManager   = $userGroupManager;
        $this->userFactory        = $userFactory;
        $this->blockUserFactory   = $blockUserFactory;
        $this->unblockUserFactory = $unblockUserFactory;
    }

    /**
     * Check whether an email is eligible to register.
     * A user is allowed if their email appears in at least one managed school group.
     *
     * Used by UserHooks at account creation time.
     */
    public function isAllowed( string $email ): bool {
        return (bool)$this->dbProvider->getPrimaryDatabase()
            ->newSelectQueryBuilder()
            ->select( 'ag_email' )
            ->from( 'aepedia_groups' )
            ->where( [
                'ag_email' => strtolower( trim( $email ) ),
                'ag_group' => self::MANAGED_GROUPS,
            ] )
            ->caller( __METHOD__ )
            ->fetchField();
    }

    /**
     * Return all emails currently assigned to a group, sorted alphabetically.
     * Used by the admin UI for display.
     *
     * @return string[]
     */
    public function getGroupMembers( string $group ): array {
        return $this->dbProvider->getPrimaryDatabase()
            ->newSelectQueryBuilder()
            ->select( 'ag_email' )
            ->from( 'aepedia_groups' )
            ->where( [ 'ag_group' => $group ] )
            ->orderBy( 'ag_email' )
            ->caller( __METHOD__ )
            ->fetchFieldValues();
    }

    /**
     * Fully replace the members of a single group with the provided email list.
     *
     * - Emails no longer in the list have the group removed from their MW account.
     *   If they no longer belong to any managed school group, their account is blocked.
     * - New emails have the group added to their MW account (if they have one).
     *   If they were previously blocked with our block reason, they are unblocked.
     *
     * @param string   $group     One of MANAGED_GROUPS.
     * @param string[] $newEmails The complete new member list for this group.
     * @return array{added: int, removed: int, blocked: int, unblocked: int}
     */
    public function replaceGroupMembers( string $group, array $newEmails ): array {
        if ( !in_array( $group, self::MANAGED_GROUPS, true ) ) {
            return [ 'added' => 0, 'removed' => 0, 'blocked' => 0, 'unblocked' => 0 ];
        }

        $db        = $this->dbProvider->getPrimaryDatabase();
        $newEmails = array_values( array_unique( array_map(
            static fn( string $e ) => strtolower( trim( $e ) ),
            $newEmails
        ) ) );

        // Start the transaction before reading so the diff is computed against
        // a locked snapshot — prevents concurrent imports from racing.
        $db->startAtomic( __METHOD__ );

        $currentEmails = $db->newSelectQueryBuilder()
            ->select( 'ag_email' )
            ->from( 'aepedia_groups' )
            ->where( [ 'ag_group' => $group ] )
            ->forUpdate()
            ->caller( __METHOD__ )
            ->fetchFieldValues();

        $toAdd    = array_diff( $newEmails, $currentEmails );
        $toRemove = array_diff( $currentEmails, $newEmails );

        if ( !empty( $toRemove ) ) {
            $db->newDeleteQueryBuilder()
                ->deleteFrom( 'aepedia_groups' )
                ->where( [ 'ag_email' => $toRemove, 'ag_group' => $group ] )
                ->caller( __METHOD__ )
                ->execute();
        }

        if ( !empty( $toAdd ) ) {
            $db->newInsertQueryBuilder()
                ->insertInto( 'aepedia_groups' )
                ->rows( array_map( static fn( $e ) => [ 'ag_email' => $e, 'ag_group' => $group ], $toAdd ) )
                ->ignore()
                ->caller( __METHOD__ )
                ->execute();
        }

        $db->endAtomic( __METHOD__ );

        // Apply MW group changes to existing accounts.
        foreach ( $this->findUsersByEmails( $toAdd ) as $user ) {
            $this->userGroupManager->addUserToGroup( $user, $group );
        }
        foreach ( $this->findUsersByEmails( $toRemove ) as $user ) {
            $this->userGroupManager->removeUserFromGroup( $user, $group );
        }

        // Block accounts removed from their last school group; unblock re-added ones.
        $blocked   = 0;
        $unblocked = 0;

        foreach ( $this->findUsersByEmails( $toRemove ) as $user ) {
            if ( $this->isAllowed( $user->getEmail() ) ) {
                // Still in at least one other school group — do not block.
                continue;
            }
            // Skip users that are already blocked — don't overwrite an admin's block.
            if ( $user->getBlock() !== null ) {
                continue;
            }
            $this->blockUserFactory
                ->newBlockUser(
                    $user,
                    $this->getSystemUser(),
                    'infinity',
                    self::BLOCK_REASON,
                    [
                        'isCreateAccountBlocked' => true,
                        'isEmailBlocked'         => false,
                        'isHardBlock'            => true,
                        'isAutoblocking'         => false,
                    ]
                )->placeBlock();
            $blocked++;
        }

        foreach ( $this->findUsersByEmails( $toAdd ) as $user ) {
            $block = $user->getBlock();
            if ( $block === null ) {
                continue;
            }
            if ( $block->getReasonComment()->text !== self::BLOCK_REASON ) {
                continue;
            }
            $this->unblockUserFactory
                ->newUnblockUser( $user, $this->getSystemUser(), 'Email address re-added to an AEPedia school group.' )
                ->unblock();
            $unblocked++;
        }

        return [
            'added'     => count( $toAdd ),
            'removed'   => count( $toRemove ),
            'blocked'   => $blocked,
            'unblocked' => $unblocked,
        ];
    }

    /**
     * Called on new account creation.
     * Looks up the user's email in aepedia_groups and applies any
     * pre-existing assignments to the freshly created account.
     */
    public function applyGroupsToNewUser( User $user ): void {
        $email = strtolower( trim( $user->getEmail() ) );
        if ( $email === '' ) {
            return;
        }

        $assignedGroups = $this->dbProvider->getPrimaryDatabase()
            ->newSelectQueryBuilder()
            ->select( 'ag_group' )
            ->from( 'aepedia_groups' )
            ->where( [ 'ag_email' => $email ] )
            ->caller( __METHOD__ )
            ->fetchFieldValues();

        foreach ( $assignedGroups as $group ) {
            if ( in_array( $group, self::MANAGED_GROUPS, true ) ) {
                $this->userGroupManager->addUserToGroup( $user, $group );
            }
        }
    }

    /**
     * Sync aepedia_groups for a single user based on a group delta.
     *
     * Called from the UserGroupsChanged hook when a sysop edits group membership
     * via Special:UserRights (or any other MW code path that uses UserGroupManager).
     * Only groups in MANAGED_GROUPS are touched; all others are silently ignored.
     *
     * @param User     $user    The user whose groups changed.
     * @param string[] $added   Groups that were added to the user.
     * @param string[] $removed Groups that were removed from the user.
     */
    public function syncUserGroups( User $user, array $added, array $removed ): void {
        $email = strtolower( trim( $user->getEmail() ) );
        if ( $email === '' ) {
            return;
        }

        $db = $this->dbProvider->getPrimaryDatabase();

        $managedAdded   = array_values( array_intersect( $added,   self::MANAGED_GROUPS ) );
        $managedRemoved = array_values( array_intersect( $removed, self::MANAGED_GROUPS ) );

        if ( !empty( $managedAdded ) ) {
            $db->newInsertQueryBuilder()
                ->insertInto( 'aepedia_groups' )
                ->rows( array_map( static fn( $g ) => [ 'ag_email' => $email, 'ag_group' => $g ], $managedAdded ) )
                ->ignore()
                ->caller( __METHOD__ )
                ->execute();
        }

        if ( !empty( $managedRemoved ) ) {
            $db->newDeleteQueryBuilder()
                ->deleteFrom( 'aepedia_groups' )
                ->where( [ 'ag_email' => $email, 'ag_group' => $managedRemoved ] )
                ->caller( __METHOD__ )
                ->execute();
        }
    }

    // -------------------------------------------------------------------------

    /**
     * Fetch User objects for all accounts matching the given emails, in one query.
     *
     * @param string[] $emails
     * @return User[]
     */
    private function findUsersByEmails( array $emails ): array {
        if ( empty( $emails ) ) {
            return [];
        }

        $rows = $this->dbProvider->getPrimaryDatabase()
            ->newSelectQueryBuilder()
            ->select( [ 'user_id', 'user_email' ] )
            ->from( 'user' )
            ->where( [ 'user_email' => $emails ] )
            ->caller( __METHOD__ )
            ->fetchResultSet();

        $users = [];
        foreach ( $rows as $row ) {
            $users[] = $this->userFactory->newFromId( (int)$row->user_id );
        }
        return $users;
    }

    private function getSystemUser(): User {
        return User::newSystemUser( 'MediaWiki default', [ 'steal' => true ] );
    }
}
