<?php

namespace MediaWiki\Extension\AEPedia;

use MediaWiki\MediaWikiServices;
use MediaWiki\User\User;

class UserHooks {

    /**
     * Fired right after a new local user account is created.
     *
     * 1. If the email is not in any school group → block the account.
     * 2. If the email belongs to a school group → apply pre-existing group assignments.
     */
    public static function onLocalUserCreated( User $user, bool $autocreated ): void {
        $email = strtolower( trim( $user->getEmail() ) );

        if ( $email === '' ) {
            if ( $autocreated ) {
                // Autocreated account with no email (e.g. some SSO providers don't forward it) — skip
                return;
            }
            self::blockUser( $user, 'No email address provided.' );
            return;
        }

        $groupManager = MediaWikiServices::getInstance()->getService( 'AEPedia.GroupManager' );

        if ( !$groupManager->isAllowed( $email ) ) {
            self::blockUser( $user, 'Your email address is not registered in any school group on this wiki.' );
            return;
        }

        // Email is valid — apply any group assignments that were already in the DB
        // (e.g. admin imported the groups CSV before this user registered)
        $groupManager->applyGroupsToNewUser( $user );
    }

    /**
     * Fired after a user's group memberships are changed (e.g. via Special:UserRights).
     *
     * Keeps aepedia_groups in sync with actual MW group assignments so that the DB
     * table always reflects the current state. Only groups in GroupManager::MANAGED_GROUPS
     * are touched; all others are silently ignored.
     */
    public static function onUserGroupsChanged(
        User $user,
        array $added,
        array $removed,
        $performer,
        $reason,
        $oldUGMs,
        $newUGMs
    ): void {
        if ( strtolower( trim( $user->getEmail() ) ) === '' ) {
            return;
        }

        MediaWikiServices::getInstance()
            ->getService( 'AEPedia.GroupManager' )
            ->syncUserGroups( $user, $added, $removed );
    }

    private static function blockUser( User $user, string $reason ): void {
        $services  = MediaWikiServices::getInstance();
        $performer = \MediaWiki\User\User::newSystemUser( 'MediaWiki default', [ 'steal' => true ] );

        $services->getBlockUserFactory()
            ->newBlockUser(
                $user,
                $performer,
                'infinity',
                $reason,
                [
                    'isCreateAccountBlocked' => true,
                    'isEmailBlocked'         => false,
                    'isHardBlock'            => true,
                    'isAutoblocking'         => false,
                ]
            )->placeBlock();
    }
}
