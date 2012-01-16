$(function () {
	$('a[href^="'+app_abs+'/RottenTomatoes/fix/rt_meta"]').click(function () {
		movieDetail($(this).attr('href').match(/\/([^/]+)$/)[1]);
		return false;
	});
});