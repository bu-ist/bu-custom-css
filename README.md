# BU Custom CSS #
**Contributors:** BostonU, inderpreet99  
**Tags:** custom, css  
**Requires at least:** 2.9  
**Tested up to:** 3.1  
**Stable tag:** 1.0  

Enables editing of a custom CSS file, with an option to override the original theme CSS.

## Description ##

This plugin is a fork of [Wordpress.com Custom CSS plugin](http://wordpress.org/extend/plugins/safecss/) which, at the time of the fork, had not been updated for more than 2 years.

Like the original, all CSS code is stored using a custom post_type, which means you can view revision history, compare revisions and restore using the built-in revision engine.

This forked version saves the css into a custom.css file in the upload directory, allowing the user to also edit the CSS through various file editing/uploading protocols like SFTP, SSH, FTP(S).
The plugin will auto import if there is a custom.css present in the site's upload directory, and will continue to auto import anytime it detects changes, so you can work seamlessly on the CSS either inside the WP environment, or externally.

Other improvements include:
* Refreshed the overall look of the admin screen and the frontend preview.
* Removed CSSTidy because it does not support CSS3 features.
* Made the language easier to understand and fixed miscellaneous bugs.

## Installation ##

1. Upload the `bu-custom-css` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Access Custom CSS under Site Design in the WP Admin sidebar.

## Frequently Asked Questions ##

## Screenshots ##

## Changelog ##

### 1.0 ###
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
