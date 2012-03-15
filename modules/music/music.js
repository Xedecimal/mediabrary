$(function () {
	$('#dialog-music');

	$('.a-music-item').live('click', function () {
		$('<div id="detail-dialog" />').dialog({
			width: '80%',
			height: 500,
			position: 'top',
			title: 'Artist Details',

			close: function () { $('#detail-dialog').remove(); }
		}).load($(this).attr('href'));

		return false;
	});

	$('#music-items').load('items/'+window.location.href.match(/([^\/]+)$/)[1]);
});
