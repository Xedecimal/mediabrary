$(function () {
	$('a[href^="RottenTomatoes/fix/rt_meta"]').click(function () {
		moveieDetail($(this).attr('href').match(/\/([^/]+)$/)[0]);
		return false;
	});
});