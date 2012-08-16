$(function () {
	$('#tmdb-trailer-toggle').on('click', function (e) {
		$('#tmdb-trailer-video').toggle('slow');
		e.preventDefault();
	});
});