$(function () {
	$('.a-fix').click(function () {
		$(this).replaceWith($(this).attr('href'));
		return false;
	});
});