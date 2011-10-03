$(function () {
	$('#dialog-music');

	$('.a-music-item').live('click', function () {
		$('<div />').dialog({
			width: '80%',
			height: 500,
			position: 'top',
			title: 'Artist Details'})
			.load($(this).attr('href'));

		return false;
	});

	$('#music-items').load('items/'+window.location.href.match(/([^\/]+)$/)[1]);
});
