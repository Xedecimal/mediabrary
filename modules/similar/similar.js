$(function () {
	$(document).on('click', '#a-similar', function () {
		if ($('#movie-details').length > 0)
			$('#movie-details').load('similar', {'s': $(this).attr('href')});
		else
			$('#tv-details').load('similar', {'s': $(this).attr('href')});

		return false;
	});
});