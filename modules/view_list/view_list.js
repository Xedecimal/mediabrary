$(function () {
	$('.a-title').click(function () {
		$('<div />').dialog({
			width: '80%',
			height: 500,
			position: 'top',
			title: 'Movie Details'
		}).load($(this).attr('href'));

		return false;
	});
});
