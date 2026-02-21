<?php

namespace MediaWiki\Extension\AEPedia;

use MediaWiki\Block\BlockUserFactory;
use MediaWiki\Block\UnblockUserFactory;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Manages the registration email allowlist.
 *
 * An import fully replaces the previous allowlist:
 *   - Accounts whose email is no longer in the list are blocked immediately,
 *     unless they belong to the 'aep_external' group.
 *   - Accounts whose email is back in the list are unblocked automatically.
 */
class AllowlistManager {

    /** Accounts in this group are never blocked by an allowlist import. */
    private const EXTERNAL_GROUP = 'aep_external';

    /** Exact block reason set by allowlist imports. Used to identify blocks we can safely remove. */
    private const BLOCK_REASON = 'Email address removed from the AEPedia allowlist.';

    private IConnectionProvider $dbProvider;
    private UserFactory $userFactory;
    private UserGroupManager $userGroupManager;
    private BlockUserFactory $blockUserFactory;
    private UnblockUserFactory $unblockUserFactory;

    public function __construct(
        IConnectionProvider $dbProvider,
        UserFactory $userFactory,
        UserGroupManager $userGroupManager,
        BlockUserFactory $blockUserFactory,
        UnblockUserFactory $unblockUserFactory
    ) {
        $this->dbProvider         = $dbProvider;
        $this->userFactory        = $userFactory;
        $this->userGroupManager   = $userGroupManager;
        $this->blockUserFactory   = $blockUserFactory;
        $this->unblockUserFactory = $unblockUserFactory;
    }

    /**
     * Check whether an email is in the allowlist.
     * Used by RegistrationHooks at account creation time.
     */
    public function isAllowed( string $email ): bool {
        return (bool)$this->dbProvider->getPrimaryDatabase()
            ->newSelectQueryBuilder()
            ->select( 'al_email' )
            ->from( 'aepedia_allowlist' )
            ->where( [ 'al_email' => strtolower( trim( $email ) ) ] )
            ->caller( __METHOD__ )
            ->fetchField();
    }

    /**
     * Return all emails currently in the allowlist, sorted alphabetically.
     * Used by the admin UI for display.
     *
     * @return string[]
     */
    public function getAllowlistEmails(): array {
        return $this->dbProvider->getPrimaryDatabase()
            ->newSelectQueryBuilder()
            ->select( 'al_email' )
            ->from( 'aepedia_allowlist' )
            ->orderBy( 'al_email' )
            ->caller( __METHOD__ )
            ->fetchFieldValues();
    }

    /**
     * Replace the entire allowlist with the provided emails.
     *
     * - Removes emails no longer in the list and blocks their accounts
     *   (unless the account is in the aep_external group).
     * - Adds new emails and unblocks their accounts if they were previously blocked.
     *
     * @param string[] $newEmails
     * @return array{added: int, removed: int, blocked: int, unblocked: int}
     */
    public function replaceAllowlist( array $newEmails ): array {
        $db        = $this->dbProvider->getPrimaryDatabase();
        $newEmails = array_values( array_unique( array_map(
            static fn( string $e ) => strtolower( trim( $e ) ),
            $newEmails
        ) ) );

        // Start the transaction before reading so the diff is computed against
        // a locked snapshot — prevents concurrent imports from racing.
        $db->startAtomic( __METHOD__ );

        $currentEmails = $db->newSelectQueryBuilder()
            ->select( 'al_email' )
            ->from( 'aepedia_allowlist' )
            ->forUpdate()
            ->caller( __METHOD__ )
            ->fetchFieldValues();

        $toAdd    = array_diff( $newEmails, $currentEmails );
        $toRemove = array_diff( $currentEmails, $newEmails );

        if ( !empty( $toRemove ) ) {
            $db->newDeleteQueryBuilder()
                ->deleteFrom( 'aepedia_allowlist' )
                ->where( [ 'al_email' => $toRemove ] )
                ->caller( __METHOD__ )
                ->execute();
        }

        if ( !empty( $toAdd ) ) {
            $db->newInsertQueryBuilder()
                ->insertInto( 'aepedia_allowlist' )
                ->rows( array_map( static fn( $e ) => [ 'al_email' => $e ], $toAdd ) )
                ->ignore()
                ->caller( __METHOD__ )
                ->execute();
        }

        $db->endAtomic( __METHOD__ );

        // Apply account blocks/unblocks outside the transaction.
        // Fetch all relevant users in two bulk queries instead of one per email.
        $blocked   = 0;
        $unblocked = 0;

        foreach ( $this->findUsersByEmails( $toRemove ) as $user ) {
            if ( in_array( self::EXTERNAL_GROUP, $this->userGroupManager->getUserGroups( $user ), true ) ) {
                continue;
            }
            // Skip users that are already blocked — don't overwrite an admin's block
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
                ->newUnblockUser( $user, $this->getSystemUser(), 'Email address re-added to the AEPedia allowlist.' )
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

    // -------------------------------------------------------------------------

    /**
     * Fetch User objects for all accounts matching the given emails, in one query.
     *
     * @param string[] $emails
     * @return \MediaWiki\User\User[]
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

    private function getSystemUser(): \MediaWiki\User\User {
        return \MediaWiki\User\User::newSystemUser( 'MediaWiki default', [ 'steal' => true ] );
    }
}
