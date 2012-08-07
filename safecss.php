<?php
/*
Plugin Name: WordPress.com Custom CSS
Plugin URI: http://automattic.com/
Description: Allows CSS editing with strict filtering for malicious code
Author: Automattic
Version: 1.5-BU1
Author URI: http://automattic.com/
*/

define('BU_SAFECSS_FILENAME', 'custom.css');

/**
 * Add local textdomain
 */
add_action( 'init', 'safecss_load_plugin_textdomain' );

function safecss_load_plugin_textdomain() {
	load_plugin_textdomain( 'safecss', false, 'safecss/languages' );
}


/**
 * Migration routine for moving safecss from wp_options to wp_posts to support revisions
 *
 * @return void
 */
function migrate() {
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
		$safecss_post = get_safecss_post();

		if ( null == $safecss_post )
			return;

		$safecss_post_hash = md5( $safecss_post['post_content'] );
		$current_revision = get_current_revision();

		if ( null == $current_revision )
			return;

		$current_revision_hash = md5( $current_revision['post_content'] );

		// If hashes are not equal, set safecss post with content from current revision
		if ( $safecss_post_hash !== $current_revision_hash ) {
			save_revision( $current_revision['post_content'] );

			// Reset post_content to display the migrated revsion
			$safecss_post['post_content'] = $current_revision['post_content'];
		}

		// Set option so that we dont keep doing this
		update_option( 'safecss_revision_migrated', time() );
	}
}

