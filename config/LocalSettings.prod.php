<?php
# Further documentation for configuration settings may be found at:
# https://www.mediawiki.org/wiki/Manual:Configuration_settings

# Protect against web entry
if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

## Uncomment this to disable output compression
# $wgDisableOutputCompression = true;

$wgSitename = "AEPedia";

## The URL base path to the directory containing the wiki;
## defaults for all runtime URL paths are based off of this.
## For more information on customizing the URLs
## (like /w/index.php/Page_title to /wiki/Page_title) please see:
## https://www.mediawiki.org/wiki/Manual:Short_URL
$wgScriptPath = "";

## The protocol and server name to use in fully-qualified URLs
$domain = getenv( 'DOMAIN_NAME' );
$wgServer = "https://$domain";

## The URL path to static resources (images, scripts, etc.)
$wgResourceBasePath = $wgScriptPath;

## The URL paths to the logo.  Make sure you change this from the default,
## or else you'll overwrite your logo when you upgrade!
$wgLogos = [
	'1x' => "$wgResourceBasePath/images/LOGO-DIWAN-naoned.png",
	'icon' => "$wgResourceBasePath/images/LOGO-DIWAN-naoned.png",
];

## UPO means: this is also a user preference option

$wgEnableEmail = true;
$wgEnableUserEmail = true; # UPO

$wgEmergencyContact = getenv( 'CONTACT_EMAIL' ) ?: "aep.naoned@gmail.com";
$wgPasswordSender = getenv( 'CONTACT_EMAIL' ) ?: "aep.naoned@gmail.com";

$wgEnotifUserTalk = true; # UPO
$wgEnotifWatchlist = true; # UPO
$wgEmailAuthentication = true;

$wgSMTP = [
    'host'      => 'in-v3.mailjet.com', // could also be an IP address. Where the SMTP server is located. If using SSL or TLS, add the prefix "ssl://" or "tls://".
    'IDHost'    => $domain,      // Generally this will be the domain name of your website (aka mywiki.org)
    'localhost' => $domain,      // Same as IDHost above; required by some mail servers
    'port'      => 587,                // Port to use when connecting to the SMTP server
    'auth'      => true,               // Should we use SMTP authentication (true or false)
    'username'  => getenv( 'SMTP_USERNAME' ),     // Username to use for SMTP authentication (if being used)
    'password'  => getenv( 'SMTP_PASSWORD' )       // Password to use for SMTP authentication (if being used)
];

## Database settings
$wgDBtype = "mysql";
$wgDBserver = getenv( 'DB_SERVER' );
$wgDBname = getenv( 'DB_NAME' );
$wgDBuser = getenv( 'DB_USER' );
$wgDBpassword = getenv( 'DB_PASSWORD' );

# MySQL specific settings
$wgDBprefix = "";
$wgDBssl = false;

# MySQL table options to use during installation or update
$wgDBTableOptions = "ENGINE=InnoDB, DEFAULT CHARSET=binary";

# Shared database table
# This has no effect unless $wgSharedDB is also set.
$wgSharedTables[] = "actor";

## Shared memory settings
$wgMainCacheType = CACHE_ACCEL;
$wgMemCachedServers = [];

## To enable image uploads, make sure the 'images' directory
## is writable, then set this to true:
$wgEnableUploads = true;
$wgUseImageMagick = true;
$wgImageMagickConvertCommand = "/usr/bin/convert";

# InstantCommons allows wiki to use images from https://commons.wikimedia.org
$wgUseInstantCommons = true;

# Periodically send a pingback to https://www.mediawiki.org/ with basic data
# about this MediaWiki instance. The Wikimedia Foundation shares this data
# with MediaWiki developers to help guide future development efforts.
$wgPingback = false;

# Site language code, should be one of the list in ./includes/languages/data/Names.php
$wgLanguageCode = "fr";

# Time zone
$wgLocaltimezone = "Europe/Paris";

