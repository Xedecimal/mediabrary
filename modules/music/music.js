$(function () {
	$('#dialog-music').dialog({
		autoOpen: false,
		width: '80%',
		height: 500,
		position: 'top',

		beforeclose: function () {
			$('#music-items a').css('opacity', 1);
		}
	});

	$('.a-music-item').live('click', function () {
		$('#dialog-music').dialog('option', 'title', 'Artist Details');

		$('#dialog-music').load($(this).attr('href'),
			function () { $('#dialog-music').dialog('open'); }
		);

		return false;
	});
});
