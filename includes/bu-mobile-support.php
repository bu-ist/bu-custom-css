<?php

define('BUCC_FILENAME_MOBILE', 'custom-mobile.min.css');
define('BUCC_FILENAME_MOBILE_DEBUG', 'custom-mobile.css');


/**
 * Returns true if the user is working with either a mobile css post (WP_Query), mobile site (frontend), or Custom Mobile CSS page
 * 
 * @global type $post
 * @return boolean true|false
 */
function bucc_is_mobile() {
	global $post;
	
	if( !function_exists('bu_mobile_init') ) return false;
	
	// if the current post is mobile, then YES
	if ( $post and $post->post_name == BUCC_POST_TYPE . '-mobile' ) {
		return true;
	}
	
	// if we're on mobile css edit page, or looking at the frontend (while being mobile), then YES
	if( $_GET['page'] == 'editcss-mobile' or (!is_admin() and bu_mobile_wants_mobile()) ) {
		return true;
	}
	
	return false;
}


/**
 * When working with mobile, replaces the css file (wherever) with a mobile one
 * 
 * @param string $filename
 * @return string
 */
function bucc_mobile_css_file( $filename, $min ) {
	if( bucc_is_mobile() ) {
		if ( $min ) {
			return apply_filters('bucc_filename_mobile', BUCC_FILENAME_MOBILE);
		} else {
			return apply_filters('bucc_filename_mobile', BUCC_FILENAME_MOBILE_DEBUG);
		}
	}
	
	return $filename;
}
add_filter('bucc_filename', 'bucc_mobile_css_file', 10, 2);


/**
 * When working with mobile, replaces the redirect url with a mobile one
 * 
 * @global type $post
 * @param type $url
 * @return string
 */
function bucc_mobile_redirect_url($url) {
	return $url . (bucc_is_mobile() ? '-mobile' : '');
}
add_filter('bucc_redirect_url', 'bucc_mobile_redirect_url');


/**
 * If bu-mobile plugin enabled, use wantsMobile parameters
 * Else, use as is.
 * 
 * @param string $url
 * @return string preview url
 */
function bucc_mobile_preview_url($url) {
	if( function_exists('bu_mobile_init') ) {
		return add_query_arg( 'wantsMobile', bucc_is_mobile() ? 'true' : 'false', $url );
	}
	return $url;
}
add_filter('bucc_preview_link', 'bucc_mobile_preview_url');


/**
 * When working with mobile, appends the parameter string with -mobile
 * 
 * @param type $option
 * @return string
 */
function bucc_mobile_option($option) {
	if( bucc_is_mobile() ) {
		return $option . '-mobile';
	}
	return $option;
}
add_filter('bucc_option', 'bucc_mobile_option');


/**
 * When working with mobile, replaces the stylesheet with a mobile one
 * 
 * @param type $stylesheet
 * @return type
 */
function bucc_mobile_current_stylesheet($stylesheet) {
	if ( bucc_is_mobile() ) {
		return bucc_mobile_stylesheet();
	}
	return $stylesheet;
}
add_filter('bucc_current_stylesheet', 'bucc_mobile_current_stylesheet');


/**
 * Provides the stylesheet of the mobile version of the current theme
 * 
 * @return boolean|string
 */
function bucc_mobile_stylesheet() {
	$theme = get_theme(bu_mobile_theme_name());
	if( !isset($theme['Stylesheet Files']) or count($theme['Stylesheet Files']) == 0 ) {
		return false;
	}
	
	$stylesheet = trailingslashit($theme['Theme Root URI']) . $theme['Stylesheet'] . '/style.css';
	return $stylesheet;
}


/**
 * Adds a Custom Mobile CSS link to the sidebar under Site Design
 * 
 * @param type $parent
 */
function bucc_mobile_menu($parent) {
	if( /* current_theme_supports('bu-custom-css-mobile') and */ function_exists('bu_mobile_init') ) {
		$title = __( 'Custom Mobile CSS', 'jetpack' );
		$hook = add_theme_page( $title, $title, 'edit_theme_options', 'editcss-mobile', array( 'Jetpack_Custom_CSS', 'admin' ) );

		add_action( "load-revision.php", array( 'Jetpack_Custom_CSS', 'prettify_post_revisions' ) );
		add_action( "load-$hook", array( 'Jetpack_Custom_CSS', 'update_title' ) );
	}
}
add_action('bucc_menu', 'bucc_mobile_menu');