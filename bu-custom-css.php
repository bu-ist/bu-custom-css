<?php
/*
Plugin Name: BU Custom CSS Editor
Description: Allows CSS editing with an option to override the original theme CSS.
Author: Boston University (IS&T)
Author URI: http://www.bu.edu/tech/
Version: 1.0
*/

/**
Copyright Automattic
Copyright Boston University

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

**/


define('BUCC_FILENAME', 'custom.css');
define('BUCC_POST_TYPE', 'bucc');

require_once( dirname(__FILE__) . '/includes/bu-mobile-support.php' );

/**
 * Add local textdomain
 */
add_action( 'init', 'bucc_load_plugin_textdomain' );

function bucc_load_plugin_textdomain() {
	load_plugin_textdomain( 'safecss', false, 'bu-custom-css/languages' );
}

/**
 * Redirect users to the Custom CSS Editor page from revisions page
 * 
 * @global type $post
 * @param str $redirect
 * @return string redirect url
 */
function bucc_revision_redirect( $redirect ) {
	global $post;

	if ( BUCC_POST_TYPE == $post->post_type ) {
		if ( strstr( $redirect, 'action=edit' ) )
			return apply_filters('bucc_redirect_url', 'themes.php?page=editcss');

		if ( 'edit.php' == $redirect )
			return '';

	}

	return $redirect;
}
add_filter('revision_redirect', 'bucc_revision_redirect');

/**
 * Redirect users to the Custom CSS Editor page from post edit page
 * 
 * @global type $post
 * @param str $redirect
 * @return string redirect url
 */
function bucc_revision_post_link( $post_link ) {
	global $post;

	if ( isset( $post ) && ( BUCC_POST_TYPE == $post->post_type ) )
		if ( strstr( $post_link, 'action=edit' ) )
			$post_link = apply_filters('bucc_redirect_url', 'themes.php?page=editcss');

	return $post_link;
}
// Override the edit link, the default link causes a redirect loop
add_filter('get_edit_post_link', 'bucc_revision_post_link');

/**
 * Get the BUCC_POST_TYPE record
 *
 * @return array
 */
function bucc_get_post() {
	$slug = apply_filters('bucc_option', BUCC_POST_TYPE);
	
	if ( $a = array_shift( get_posts( array( 'name' => $slug, 'numberposts' => 1, 'post_type' => BUCC_POST_TYPE, 'post_status' => 'publish' ) ) ) )
		$safecss_post = get_object_vars( $a );
	else
		$safecss_post = false;

	return $safecss_post;
}

/**
 * Get the current revision of the original BUCC_POST_TYPE record
 *
 * @return object
 */
function bucc_get_current_revision() {

	if ( !$safecss_post = bucc_get_post() )
		return false;

	if ( !empty( $safecss_post['ID'] ) )
		$revisions = wp_get_post_revisions( $safecss_post['ID'], 'orderby=ID&order=DESC&limit=1' );

	// Empty array if no revisions exist so return
	if ( empty( $revisions ) ) {
		return $safecss_post;

	// Return the first entry in $revisions, this will be the current revision
	} else {
		$current_revision = get_object_vars( array_shift( $revisions ) );
		return $current_revision;
	}
}

/**
 * Save new revision of CSS
 * Checks to see if content was modified before really saving
 *
 * @param string $css
 * @param bool $is_preview
 * @return bool
 */
