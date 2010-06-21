$(function () {
	$('#a-roulette').click(function () {
		var ix = parseInt(Math.random()*$('.movie-item').length);
		$('.movie-item:eq('+ix+')').trigger('click');
		return false;
	});
});