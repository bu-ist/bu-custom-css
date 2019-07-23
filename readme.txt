=== BU Custom CSS ===
Contributors: automattic, BostonU, inderpreet99, awbauer
Tags: custom, css
Requires at least: 4.0
Tested up to: 4.1.9
Stable tag: 2.1.1

Enables editing of a custom CSS file, with an option to override the original theme CSS.

== Description ==

This plugin is a fork of [Jetpack's Custom CSS module](http://wordpress.org/plugins/jetpack/). This plugin provides the Custom CSS module without the connection to WordPress.com. It also lets users use Custom CSS features without needing to install the entire Jetpack plugin.

Like the original, all CSS code is stored using a custom post_type, which means you can view revision history, compare revisions and restore using the built-in revision engine.

This forked version saves the CSS into a custom.min.css and custom.css file in the upload directory to serve CSS without going through the database. It also supports the SCRIPT_DEBUG constant.

Other improvements include:
* W3C Validator link
* BU look and text changes
* Included Jetpack's User Agent class for device detection
* BU Mobile plugin support to add Custom CSS for mobile themes

== Installation ==

1. Upload the `bu-custom-css` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Access Custom CSS under Appearance in the WP Admin sidebar.

== Changelog ==

= 2.1.1 =
* Updated php class constructor methods to avoid deprecated notices.

= 2.1.0 =
* Merge changes from Jetpack 4.4.1 Custom CSS module

= 2.0.3 =
* Uses `site_url()` function to include CSS using correct protocol

= 2.0.2 =
* Revert 2.0.1 slashes change (caused extra slashes to appear in random places)
* Disable all CSS processing (kses, preg_replace, strip_tags)
* Add IE slash fix

= 2.0.1 =
* Do not discard invalid properties
* Add slashes to saved post content to avoid IE backslashes from getting stripped

= 2.0 =
* Import Jetpack's Custom CSS module from 3.4-beta
* Port previous changes on top of Jetpack's code
* Remove physical custom.css file sync functionality
* Add custom.min.css file support

= 1.0.3 =
* fix deprecated revisions functions for WP 3.6 support (backport from jetpack)
* textarea styling for WP 3.6

= 1.0.2 =
* live cache busting added

= 1.0.1 =
* bu-mobile support: automatic switching on preview to mobile and non-mobile
* add "no revisions" message, UI cleanup

= 1.0 =
* Fork Wordpress.com Custom CSS Plugin version 1.5
* Fix preview stylesheet urls
* Fix preview js error
* Fix dupliate revisions
* Remove CSSTidy (doesn't support CSS3)
* Sync custom.css files in the upload dir with editor
* Restructure code
* Add metaboxes
* Add BU Warning (BU users only)
* Add BU Mobile support (BU users only)
* Use preview flag to escape preview caching bug
