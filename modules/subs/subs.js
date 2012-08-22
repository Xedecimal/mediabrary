$(function () {
	$('#a-get-subs').live('click', function () {
		$.get('subs/get', {
			path: $(this).attr('href'),
			title: $('#detail-title').val(),
			date: $('#detail-date').val()
		},
		function (data) {
			if (data.result == 'success') alert('Done deal!');
		}, 'json');
		return false;
	});
});
