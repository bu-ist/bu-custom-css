<p><?php printf(__("By default, your custom CSS will be included in addition to your theme's <a href=\"%s\">original CSS</a>. You also have the option of disabling the theme's original CSS by checking the box below."), $stylesheet) ?></p>

<p>
	<label>
		<input type="checkbox" name="add_to_existing" value="false" <?php checked(get_option(apply_filters('bucc_option', 'safecss_add')), 'no', true ); ?> />
		<?php _e( "Disable original CSS", 'safecss' ); ?><br />
		<span class="description"><?php _e( "Do not load my theme's stylesheet, and load this custom CSS after.", 'safecss' ); ?></span>
	</label>
</p>