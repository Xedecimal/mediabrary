$(function () {
	$('a[href^="'+app_abs+'/TMDB/fix/tmdb_meta"]').click(function () {
		movieDetail($(this).attr('href').match(/\/([^/]+)$/)[1]);
		return false;
	});
});