$(function () {
	$('.a-fix').click(function () {
		$(this).load($(this).attr('href'));
		return false;
	});
});