$(function () {
	$('#mediainfo-table').tablesorter();
	$('.hider').change(function () {
		$('.'+$(this).val()).css('display', $(this).attr('checked') ? 'none' : '');
	});
})
