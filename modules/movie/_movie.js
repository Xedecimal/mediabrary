$(function () {
	$('#dialog-movie').dialog({
		autoOpen: false,
		width: '80%',
		height: 500,
		position: 'top',

		beforeclose: function () {
			$('#movie-items a').css('opacity', 1);
		}
	});

	$('.movie-item').live('hover', function (e) {
		if (e.type == 'mouseenter')
		{
			$(window.current).find('.movie-details').hide();
			$(this).find('.movie-details').show();
			window.current = this;
		}
	});

	$('.a-movie-item').live('click', function () {
		$('#dialog-movie').dialog('option', 'title',
			'Details for '+$(this).attr('href'));

		$('#dialog-movie').load(app_abs+'/movie/detail',
			{path: $(this).attr('href')}, function () {
				$('#dialog-movie').dialog('open');
			}
		);

		return false;
	});

	$('#filter-toggle').click(function () {
		$('.filter').toggle(500);
		return false;
	});
});
