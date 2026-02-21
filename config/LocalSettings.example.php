<?php
# -- Database ------------------------------------------------------------------
$wgDBtype     = "mysql";
$wgDBserver   = "your-mariadb-host";   # e.g. mariadb service name in Dokploy
$wgDBname     = "mediawiki";
$wgDBuser     = "wiki";
$wgDBpassword = "changeme";

# -- Basic wiki settings -------------------------------------------------------
$wgSitename   = "School Wiki";
$wgServer     = "https://wiki.yourschool.fr";
$wgScriptPath = "";
$wgSecretKey  = "replace-with-a-long-random-string";
$wgUpgradeKey = "replace-with-another-random-string";

# -- Language ------------------------------------------------------------------
$wgLanguageCode = "fr";

# -- File uploads --------------------------------------------------------------
$wgEnableUploads  = true;
$wgUploadDirectory = "/var/www/html/images";

# -- Extensions ----------------------------------------------------------------
wfLoadExtension( 'Lockdown' );
wfLoadExtension( 'AEPedia' );

# -- Custom namespaces ---------------------------------------------------------
# One namespace per group. Use even numbers >= 3000 for custom namespaces.
# The odd number (e.g. 3001) is automatically the Talk namespace.
define( 'NS_HR',          3000 );
define( 'NS_HR_TALK',     3001 );
define( 'NS_ACCOUNTING',  3002 );
define( 'NS_ACCOUNTING_TALK', 3003 );
define( 'NS_DIRECTION',   3004 );
define( 'NS_DIRECTION_TALK', 3005 );

$wgExtraNamespaces[NS_HR]              = 'HR';
$wgExtraNamespaces[NS_HR_TALK]         = 'HR_Talk';
$wgExtraNamespaces[NS_ACCOUNTING]      = 'Accounting';
$wgExtraNamespaces[NS_ACCOUNTING_TALK] = 'Accounting_Talk';
$wgExtraNamespaces[NS_DIRECTION]       = 'Direction';
$wgExtraNamespaces[NS_DIRECTION_TALK]  = 'Direction_Talk';

# -- Lockdown: restrict namespaces to their respective groups ------------------
# Only members of the group (+ sysops) can read/edit pages in these namespaces.

$wgNamespacePermissionLockdown[NS_HR]['read']         = [ 'aep_rh', 'sysop' ];
$wgNamespacePermissionLockdown[NS_HR]['edit']         = [ 'aep_rh', 'sysop' ];
$wgNamespacePermissionLockdown[NS_HR_TALK]['read']    = [ 'aep_rh', 'sysop' ];
$wgNamespacePermissionLockdown[NS_HR_TALK]['edit']    = [ 'aep_rh', 'sysop' ];

$wgNamespacePermissionLockdown[NS_ACCOUNTING]['read']         = [ 'aep_tresorerie', 'sysop' ];
$wgNamespacePermissionLockdown[NS_ACCOUNTING]['edit']         = [ 'aep_tresorerie', 'sysop' ];
$wgNamespacePermissionLockdown[NS_ACCOUNTING_TALK]['read']    = [ 'aep_tresorerie', 'sysop' ];
$wgNamespacePermissionLockdown[NS_ACCOUNTING_TALK]['edit']    = [ 'aep_tresorerie', 'sysop' ];

$wgNamespacePermissionLockdown[NS_DIRECTION]['read']         = [ 'aep_ca', 'sysop' ];
$wgNamespacePermissionLockdown[NS_DIRECTION]['edit']         = [ 'aep_ca', 'sysop' ];
$wgNamespacePermissionLockdown[NS_DIRECTION_TALK]['read']    = [ 'aep_ca', 'sysop' ];
$wgNamespacePermissionLockdown[NS_DIRECTION_TALK]['edit']    = [ 'aep_ca', 'sysop' ];

# -- General permissions -------------------------------------------------------
# Anonymous users cannot read anything (school wiki = logged-in only)
$wgGroupPermissions['*']['read']           = false;
$wgGroupPermissions['*']['edit']           = false;
$wgGroupPermissions['*']['createaccount']  = true;  # Must stay true so registration works

# Logged-in users can read and edit public pages
$wgGroupPermissions['user']['read']  = true;
$wgGroupPermissions['user']['edit']  = true;

# Custom groups (must exist for Lockdown to reference them)
$wgGroupPermissions['aep_rh']         = [];
$wgGroupPermissions['aep_tresorerie']  = [];
$wgGroupPermissions['aep_ca']          = [];
