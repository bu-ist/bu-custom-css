<div class="wrap">
	<h2><?php bucc_is_mobile() ? _e( 'Mobile CSS Stylesheet Editor', 'safecss' ) : _e( 'CSS Stylesheet Editor', 'safecss' ); ?></h2>

	<form id="safecssform" action="" method="post">
	<div id="poststuff" class="metabox-holder <?php echo 2 == $screen_layout_columns ? ' has-right-sidebar' : ''; ?>">

		<div id="post-body">
			<div id="post-body-content">

				<?php if( function_exists('safecss') ): ?>
				<div class="error">
					<p>
						<strong>You have the Wordpress.com Custom CSS plugin activated.</strong>
						Since this plugin is a fork with some improvements. We recommend that you deactivate that plugin first before continuing.
					</p>
				</div>
				<?php endif; ?>

				<?php if( $last_updated ): ?>
				<div class="error">
					<p>
						<strong>Your Custom CSS file has been modified outside of Wordpress!</strong>
						We have refreshed the editor below to show the new CSS. The previous version, updated on <?php echo $last_updated; ?>, has been saved as a revision.
					</p>
				</div>
				<?php endif; ?>

				<?php if( defined('BU_CMS') and BU_CMS ): ?>
				<div class="error">
					<p>
						<strong>Important:</strong>
						Adding custom CSS to your theme can have a negative effect on the appearance of your pages.
						The IS&amp;T Help Desk does not support custom styles created using this tool.
						By using this tool, you agree to take full responsibility for the appearance of your website,
						and that your website will remain in compliance with the <a href="http://www.bu.edu/brand/websites/" target="_blank">University branding guidelines</a>.
					</p>
				</div>
				<?php endif; ?>

				<div class="postbox">
					<h3><span>Custom CSS</span></h3>
					<textarea id="safecss" name="safecss"><?php echo str_replace('</textarea>', '&lt;/textarea&gt', bucc()); ?></textarea>
				</div>

				<?php
				$safecss_post = bucc_get_post();
				do_meta_boxes( 'editcss', 'normal', $safecss_post );
				?>
			</div>

		</div>

		<div id="side-info-column" class="inner-sidebar">
			<?php do_meta_boxes( 'editcss', 'side', $safecss_post ); ?>
		</div>

	</div>	
	</form>

</div>