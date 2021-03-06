$(function () {
	$('#chk-seen').live('click', function () {
		$.get('rate/'+$('#movie-id').val()+'/1');
		$('#movie-items').load('movie/items');
	});

	$('#chk-liked').live('click', function () {
		$.get('rate/'+$('#movie-id').val()+'/2');
		$('#movie-items').load('movie/items');
	});

	$('.a-rate').live('click', function () {
		$.get($(this).attr('href'));
		if ($('#rate-hide').attr('checked')) {
			id = $(this).attr('href').match(/([^\/]+)\/(\d+)$/)[1];
			$('#'+id).hide(500);
		}
		//$('#movie-items').load('movie/items');
		return false;
	});

	$('#rate-hide').live('click', function () {
		if ($('#rate-hide').attr('checked'))
			$.get('rate/hide');
		else $.get('rate/show');

		window.refreshAll();
	});

	if ($('#hide_rate_value').val())
		$('#rate-hide').attr('checked', 'checked');
});