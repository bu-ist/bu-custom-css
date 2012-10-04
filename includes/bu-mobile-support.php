<?php

define('BUCC_FILENAME_MOBILE', 'custom-mobile.css');


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
function bucc_mobile_css_file($filename) {
	if( bucc_is_mobile() ) {
		return apply_filters('bucc_filename_mobile', BUCC_FILENAME_MOBILE);
	}
	
	return $filename;
}
add_filter('bucc_filename', 'bucc_mobile_css_file');


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
		$title  = __( 'Custom Mobile CSS', 'safecss' );
		$hook = add_submenu_page( $parent, $title, $title, 'switch_themes', 'editcss-mobile', 'bucc_admin' );
		add_action( "admin_print_scripts-$hook", 'bucc_enqueue_scripts' );
		add_action( "admin_print_styles-$hook", 'bucc_enqueue_styles' );
	}
}
add_action('bucc_menu', 'bucc_mobile_menu');