<?php

use MediaWiki\Extension\AEPedia\AllowlistManager;
use MediaWiki\Extension\AEPedia\GroupManager;
use MediaWiki\MediaWikiServices;

return [
    'AEPedia.AllowlistManager' => static function ( MediaWikiServices $services ): AllowlistManager {
        return new AllowlistManager(
            $services->getConnectionProvider(),
            $services->getUserFactory(),
            $services->getUserGroupManager(),
            $services->getBlockUserFactory(),
            $services->getUnblockUserFactory()
        );
    },

    'AEPedia.GroupManager' => static function ( MediaWikiServices $services ): GroupManager {
        return new GroupManager(
            $services->getConnectionProvider(),
            $services->getUserGroupManager(),
            $services->getUserFactory()
        );
    },
];
