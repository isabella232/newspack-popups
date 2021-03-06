# newspack-popups

AMP-compatible popup notifications.

## Config file

Newspack Campaigns requires a custom config file to provide database credentials and other key data to the lightweight API. The file is named `newspack-popups-config.php` and should automatically be created at the root of the WordPress installation. If for any reason it is not created automatically, manually add this file using the following template:

```
<?php
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', 'root' );
define( 'DB_NAME', 'local' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_PREFIX', 'wp_' );
```

## Segmentation features

The segmentation features rely on visit logging. This is currently opt-in, managed by the `ENABLE_CAMPAIGN_EVENT_LOGGING` flag defined in the aforementioned file:

```
define( 'ENABLE_CAMPAIGN_EVENT_LOGGING', true );
```

The segmentation feature causes amp-access to be added to all pages whether or not campaigns are present. To override this behavior use the `newspack_popups_suppress_insert_amp_access` filter. The filter receives an array of campaigns for the current page. To suppress, return true, for example:

```
add_filter(
	'newspack_popups_suppress_insert_amp_access',
	function( $should_suppress, $campaigns ) {
		if ( empty( $campaigns ) ) {
			return true;
		}
		return $should_suppress;
	},
	10,
	2
);
```
