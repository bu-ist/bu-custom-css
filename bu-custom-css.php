<?php
/*
Plugin Name: BU Custom CSS Editor
Description: Allows CSS editing with an option to override the original theme CSS.
Author: Boston University (IS&T)
Author URI: http://www.bu.edu/tech/
Version: 1.5-BU1
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

/**
 * Add local textdomain
 */
add_action( 'init', 'bucc_load_plugin_textdomain' );

function bucc_load_plugin_textdomain() {
	load_plugin_textdomain( 'safecss', false, 'bu-custom-css/languages' );
}


/**
 * Migration routine for moving safecss from wp_options to wp_posts to support revisions
 *
 * @return void
 */
function bucc_migrate() {
	$css = get_option( 'safecss' );

	// Check if CSS is stored in wp_options
	if ( $css ) {
		// Remove the async actions from publish_post
		remove_action( 'publish_post', 'queue_publish_post' );

		$post = array();
		$post['post_content'] = $css;
		$post['post_title']   = 'safecss';
		$post['post_status']  = 'publish';
		$post['post_type']    = 'safecss';

		// Insert the CSS into wp_posts
		$post_id = wp_insert_post( $post );

		// Check for errors
		if ( !$post_id or is_wp_error( $post_id ) )
			die( $post_id->get_error_message() );

		// Delete safecss option
		delete_option( 'safecss' );
	}

	unset( $css );

	// Check if we have already done this
	if ( !get_option( 'safecss_revision_migrated' ) ) {
		define( 'DOING_MIGRATE', true );

		// Get hashes of safecss post and current revision
		$safecss_post = bucc_get_post();

		if ( null == $safecss_post )
			return;

		$safecss_post_hash = md5( $safecss_post['post_content'] );
		$current_revision = bucc_get_current_revision();

		if ( null == $current_revision )
			return;

		$current_revision_hash = md5( $current_revision['post_content'] );

		// If hashes are not equal, set safecss post with content from current revision
		if ( $safecss_post_hash !== $current_revision_hash ) {
			bucc_save_revision( $current_revision['post_content'] );

			// Reset post_content to display the migrated revsion
			$safecss_post['post_content'] = $current_revision['post_content'];
		}

		// Set option so that we dont keep doing this
		update_option( 'safecss_revision_migrated', time() );
	}
}

function bucc_revision_redirect( $redirect ) {
	global $post;

	if ( 'safecss' == $post->post_type ) {
		if ( strstr( $redirect, 'action=edit' ) )
			return 'themes.php?page=editcss';

		if ( 'edit.php' == $redirect )
			return '';

	}

	return $redirect;
}

// Add safecss to allowed post_type's for revision
add_filter('revision_redirect', 'bucc_revision_redirect');

function bucc_revision_post_link( $post_link ) {
	global $post;

	if ( isset( $post ) && ( 'safecss' == $post->post_type ) )
		if ( strstr( $post_link, 'action=edit' ) )
			$post_link = 'themes.php?page=editcss';

	return $post_link;
}

// Override the edit link, the default link causes a redirect loop
add_filter('get_edit_post_link', 'bucc_revision_post_link');

/**
 * Get the safecss record
 *
 * @return array
 */
function bucc_get_post() {

	if ( $a = array_shift( get_posts( array( 'numberposts' => 1, 'post_type' => 'safecss', 'post_status' => 'publish' ) ) ) )
		$safecss_post = get_object_vars( $a );
	else
		$safecss_post = false;

	return $safecss_post;
}

