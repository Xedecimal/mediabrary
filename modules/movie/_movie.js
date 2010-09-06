$(function () {
	$('#dialog-movie').dialog({
		autoOpen: false,
		width: '80%',
		position: 'top',

		beforeclose: function (event, ui) {
			$('#movie-items a').css('opacity', 1);
		}
	});

	$('.movie-item').live('click', function () {
		if ($(this).find('img').attr('src') == 'modules/movie/img/loading.jpg')
		{
			alert('Please wait until this item is done loading, multiple \
				requests will most likely cause you headaches.');
			return false;
		}

		$('#dialog-movie').dialog('option', 'title',
			'Details for '+$(this).attr('href'));

		$('#movie-items a').css('opacity', 1);
		$('#movie-items a[href!='+$(this).attr('href')+']').css('opacity', 0.15);

		$('#dialog-movie').load(app_abs+'/movie/detail',
			{path: $(this).attr('href')}, function () {
				$('#dialog-movie').dialog('open');
				img = $('input[name="movie-bd"]').val();
				//$('.ui-widget-overlay').css('background', 'url(\''+img+'\') no-repeat center');
			}
		);

		return false;
	});

	$('#filter-toggle').click(function () {
		$('.filter').toggle(500);
		return false;
	});
});
