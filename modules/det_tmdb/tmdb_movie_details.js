$(function () {
	$('#tmdb-trailer-toggle').on('click', function (e) {
		$('#tmdb-trailer-video').toggle('slow');
		e.preventDefault();
	});
	
	$(document).on('click', '#a-back-next', function () {
		$.get('TMDB/backdrops/'+$('#detail-id').val(), function (data) {
			//$('#detail-dialog').css('background', 'url('+data[0]+') 100% 100%');
		}, 'json');
	});
});