/**
 * Get the current revision of the original safecss record
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
	
	// If null, there was no original safecss record, so create one
	if ( !$safecss_post = bucc_get_post() ) {
		$post = array();
		$post['post_content'] = $css;
		$post['post_title']   = 'safecss';
		$post['post_status']  = 'publish';
		$post['post_type']    = 'safecss';

		// Insert the CSS into wp_posts
		$post_id = wp_insert_post( $post );

		do_action('post_bucc_save_revision', $safecss_post, $is_preview);
		return true;
	}

	$safecss_post['post_content'] = $css;

	// Do not update post if we are only saving a preview
	if ( false === $is_preview )
		wp_update_post( $safecss_post );

	else if ( !defined( 'DOING_MIGRATE' ) )
		_wp_put_post_revision( $safecss_post );
	
	do_action('post_bucc_save_revision', $safecss_post, $is_preview);
}

function bucc_skip_stylesheet() {

	if ( bucc_is_preview() )
		return (bool) ( get_option('safecss_preview_add') == 'no' );
	else
		return (bool) ( get_option('safecss_add') == 'no' );
}

function bucc_init() {
	// Register safecss as a custom post_type
	register_post_type( 'safecss', array(
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

	// Do migration routine if necessary
	if ( !empty( $_GET['page'] ) && ( 'editcss' == $_GET['page'] ) && is_admin() )
		bucc_migrate();

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
			update_option( 'safecss_preview_rev', intval( get_option( 'safecss_preview_rev' ) ) + 1 );
			update_option( 'safecss_preview_add', $add_to_existing );
			wp_redirect( add_query_arg( 'csspreview', 'true', get_option( 'home' ) ) );

			exit;
		}

		// Save the CSS
		bucc_save_revision( $css );
		update_option( 'safecss_rev', intval( get_option( 'safecss_rev' ) ) + 1 );
		update_option( 'safecss_add', $add_to_existing );

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
		if ( get_option( 'safecss_revision_migrated' ) )
			if ( $safecss_post = bucc_get_post() )
				$css = $safecss_post['post_content'];
		else
			if ( $current_revision = bucc_get_current_revision() )
				$css = $current_revision['post_content'];

		// Fix for un-migrated Custom CSS
		if ( empty( $safecss_post ) ) {
			$_css = get_option( 'safecss' );
			if ( !empty( $_css ) ) {
				$css = $_css;
			}
		}
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
		if ( get_option( 'safecss_revision_migrated' ) )
			if ( $safecss_post = bucc_get_post() )
				$css = $safecss_post['post_content'];
		else
			if ( $current_revision = bucc_get_current_revision() )
				$css = $current_revision['post_content'];

		// Fix for un-migrated Custom CSS
		if ( empty( $safecss_post ) ) {
			$_css = get_option( 'safecss' );
			if ( !empty( $_css ) ) {
				$css = $_css;
			}
		}
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
	$href = add_query_arg( 'csrev',      (int) get_option( $option . '_rev' ), $href );
?>
	<link rel="stylesheet" type="text/css" href="<?php echo esc_attr( $href ); ?>" />
<?php
}

function bucc_style_filter( $current ) {
	if ( bucc_is_freetrial() && ( !bucc_is_preview() || !current_user_can( 'switch_themes' ) ) )
		return $current;

	if ( bucc_skip_stylesheet() )
		return 'http://' . $_SERVER['HTTP_HOST'] . '/wp-content/plugins/safecss/blank.css';

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
	include('interface/admin.php');
}

/**
 * Metabox to show options like how the original stylesheet css should be handled
 * @param type $safecss_post
 */
function bucc_original_css_metabox( $safecss_post ) {
	$stylesheet = get_bloginfo( 'stylesheet_directory' ) . '/style.css' . '?minify=false';
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
	}
}

function bucc_metabox_original_css() {
	add_meta_box( 'bucc_originalcssdiv', __( 'Original CSS', 'safecss' ), 'bucc_original_css_metabox', 'editcss', 'normal' );
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
 * Saves the updated safecss a static file in uploads folder
 * 
 * @param int $post_id
 * @param object $post
 * @return boolean true if file saved, false otherwise
 */
function bucc_save_to_file($post_id, $post) {
	
	if ( !$post or $post->post_type != 'safecss' ) return;
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
		$safecss_post = bucc_get_post();
		$mod_time = get_post_modified_time(get_option('date_format') . ' ' . get_option('time_format'), null, $safecss_post['ID']);
		$newcss = file_get_contents($filepath);
		if( $safecss_post and $safecss_post['post_content'] != $newcss ) {
			bucc_save_revision($newcss);
			return $mod_time;
		}
	}
	return false;
}