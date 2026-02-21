<?php

namespace MediaWiki\Extension\AEPedia;

use DatabaseUpdater;

class SchemaHooks {
    /**
     * Called by maintenance/update.php to create/update DB tables.
     */
    public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ): void {
        $updater->addExtensionTable(
            'aepedia_allowlist',
            __DIR__ . '/../sql/tables.sql'
        );
        $updater->addExtensionTable(
            'aepedia_groups',
            __DIR__ . '/../sql/tables.sql'
        );
    }
}
