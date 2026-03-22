<?php

use MediaWiki\Extension\AEPedia\GroupManager;
use MediaWiki\MediaWikiServices;

return [
    'AEPedia.GroupManager' => static function ( MediaWikiServices $services ): GroupManager {
        return new GroupManager(
            $services->getConnectionProvider(),
            $services->getUserGroupManager(),
            $services->getUserFactory(),
            $services->getBlockUserFactory(),
            $services->getUnblockUserFactory()
        );
    },
];