function safecss_revision_redirect( $redirect ) {
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
add_filter('revision_redirect', 'safecss_revision_redirect');

function safecss_revision_post_link( $post_link ) {
	global $post;

	if ( isset( $post ) && ( 'safecss' == $post->post_type ) )
		if ( strstr( $post_link, 'action=edit' ) )
			$post_link = 'themes.php?page=editcss';

	return $post_link;
}

// Override the edit link, the default link causes a redirect loop
add_filter('get_edit_post_link', 'safecss_revision_post_link');

/**
 * Get the safecss record
 *
 * @return array
 */
function get_safecss_post() {

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
function get_current_revision() {

	if ( !$safecss_post = get_safecss_post() )
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
function save_revision( $css, $is_preview = false ) {

	$css = apply_filters('pre_safecss_save_revision', $css);
	
	// If null, there was no original safecss record, so create one
	if ( !$safecss_post = get_safecss_post() ) {
		$post = array();
		$post['post_content'] = $css;
		$post['post_title']   = 'safecss';
		$post['post_status']  = 'publish';
		$post['post_type']    = 'safecss';

		// Insert the CSS into wp_posts
		$post_id = wp_insert_post( $post );

		do_action('post_safecss_save_revision', $safecss_post, $is_preview);
		return true;
	}

	$safecss_post['post_content'] = $css;

	// Do not update post if we are only saving a preview
	if ( false === $is_preview )
		wp_update_post( $safecss_post );

	else if ( !defined( 'DOING_MIGRATE' ) )
		_wp_put_post_revision( $safecss_post );
	
	do_action('post_safecss_save_revision', $safecss_post, $is_preview);
}

function safecss_skip_stylesheet() {

	if ( safecss_is_preview() )
		return (bool) ( get_option('safecss_preview_add') == 'no' );
	else
		return (bool) ( get_option('safecss_add') == 'no' );
}

function safecss_init() {
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

		safecss_print();

		exit;
	}

	// Do migration routine if necessary
	if ( !empty( $_GET['page'] ) && ( 'editcss' == $_GET['page'] ) && is_admin() )
		migrate();

	add_action('wp_head', 'safecss_style', 101);

	if ( !current_user_can( 'switch_themes' ) && !is_super_admin() )
		return;

	add_action('admin_menu', 'safecss_menu');

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

		if ( $_POST['add_to_existing'] == 'true' )
			$add_to_existing = 'yes';
		else
			$add_to_existing = 'no';

		if ( 'preview' == $_POST['action'] || safecss_is_freetrial() ) {
			$is_preview = true;

			// Save the CSS
			save_revision( $css, $is_preview );

			// Cache Buster
			update_option( 'safecss_preview_rev', intval( get_option( 'safecss_preview_rev' ) ) + 1 );
			update_option( 'safecss_preview_add', $add_to_existing );
			update_option( 'safecss_preview_content_width', $custom_content_width );
			wp_redirect( add_query_arg( 'csspreview', 'true', get_option( 'home' ) ) );

			exit;
		}

		// Save the CSS
		save_revision( $css );
		update_option( 'safecss_rev', intval( get_option( 'safecss_rev' ) ) + 1 );
		update_option( 'safecss_add', $add_to_existing );
		update_option( 'safecss_content_width', $custom_content_width );

		add_action( 'admin_notices', 'safecss_saved' );
	}

	// Modify all internal links so that preview state persists
	if ( safecss_is_preview() )
		ob_start('safecss_buffer');
}
add_action('init', 'safecss_init');

function safecss_is_preview() {
	return isset( $_GET['csspreview'] ) && $_GET['csspreview'] === 'true';
}

function safecss_is_freetrial() {
	return false;
}

function safecss() {
	if ( safecss_is_freetrial() && ( !current_user_can( 'switch_themes' ) || !safecss_is_preview() ) && !is_admin() )
		return '/* */';

	$option = ( safecss_is_preview() || safecss_is_freetrial() ) ? 'safecss_preview' : 'safecss';
	$css    = '';

	if ( 'safecss' == $option ) {
		if ( get_option( 'safecss_revision_migrated' ) )
			if ( $safecss_post = get_safecss_post() )
				$css = $safecss_post['post_content'];
		else
			if ( $current_revision = get_current_revision() )
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
		$safecss_post = get_current_revision();
		$css = $safecss_post['post_content'];
	}

	$css = str_replace( array( '\\\00BB \\\0020', '\0BB \020', '0BB 020' ), '\00BB \0020', $css );

	if ( empty($css) and $safecss_file = bu_safecss_get_file() ) {
		$css = file_get_contents($safecss_file);
	}
	
	if ( empty( $css ) ) {
		$css = _e( '/* Welcome to Custom CSS!

If you are familiar with CSS or you have a stylesheet ready to paste, you may delete these comments and get started.

CSS (Cascading Style Sheets) is a kind of code that tells the browser how to render a web page. Here\'s an example:

img { border: 1px solid red; }

That line basically means "give images a red border one pixel thick."

CSS is not very hard to learn. If you already know a little HTML it will be fun to move things around on the web page by changing your stylesheet. There are many free references to help you get started. You can find helpful links, starter stylesheets and knowledgable people in the forum:
http://wordpress.com/forums/forum.php?id=3

We hope you enjoy developing your custom CSS. Here are a few things to keep in mind:

You can not edit the stylesheets of your theme. Your stylesheet will be loaded after the theme stylesheets, which means that your rules can take precedence and override the theme CSS rules. The Sandbox theme is recommended for those who would prefer to start from scratch.

CSS comments will be stripped from your stylesheet. */

/* This is a comment. Comments begin with /* and end with */

/*
Things we strip out include:
 * HTML code
 * @import rules
 * expressions
 * invalid and unsafe code
 * URLs not using the http: protocol

Things we encourage include:
 * @media blocks!
 * sharing your CSS!
 * testing in several browsers!
 * helping others in the forum!
', 'safecss' );
$css .= "\n*/";
	}

	return $css;
}

function safecss_print() {
	echo safecss();
}

function safecss_style() {
	global $blog_id, $current_blog;

	if ( safecss_is_freetrial() && ( !safecss_is_preview() || !current_user_can( 'switch_themes' ) ) )
		return;
	
	// shortcircuit if not preview, and output the stylesheet tag
	if ( !safecss_is_preview() ) {
		// quickly handle actual css
		if( $href = bu_safecss_get_file(true) ) {
			?><link rel="stylesheet" type="text/css" href="<?php echo esc_attr( $href ); ?>" /><?php
			return true;
		}
		return false;
	}

	$option = safecss_is_preview() ? 'safecss_preview' : 'safecss';

	// Prevent debug notices
	$css = '';

	// Check if extra CSS exists
	if ( 'safecss' == $option ) {
		if ( get_option( 'safecss_revision_migrated' ) )
			if ( $safecss_post = get_safecss_post() )
				$css = $safecss_post['post_content'];
		else
			if ( $current_revision = get_current_revision() )
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
		$safecss_post = get_current_revision();
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

function safecss_style_filter( $current ) {
	if ( safecss_is_freetrial() && ( !safecss_is_preview() || !current_user_can( 'switch_themes' ) ) )
		return $current;

	if ( safecss_skip_stylesheet() )
		return 'http://' . $_SERVER['HTTP_HOST'] . '/wp-content/plugins/safecss/blank.css';

	return $current;
}
add_filter( 'stylesheet_uri', 'safecss_style_filter' );

function safecss_buffer($html) {
	$html = str_replace( '</body>', safecss_preview_flag(), $html );
	return preg_replace_callback( '!href=([\'"])(.*?)\\1!', 'safecss_preview_links', $html );
}

function safecss_preview_links( $matches ) {
	if ( 0 !== strpos( $matches[2], get_option( 'home' ) ) )
		return $matches[0];

	$link = add_query_arg( 'csspreview', 'true', $matches[2] );
	return "href={$matches[1]}$link{$matches[1]}";
}

// Places a black bar above every preview page
function safecss_preview_flag() {
	if ( is_admin() )
		return;

	$message = wp_specialchars( __( 'Preview: changes must be saved or they will be lost', 'safecss' ), 'single' );
	return "
<script type='text/javascript'>
// <![CDATA[
var flag = document.createElement('div');
flag.innerHTML = '$message';
flag.style.background = 'black';
flag.style.color = 'white';
flag.style.textAlign = 'center';
flag.style.fontSize = '15px';
flag.style.padding = '1px';
document.body.style.paddingTop = '32px';
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

function safecss_menu() {
	global $pagenow;

	$parent = 'themes.php';
	$title  = __( 'Edit CSS', 'safecss' );
	$hook   = add_submenu_page( $parent, $title, $title, 'switch_themes', 'editcss', 'safecss_admin' );
	add_action( "admin_print_scripts-$hook", 'safe_css_enqueue_scripts' );
	add_action( "admin_head-$hook", 'safecss_admin_head' );
}

function safe_css_enqueue_scripts() {
	wp_enqueue_script( 'postbox' );
}

function safecss_admin_head() {
?>

<style type="text/css">
.wrap form.safecss {
	margin-right: 10px;
}
.wrap textarea#safecss {
	width: 100%;
	height: 100%;
}
</style>
<script type="text/javascript">
/*<![CDATA[*/
safecssResize = function() {
	var hh, bh, h, o = document.getElementById('safecss');
	hh = document.body.parentNode.clientHeight;
	bh = document.body.clientHeight;
	h = o.offsetHeight + hh - bh + 100;
	o.style.height = (h > 250 ? h : 250) + 'px';
}
safecssInit = function() {
	postboxes.add_postbox_toggles('editcss');
	safecssResize();
	var button = document.getElementById('preview');
	button.onclick = function(event) {
		//window.open('<?php echo add_query_arg('csspreview', 'true', get_option('home')); ?>');

		document.forms["safecssform"].target = "csspreview";
		document.forms["safecssform"].action.value = 'preview';
		document.forms["safecssform"].submit();
		document.forms["safecssform"].target = "";
		document.forms["safecssform"].action.value = 'save';

		event = event || window.event;
		if ( event.preventDefault ) event.preventDefault();
		return false;
	}
}
window.onresize = safecssResize;
addLoadEvent(safecssInit);
/*]]>*/
</script>

<?php
}

function safecss_saved() {
	echo '<div id="message" class="updated fade"><p><strong>' . __('Stylesheet saved.', 'safecss') . '</strong></p></div>';
}

function safecss_admin() {
	global $screen_layout_columns;

	$custom_content_width = intval( get_option( 'safecss_content_width' ) );

	// if custom content width hasn't been overridden and the theme has a content_width value, use that as a default
	if ( $custom_content_width <= 0 && !empty( $GLOBALS['content_width'] ) )
		$custom_content_width = intval( $GLOBALS['content_width'] );
?>
<div id="poststuff" class="metabox-holder<?php echo 2 == $screen_layout_columns ? ' has-right-sidebar' : ''; ?>">
<div class="wrap">
<h2><?php _e( 'CSS Stylesheet Editor', 'safecss' ); ?></h2>


<?php if( bu_safecss_process_file_updates() ): ?>
<div class="error">
	<p>
		<strong>CSS File Updated Directly</strong>
		<?php echo bu_safecss_filename(); ?> file was updated directly. We have refreshed the editor below with the new content.
	</p>
</div>
<?php endif; ?>

<form id="safecssform" action="" method="post">
	<p><textarea id="safecss" name="safecss"><?php echo str_replace('</textarea>', '&lt;/textarea&gt', safecss()); ?></textarea></p>
	<p class="custom-css-help"><?php _e('For help with CSS try <a href="http://www.w3schools.com/css/default.asp">W3Schools</a>, <a href="http://alistapart.com/">A List Apart</a>, and our own <a href="http://support.wordpress.com/editing-css/">CSS documentation</a> and <a href="http://en.forums.wordpress.com/forum/css-customization">CSS Forum</a>.', 'safecss'); ?></p>
	<h4><?php _e("Do you want to make changes to your current theme's stylesheet, or do you want to start from scratch?", 'safecss'); ?></h4>
	<p><label><input type="radio" name="add_to_existing" value="true" <?php if ( get_option( 'safecss_add') != 'no' ) echo ' checked="checked"'; ?> /> <?php printf( __( 'Add this to the %s theme\'s CSS stylesheet (<a href="%s">view original stylesheet</a>)', 'safecss' ), get_current_theme(), get_bloginfo( 'stylesheet_directory' ) . '/style.css' . '?minify=false' ); ?></label><br />
	<label><input type="radio" name="add_to_existing" value="false" <?php if ( get_option( 'safecss_add') == 'no' ) echo ' checked="checked"'; ?> /> <?php _e( 'Start from scratch and just use this ', 'safecss' ); ?></label>
	</p>

	<h4> <?php _e( "If you change the width of your main content column, make sure your media files fit. Enter the maximum width for media files in your new CSS below.", 'safecss' ); ?></h4>
	<p class="custom_content_width"><label for="custom_content_width"><?php _e( 'Limit width to', 'safecss' ); ?></label>
	<input type="text" name="custom_content_width" id="custom_content_width" value="<?php echo $custom_content_width; ?>" size=5 /> <?php printf( __( 'pixels for videos, full size images, and other shortcodes. (<a href="%s">more info</a>)', 'safecss' ), 'http://support.wordpress.com/editing-css/#limited-width'); ?></p>
	<p class="submit">
		<input type="hidden" name="action" value="save" />

		<?php wp_nonce_field('safecss') ?>

		<input type="button" class="button" id="preview" name="preview" value="<?php _e('Preview', 'safecss') ?>" />
<?php if ( !safecss_is_freetrial() ) : ?>
		<input type="submit" class="button" id="save" name="save" value="<?php _e('Save Stylesheet &raquo;', 'safecss') ?>" />
<?php endif; ?>
		<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
	</p>
</form>

<?php
$safecss_post = get_safecss_post();

if ( 0 < $safecss_post['ID'] && wp_get_post_revisions( $safecss_post['ID'] ) ) {
	function post_revisions_meta_box( $safecss_post ) {
		// Specify numberposts and ordering args
		$args = array( 'numberposts' => 5, 'orderby' => 'ID', 'order' => 'DESC' );
		// Remove numberposts from args if show_all_rev is specified
		if ( isset( $_GET['show_all_rev'] ) )
			unset( $args['numberposts'] );

		wp_list_post_revisions( $safecss_post['ID'], $args );
	}

	add_meta_box( 'revisionsdiv', __( 'CSS Revisions', 'safecss' ), 'post_revisions_meta_box', 'editcss', 'normal' );
	do_meta_boxes( 'editcss', 'normal', $safecss_post );
}
?>
</div>
</div>
<?php
}


/**
 * Get the filename for custom css file
 * 
 * @return string
 */
function bu_safecss_filename() {
	$filename = BU_SAFECSS_FILENAME;
	return apply_filters('bu_safecss_filename', $filename);
}


/**
 * Get the file (path or url) to custom css file
 * 
 * @param boolean $url true to get frontend URL, false (default) to get absolute path
 * @param boolean $projected true if file existence doesn't matter, false (default) if it does
 * @return boolean|string if the custom css file is found, returns it or false
 */
function bu_safecss_get_file($url = false, $projected = false) {
	
	$filename = bu_safecss_filename();
	$filepath = ABSPATH . get_option('upload_path') . '/' . $filename;
	$siteurl = get_option('siteurl');
	if (file_exists($filepath) or $projected) {
		if( $url ) return $siteurl . '/files/' . $filename;
		else return $filepath;
	}
	return false;
}


/**
 * Saves the revision to a static file in uploads folder
 * 
 * @param post $p
 * @param boolean $is_preview
 * @return boolean true if file saved, false otherwise
 */
function bu_safecss_save_revision_to_file($p, $is_preview) {
	if ( false !== $is_preview ) return false;
	
	if ( $safecss_post = get_safecss_post() ) {
		// save css to file
		$file = bu_safecss_get_file(false, true);
		$dir = dirname($file);

		if(is_dir($dir) && is_writable($dir)) {
			$temp_file = tempnam('/tmp', BU_SAFECSS_FILENAME);

			if ($temp_file) {
				$f = @fopen($temp_file, 'w');

				if ($f) {
					fwrite($f, $safecss_post['post_content']);
					fclose($f);

					@rename($temp_file, $file); // atomic on unix
					@chmod($file, 0664);
				}
			}
			return true;
		} else {
			error_log("Could not update the custom CSS file. Directory ($dir) is not writable.");
		}
	}
	return false;
}
add_action('post_safecss_save_revision', 'bu_safecss_save_revision_to_file', 10, 2);


/**
 * Pick up any updates in the custom css file (i.e. updates happening outside wordpress, like through ftp/ssh)
 * 
 * @return boolean true|false indicating if newer content was retrieved from custom css file
 */
function bu_safecss_process_file_updates() {
	
	if ( $filepath = bu_safecss_get_file() ) {
		$safecss_post = get_safecss_post();
		$newcss = file_get_contents($filepath);
		if( $safecss_post and $safecss_post['post_content'] != $newcss ) {
			save_revision($newcss);
			return true;
		}
	}
	return false;
}