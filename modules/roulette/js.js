$(function () {
	$('#a-roulette').click(function () {
		var ix = parseInt(Math.random()*$('.movie-item').length);
		$('.movie-item:eq('+ix+')').trigger('click');
		return false;
	});

	$('#a-roulette-tv').click(function () {
		var ix = parseInt(Math.random()*$('.episode-link').length);
		window.location.href = $('.episode-link:eq('+ix+')').attr('href');
		return false;
	});
});