function bucc_save_revision( $css, $is_preview = false ) {

	$css = apply_filters('pre_bucc_save_revision', $css);
	
	// If null, there was no original css post, so create one
	if ( !$safecss_post = bucc_get_post() ) {
		// previews should save an empty post, so we can work with revisions
		$post = array();
		$post['post_content'] = $is_preview ? '' : $css;
		$post['post_title']   = apply_filters('bucc_option', BUCC_POST_TYPE);
		$post['post_name']    = apply_filters('bucc_option', BUCC_POST_TYPE);
		$post['post_status']  = 'publish';
		$post['post_type']    = BUCC_POST_TYPE;

		// Insert the CSS into wp_posts
		$post_id = wp_insert_post( $post );
		
		// when first saving a post, custom.css is added to wp_head, so we must clear cache
		if( function_exists('invalidate_blog_cache') ) {
			invalidate_blog_cache();
		}

		do_action('post_bucc_save_revision_new', $safecss_post, $is_preview);
		
		if( $is_preview ) {
			$safecss_post = bucc_get_post();
		} else {
			return true;
		}
	}

	$safecss_post['post_content'] = $css;

	// Do not update post if we are only saving a preview
	if ( false === $is_preview ) {
		wp_update_post( $safecss_post );
	} else {
		_wp_put_post_revision( $safecss_post );
	}
	
	do_action('post_bucc_save_revision', $safecss_post, $is_preview);
}

function bucc_skip_stylesheet() {

	if ( bucc_is_preview() )
		return (bool) ( get_option(apply_filters('bucc_option', 'safecss_preview_add')) == 'no' );
	else
		return (bool) ( get_option(apply_filters('bucc_option', 'safecss_add')) == 'no' );
}

function bucc_init() {
	// Register BUCC_POST_TYPE as a custom post_type
	register_post_type( BUCC_POST_TYPE, array(
		'supports' => array( 'revisions' )
	) );

	// Short-circuit WP if this is a CSS stylesheet request
	if ( isset( $_GET['custom-css'] ) ) {
		header( 'Content-Type: text/css', true, 200 );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + 31536000) . ' GMT' ); // 1 year
		$blog_id = $_GET['csblog'];

		if ( is_int( $blog_id ) ) {
			switch_to_blog( $blog_id );
			$current_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
		}

		bucc_print();

		exit;
	}

	add_action('wp_head', 'bucc_style', 101);

	if ( !current_user_can( 'switch_themes' ) && !is_super_admin() )
		return;

	add_action('admin_menu', 'bucc_menu');

	if ( isset( $_POST['safecss'] ) && false == strstr( $_SERVER[ 'REQUEST_URI' ], 'options.php' ) ) {
		check_admin_referer('safecss');

		// Remove wp_filter_post_kses, this causes CSS escaping issues
		remove_filter( 'content_save_pre', 'wp_filter_post_kses' );
		remove_filter( 'content_filtered_save_pre', 'wp_filter_post_kses' );
		remove_all_filters( 'content_save_pre' );

		$css = $orig = stripslashes( $_POST['safecss'] );
		$css = preg_replace( '/\\\\([0-9a-fA-F]{4})/', '\\\\\\\\$1', $prev = $css );

		if ( $css != $prev )
			$warnings[] = 'preg_replace found stuff';

		// Some people put weird stuff in their CSS, KSES tends to be greedy
		$css = str_replace( '<=', '&lt;=', $css );

		// Why KSES instead of strip_tags?  Who knows?
		$css = wp_kses_split( $prev = $css, array(), array() );
		$css = str_replace( '&gt;', '>', $css ); // kses replaces lone '>' with &gt;
		
		// Why both KSES and strip_tags?  Because we just added some '>'.
		$css = strip_tags( $css );

		if ( $css != $prev )
			$warnings[] = 'kses found stuff';

		if ( intval( $_POST['custom_content_width'] ) > 0 )
			$custom_content_width = intval( $_POST['custom_content_width'] );
		else
			$custom_content_width = false;

		if ( $_POST['add_to_existing'] == 'false' )
			$add_to_existing = 'no';
		else
			$add_to_existing = 'yes';

		if ( 'preview' == $_POST['action'] || bucc_is_freetrial() ) {
			$is_preview = true;

			// Save the CSS
			bucc_save_revision( $css, $is_preview );

			// Cache Buster
			update_option( apply_filters('bucc_option', 'safecss_preview_rev'), intval( get_option( apply_filters('bucc_option', 'safecss_preview_rev') ) ) + 1 );
			update_option( apply_filters('bucc_option', 'safecss_preview_add'), $add_to_existing );
			$link = add_query_arg( 'csspreview', 'true', get_option( 'home' ) );
			$link = add_query_arg( 'preview', 'true', $link );
			$link = apply_filters( 'bucc_preview_link', $link );
			wp_redirect( $link );

			exit;
		}

		// Save the CSS
		bucc_save_revision( $css );
		update_option( apply_filters('bucc_option', 'safecss_rev'), intval( get_option( apply_filters('bucc_option', 'safecss_rev') ) ) + 1 );
		
		$add_to_existing_option = apply_filters('bucc_option', 'safecss_add');
		if( get_option($add_to_existing_option) != $add_to_existing ) {
			update_option( $add_to_existing_option, $add_to_existing );
			
			// since add_to_existing option changes all the site's template where style.css is used, we must reset cache
			if( function_exists('invalidate_blog_cache') ) {
				invalidate_blog_cache();
			}
		}

		add_action( 'admin_notices', 'bucc_saved' );
	}

	// Modify all internal links so that preview state persists
	if ( bucc_is_preview() )
		ob_start('bucc_buffer');
}
add_action('init', 'bucc_init');

