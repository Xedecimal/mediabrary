$(function () {
	$('.aCategory').click(function () {
		$.get($(this).attr('href'), function () {
			$('#movie-items').load('movie/items');
		});
		return false;
	});
});