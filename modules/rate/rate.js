$(function () {
	$('#chk-seen').live('click', function () {
		$.get('rate/'+$('#movie-id').val()+'/1');
		$('#movie-items').load('movie/items');
	});

	$('#chk-liked').live('click', function () {
		$.get('rate/'+$('#movie-id').val()+'/2');
		$('#movie-items').load('movie/items');
	});

	$('#rate-hide').click(function () {
		if ($('#rate-hide').attr('checked'))
			$.get('rate/hide');
		else $.get('rate/show');

		$('#movie-items').load('movie/items');
	});
});