## Set $wgCacheDirectory to a writable directory on the web server
## to make your wiki go slightly faster. The directory should not
## be publicly accessible from the web.
#$wgCacheDirectory = "$IP/cache";

$wgSecretKey = getenv( 'SECRET_KEY' );

# Changing this will log out all existing sessions.
$wgAuthenticationTokenVersion = getenv( 'AUTH_TOKEN_VERSION' ) ?: "1";

# Site upgrade key. Must be set to a string (default provided) to turn on the
# web installer while LocalSettings.php is in place
$wgUpgradeKey = getenv( 'UPGRADE_KEY' );

## For attaching licensing metadata to pages, and displaying an
## appropriate copyright notice / icon. GNU Free Documentation
## License and Creative Commons licenses are supported so far.
$wgRightsPage = ""; # Set to the title of a wiki page that describes your license/copyright
$wgRightsUrl = "";
$wgRightsText = "";
$wgRightsIcon = "";

# Path to the GNU diff3 utility. Used for conflict resolution.
$wgDiff3 = "/usr/bin/diff3";

# The following permissions were set based on your choice in the installer
# Those are being overriden by Lockdown config later in this file
#$wgGroupPermissions["*"]["createaccount"] = false;
#$wgGroupPermissions["*"]["edit"] = false;
#$wgGroupPermissions["*"]["read"] = false;

## Default skin: you can change the default skin. Use the internal symbolic
## names, e.g. 'vector' or 'monobook':
$wgDefaultSkin = "vector-2022";

# Enabled skins.
# The following skins were automatically enabled:
wfLoadSkin( 'MinervaNeue' );
wfLoadSkin( 'MonoBook' );
wfLoadSkin( 'Timeless' );
wfLoadSkin( 'Vector' );

# Enabled extensions. Most of the extensions are enabled by adding
# wfLoadExtension( 'ExtensionName' );
# to LocalSettings.php. Check specific extension documentation for more details.
# The following extensions were automatically enabled:
wfLoadExtension( 'CategoryTree' );
wfLoadExtension( 'Cite' );
wfLoadExtension( 'CodeEditor' );
wfLoadExtension( 'DiscussionTools' );
wfLoadExtension( 'Echo' );
wfLoadExtension( 'InputBox' );
wfLoadExtension( 'Linter' );
wfLoadExtension( 'PageImages' );
wfLoadExtension( 'PdfHandler' );
wfLoadExtension( 'SyntaxHighlight_GeSHi' );
wfLoadExtension( 'TextExtracts' );
wfLoadExtension( 'Thanks' );
wfLoadExtension( 'VisualEditor' );
wfLoadExtension( 'WikiEditor' );

# End of automatically generated settings.
# Add more configuration options below.

# -- Debug --------------------------------------------------------------------
# Uncomment to ease the development.
#$wgShowExceptionDetails = true;
#$wgDebugToolbar = true;
#$wgDevelopmentWarnings = true;
#
# Also add those 2 lines at the very begining of this file
# error_reporting( -1 );
# ini_set( 'display_errors', 1 );

# -- Language ------------------------------------------------------------------
$wgULSLanguages = [ 'fr', 'br' ];
$wgULSAnonCanChangeLanguage = true;

# -- Extensions ----------------------------------------------------------------
wfLoadExtension( 'Lockdown' );
wfLoadExtension( 'UniversalLanguageSelector' );
wfLoadExtension( 'MassMessage' );
wfLoadExtension( 'MassMessageEmail' );
wfLoadExtension( 'Translate' );
wfLoadExtension( 'TranslationNotifications' );
wfLoadExtension( 'AEPedia' );

# -- Translate -----------------------------------------------------------------
$wgTranslateDocumentationLanguageCode = 'qqq';
$wgTranslatePageTranslationULS = true;
$wgGroupPermissions['user']['translate'] = true;
$wgGroupPermissions['user']['skipcaptcha'] = true;
$wgGroupPermissions['user']['pagetranslation'] = true;

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

