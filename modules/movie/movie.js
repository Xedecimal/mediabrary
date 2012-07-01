$(function () {
	$('.movie-item').live('hover', function (e) {
		if (e.type == 'mouseenter')
		{
			$(window.current).find('.movie-details').hide();
			$(this).find('.movie-details').show();
			window.current = this;
		}
		else
		{
			$(window.current).find('.movie-details').hide();
			window.current = null;
		}
	});

	$('.a-movie-item').live('click', function () {
		movieDetail($(this).attr('href').match(/([^/?]+)\?.*$/)[1]);
		return false;
	});

	$('#movie-items').load('movie/items');
});

function movieDetail(id)
{
	$.get(app_abs+'/movie/detail/'+id).success(function (data) {
		$(data).dialog({
			width: '80%',
			height: 500,
			position: 'top',

			close: function () { $('#detail-dialog').remove(); }
		});
	});
}