function bucc_is_preview() {
	return isset( $_GET['csspreview'] ) && $_GET['csspreview'] === 'true';
}

function bucc_is_freetrial() {
	return false;
}

function bucc() {
	if ( bucc_is_freetrial() && ( !current_user_can( 'switch_themes' ) || !bucc_is_preview() ) && !is_admin() )
		return '/* */';

	$option = ( bucc_is_preview() || bucc_is_freetrial() ) ? 'safecss_preview' : 'safecss';
	$css    = '';

	if ( 'safecss' == $option ) {
		if ( $safecss_post = bucc_get_post() )
			$css = $safecss_post['post_content'];
	}

	if ( 'safecss_preview' == $option ) {
		$safecss_post = bucc_get_current_revision();
		$css = $safecss_post['post_content'];
	}

	$css = str_replace( array( '\\\00BB \\\0020', '\0BB \020', '0BB 020' ), '\00BB \0020', $css );

	if ( empty($css) and $safecss_file = bucc_get_file() ) {
		$css = file_get_contents($safecss_file);
	}

	return $css;
}

function bucc_print() {
	echo bucc();
}

function bucc_style() {
	global $blog_id, $current_blog;

	if ( bucc_is_freetrial() && ( !bucc_is_preview() || !current_user_can( 'switch_themes' ) ) )
		return;
	
	// shortcircuit if not preview, and output the stylesheet tag
	if ( !bucc_is_preview() ) {
		// quickly handle actual css
		if( $href = bucc_get_file(true) ) {
			?><link rel="stylesheet" type="text/css" href="<?php echo esc_attr( $href ); ?>" /><?php
			return true;
		}
		return false;
	}

	$option = bucc_is_preview() ? 'safecss_preview' : 'safecss';

	// Prevent debug notices
	$css = '';

	// Check if extra CSS exists
	if ( 'safecss' == $option ) {
		if ( $safecss_post = bucc_get_post() )
			$css = $safecss_post['post_content'];
	}

	if ( 'safecss_preview' == $option ) {
		$safecss_post = bucc_get_current_revision();
		$css = $safecss_post['post_content'];
	}

	$css = str_replace( array( '\\\00BB \\\0020', '\0BB \020', '0BB 020' ), '\00BB \0020', $css );

	if ( $css == '' )
		return;

	$href = get_option( 'siteurl' );
	$href = add_query_arg( 'custom-css', 1,                                    $href );
	$href = add_query_arg( 'csblog',     $blog_id,                             $href );
	$href = add_query_arg( 'cscache',    5,                                    $href );
	$href = add_query_arg( 'csrev',      (int) get_option( apply_filters('bucc_option', $option . '_rev') ), $href );
?>
	<link rel="stylesheet" type="text/css" href="<?php echo esc_attr( $href ); ?>" />
<?php
}

