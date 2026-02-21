<?php

namespace MediaWiki\Extension\AEPedia;

use MediaWiki\MediaWikiServices;
use MediaWiki\User\User;

class RegistrationHooks {

    /**
     * Fired right after a new local user account is created.
     *
     * 1. If the email is not in the allowlist → block the account.
     * 2. If the email is allowlisted → apply any pre-existing group assignments.
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

        $services      = MediaWikiServices::getInstance();
        $allowlistMgr  = $services->getService( 'AEPedia.AllowlistManager' );

        if ( !$allowlistMgr->isAllowed( $email ) ) {
            self::blockUser( $user, 'Your email address is not authorized to create an account on this wiki.' );
            return;
        }

        // Email is valid — apply any group assignments that were already in the DB
        // (e.g. admin imported the groups CSV before this user registered)
        $services->getService( 'AEPedia.GroupManager' )->applyGroupsToNewUser( $user );
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
