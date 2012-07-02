$(function () {
	$('#tmdb-trailer-toggle').on('click', function () {
		$('#tmdb-trailer-video').toggle('slow');
		return false;
	});

	$('#cover-next').on('click', function () {
		$.get('TMDB/newcover', {
				path: $('#detail-path').val(),
				current: $('#tmdb-cover').attr('src')
			},
			function (data) {
				console.log("Cover to: "+data.cover);
				$('#tmdb-cover-image').attr('src', data.cover);
			},
		'json');

		return false;
	});
});