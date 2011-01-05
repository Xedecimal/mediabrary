$(function () {
	$('.aCategory').live('click', function () {
		$.get($(this).attr('href'), function () {
			window.refreshAll();
		});
		return false;
	});
});
