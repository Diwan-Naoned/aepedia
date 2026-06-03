<?php

namespace MediaWiki\Extension\AEPedia\Auth;

use MediaWiki\Auth\AbstractPreAuthenticationProvider;
use MediaWiki\MediaWikiServices;
use StatusValue;

/**
 * Rejects account creation before any verification email is sent if the
 * submitted email is not present in aepedia_groups.
 *
 * This runs at AuthManager::beginAccountCreation() time, before any
 * PrimaryAuthenticationProvider, so unauthorized signups never produce a
 * user row, a LocalUserCreated hook call, or an outbound email.
 */
class EmailAllowlistPreAuthenticationProvider extends AbstractPreAuthenticationProvider {

    public function testForAccountCreation( $user, $creator, array $reqs ) {
        $groupManager = MediaWikiServices::getInstance()->getService( 'AEPedia.GroupManager' );
        if ( !$groupManager->isEmailAllowed( $user->getEmail() ) ) {
            return StatusValue::newFatal( 'aepedia-signup-not-allowed' );
        }
        return StatusValue::newGood();
    }
}
