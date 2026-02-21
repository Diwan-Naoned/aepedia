<?php

namespace MediaWiki\Extension\AEPedia;

use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Central service for managing AEPedia group assignments.
 *
 * This is the single place that:
 *   1. Writes to aepedia_groups (the DB table, source of truth for pre-registration assignments)
 *   2. Immediately applies MW user groups to existing accounts
 *
 * Called by:
 *   - SpecialAEPediaAdmin (on CSV import)
 *   - RegistrationHooks (on new account creation, to apply pre-existing assignments)
 */
class GroupManager {

    /**
     * Groups managed by AEPedia. Groups outside this list are never touched.
     * Add new groups here when you add namespaces in LocalSettings.php.
     */
    public const MANAGED_GROUPS = [
        'aep_rh',
        'aep_tresorerie',
        'aep_ca',
    ];

    private IConnectionProvider $dbProvider;
    private UserGroupManager $userGroupManager;
    private UserFactory $userFactory;

    public function __construct(
        IConnectionProvider $dbProvider,
        UserGroupManager $userGroupManager,
        UserFactory $userFactory
    ) {
        $this->dbProvider       = $dbProvider;
        $this->userGroupManager = $userGroupManager;
        $this->userFactory      = $userFactory;
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
     * - New emails have the group added to their MW account (if they have one).
     *
     * @param string   $group     One of MANAGED_GROUPS.
     * @param string[] $newEmails The complete new member list for this group.
     * @return array{added: int, removed: int}
     */
    public function replaceGroupMembers( string $group, array $newEmails ): array {
        if ( !in_array( $group, self::MANAGED_GROUPS, true ) ) {
            return [ 'added' => 0, 'removed' => 0 ];
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

        // Apply MW group changes to existing accounts — fetch all users in two bulk queries
        foreach ( $this->findUsersByEmails( $toAdd ) as $user ) {
            $this->userGroupManager->addUserToGroup( $user, $group );
        }
        foreach ( $this->findUsersByEmails( $toRemove ) as $user ) {
            $this->userGroupManager->removeUserFromGroup( $user, $group );
        }

        return [ 'added' => count( $toAdd ), 'removed' => count( $toRemove ) ];
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
}
