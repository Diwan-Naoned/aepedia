<?php

namespace MediaWiki\Extension\AEPedia;

use MediaWiki\MediaWikiServices;
use MediaWiki\User\User;

class UserHooks {

    /**
     * Fired right after a new local user account is created.
     *
     * If the email belongs to a school group, apply pre-existing group
     * assignments so the user immediately gets read/edit access.
     * Users whose email is not in any group simply have no school group
     * and therefore no read/edit permissions (enforced by $wgGroupPermissions).
     */
    public static function onLocalUserCreated( User $user, bool $autocreated ): void {
        MediaWikiServices::getInstance()
            ->getService( 'AEPedia.GroupManager' )
            ->applyGroupsToNewUser( $user );
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
        MediaWikiServices::getInstance()
            ->getService( 'AEPedia.GroupManager' )
            ->syncUserGroups( $user, $added, $removed );
    }
}
