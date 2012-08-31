<div class="submitbox" id="submitpost">

	<div id="minor-publishing">
		<?php if($safecss_url = bu_safecss_get_file(true)): ?>
		To check for errors, you can <a href="http://jigsaw.w3.org/css-validator/validator?uri=<?php echo bu_safecss_get_file(true); ?>" target="_blank">validate your CSS</a> using W3C's free CSS Validation Service.
		<?php endif; ?>
	</div>
	
	<input type="hidden" name="action" value="save" />
	<?php wp_nonce_field('safecss') ?>

	<div id="major-publishing-actions">
		<input type="button" class="button" id="preview" name="preview" value="<?php _e('Preview', 'safecss') ?>" />
		<div id="publishing-action">
			<input type="submit" class="button-primary" id="publish" name="save" value="<?php _e('Publish', 'safecss') ?>" />
		</div>
		<div class="clear"></div>
	</div>
	<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
</div>