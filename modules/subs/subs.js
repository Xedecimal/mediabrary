$(function () {
	$('#a-get-subs').live('click', function () {
		$.get('subs/get', {
			path: $(this).attr('href'),
			title: $('#detail-title').val(),
			date: $('#detail-date').val()
		}, function (data) {
			$('#details').html(data);
		}, 'html');

		return false;
	});
});
