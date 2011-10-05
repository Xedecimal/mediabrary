$(function () {
	$('.a-title').click(function () {
		$('<div />').dialog({
			width: '80%',
			height: 500,
			position: 'top',
			title: 'Item Details'
		}).load($(this).attr('href'));

		return false;
	});
});
