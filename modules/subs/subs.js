$(function () {
	$('#a-get-subs').live('click', function () {
		$.get('subs/get', {
			path: $(this).attr('href'),
			title: $('#movie_title').val() },
			function () { });
		return false;
	});
});
