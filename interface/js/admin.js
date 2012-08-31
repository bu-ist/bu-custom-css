
safecssInit = function() {
	postboxes.add_postbox_toggles('editcss');
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
addLoadEvent(safecssInit);