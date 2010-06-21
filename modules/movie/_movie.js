$(function () {
	$('#dialog-movie').dialog({
		'modal': true,
		autoOpen: false,
		width: '80%',
		position: 'top'
	});

	$('.movie-item').live('click', function () {
		if ($(this).find('img').attr('src') == 'modules/movie/img/loading.jpg')
		{
			alert('Please wait until this item is done loading, multiple \
				requests will most likely cause you headaches.');
			return false;
		}

		$('#dialog-movie').load(app_abs+'/movie/detail',
			{path: $(this).attr('href')}, function () {
				$('#dialog-movie').dialog('open');
				img = $('input[name="movie-bd"]').val();
				//$('.ui-widget-overlay').css('background', 'url(\''+img+'\') no-repeat center');
			}
		);
		return false;
	});

	$('#filter-toggle').click(function () {$('.filter').toggle(500); return false;});
});