function bucc_style_filter( $current ) {
	if ( bucc_is_freetrial() && ( !bucc_is_preview() || !current_user_can( 'switch_themes' ) ) )
		return $current;

	if ( bucc_skip_stylesheet() )
		return plugins_url('blank.css', __FILE__);

	return $current;
}
add_filter( 'stylesheet_uri', 'bucc_style_filter' );

function bucc_buffer($html) {
	$html = str_replace( '</body>', bucc_preview_flag(), $html );
	return preg_replace_callback( '!href=([\'"])(.*?)\\1!', 'bucc_preview_links', $html );
}

function bucc_preview_links( $matches ) {
	if ( 0 !== strpos( $matches[2], get_option( 'home' ) ) )
		return $matches[0];

	$link = add_query_arg( 'csspreview', 'true', $matches[2] );
	$link = add_query_arg( 'preview', 'true', $link );
	return "href={$matches[1]}$link{$matches[1]}";
}

// Places a black bar above every preview page
function bucc_preview_flag() {
	if ( is_admin() )
		return;

	$message = '<strong>' . esc_js( __( 'You are previewing custom CSS.') ) . '</strong> ' . esc_js( __( 'Don\'t forget to save any changes, or they will be lost.', 'safecss' ) );
	return "
<script type='text/javascript'>
// <![CDATA[
var flag = document.createElement('div');
flag.innerHTML = '$message';
flag.style.background = '#fefacb';
flag.style.color = 'black';
flag.style.fontSize = '13px';
flag.style.padding = '10px';
document.body.insertBefore(flag, document.body.childNodes[0]);
var ulink = document.getElementById('upgradelink');
if(ulink) {
	ulink.style.textDecoration = 'underline';
	ulink.style.cursor = 'pointer';
	ulink.onclick = function() {document.location.href = '$url';};
}
// ]]>
</script>
";
}

function bucc_menu() {
	global $pagenow;

	$parent = 'themes.php';
	$title  = __( 'Custom CSS', 'safecss' );
	$hook   = add_submenu_page( $parent, $title, $title, 'switch_themes', 'editcss', 'bucc_admin' );
	add_action( "admin_print_scripts-$hook", 'bucc_enqueue_scripts' );
	add_action( "admin_print_styles-$hook", 'bucc_enqueue_styles' );
	
	do_action('bucc_menu', $parent);
}

function bucc_enqueue_scripts() {
	wp_enqueue_script( 'postbox' );
	wp_enqueue_script( 'bucc_admin_script', plugins_url('/interface/js/admin.js', __FILE__) );
}

function bucc_enqueue_styles() {
	wp_enqueue_style( 'bucc_admin_style', plugins_url('/interface/css/admin.css', __FILE__) );
}

function bucc_saved() {
	echo '<div id="message" class="updated fade"><p><strong>' . __('Stylesheet saved.', 'safecss') . '</strong></p></div>';
}

/**
 * Show the admin screen for editing CSS
 * @global int $screen_layout_columns
 */
function bucc_admin() {
	global $screen_layout_columns;
	$screen_layout_columns = 2;
	$last_updated = bucc_process_file_updates();
	include('interface/admin.php');
}

/**
 * Metabox to show options like how the original stylesheet css should be handled
 * @param type $safecss_post
 */
function bucc_original_css_metabox( $safecss_post ) {
	$stylesheet = get_bloginfo( 'stylesheet_directory' ) . '/style.css' . '?minify=false';
	$stylesheet = apply_filters( 'bucc_current_stylesheet', $stylesheet );
	
	$theme = get_current_theme();
	include('interface/original-css-metabox.php');
}

/**
 * Metabox to show publish options
 * @param type $safecss_post
 */
function bucc_submit_metabox( $safecss_post ) {
	include('interface/submit-metabox.php');
}

/**
 * Metabox to show revisions
 * @param post-assoc-array $safecss_post
 */
