$(function () {
	$('#a-roulette').live('click', function () {
		var ix = parseInt(Math.random()*$('.a-movie-item').length);
		$('.a-movie-item:eq('+ix+')').trigger('click');
		return false;
	});

	$('#a-roulette-tv').click(function () {
		var ix = parseInt(Math.random()*$('.episode-link').length);
		window.location.href = $('.episode-link:eq('+ix+')').attr('href');
		return false;
	});
});