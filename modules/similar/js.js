$(function () {
	$('#a-similar').click(function () {
		$('#movie-details').load('similar', {'s': $(this).attr('href')});
		return false;
	});
});