function bucc_revisions_metabox( $safecss_post ) {
	
	if ( 0 < $safecss_post['ID'] && wp_get_post_revisions( $safecss_post['ID'] ) ) {
		// Specify numberposts and ordering args
		$args = array( 'numberposts' => 5, 'orderby' => 'ID', 'order' => 'DESC' );
		// Remove numberposts from args if show_all_rev is specified
		if ( isset( $_GET['show_all_rev'] ) )
			unset( $args['numberposts'] );

		wp_list_post_revisions( $safecss_post['ID'], $args );
	} else {
		echo "No revisions";
	}
}

function bucc_metabox_original_css() {
	
	// make sure overriding default css is not disabled by theme
	if( !defined('BU_CUSTOM_CSS_DISABLE_OVERRIDE') or (defined('BU_CUSTOM_CSS_DISABLE_OVERRIDE') and !BU_CUSTOM_CSS_DISABLE_OVERRIDE) ) {
		add_meta_box( 'bucc_originalcssdiv', __( 'Original CSS', 'safecss' ), 'bucc_original_css_metabox', 'editcss', 'normal' );
	}
	
	add_meta_box( 'revisionsdiv', __( 'Revisions', 'safecss' ), 'bucc_revisions_metabox', 'editcss', 'normal' );
	add_meta_box( 'submitdiv', __( 'Publish', 'safecss' ), 'bucc_submit_metabox', 'editcss', 'side' );
}
add_action('admin_menu', 'bucc_metabox_original_css');


/**
 * Get the filename for custom css file
 * 
 * @return string
 */
function bucc_filename() {
	$filename = BUCC_FILENAME;
	return apply_filters('bucc_filename', $filename);
}


/**
 * Get the file (path or url) to custom css file
 * 
 * @param boolean $url true to get frontend URL, false (default) to get absolute path
 * @param boolean $projected true if file existence doesn't matter, false (default) if it does
 * @return boolean|string if the custom css file is found, returns it or false
 */
function bucc_get_file($url = false, $projected = false) {
	
	$filename = bucc_filename();
	$filepath = ABSPATH . get_option('upload_path') . '/' . $filename;
	$siteurl = get_option('siteurl');
	if (file_exists($filepath) or $projected) {
		if( $url ) return $siteurl . '/files/' . $filename;
		else return $filepath;
	}
	return false;
}


/**
 * Saves the updated post as static file in uploads folder
 * 
 * @param int $post_id
 * @param object $post
 * @return boolean true if file saved, false otherwise
 */
function bucc_save_to_file($post_id, $post) {
	
	if ( !$post or $post->post_type != BUCC_POST_TYPE ) return;
	if( defined('DOING_AUTOSAVE') and DOING_AUTOSAVE ) return;
	
	// save css to file
	$file = bucc_get_file(false, true);
	$dir = dirname($file);

	if(is_dir($dir) && is_writable($dir)) {
		$temp_file = tempnam('/tmp', BUCC_FILENAME);

		if ($temp_file) {
			$f = @fopen($temp_file, 'w');

			if ($f) {
				fwrite($f, $post->post_content);
				fclose($f);

				@rename($temp_file, $file); // atomic on unix
				@chmod($file, 0664);
			}
		}
		return true;
	} else {
		error_log("Could not update the custom CSS file. Directory ($dir) is not writable.");
	}
	
	return false;
}
add_action('save_post', 'bucc_save_to_file', 10, 2);


/**
 * Pick up any updates in the custom css file (i.e. updates happening outside wordpress, like through ftp/ssh)
 * 
 * @return last-mod-time|false non-false response indicates that newer content was retrieved from custom css file
 */
function bucc_process_file_updates() {
	
	if ( $filepath = bucc_get_file() ) {
		$newcss = file_get_contents($filepath);
		if( $safecss_post = bucc_get_post() ) {
			$mod_time = get_post_modified_time(get_option('date_format') . ' ' . get_option('time_format'), null, $safecss_post['ID']);
			if( $safecss_post and $safecss_post['post_content'] != $newcss ) {
				bucc_save_revision($newcss);
				return $mod_time;
			}
		} else {
			// this is the first import from custom.css, do it silently
			bucc_save_revision($newcss);
		}
	}
	return false;
}