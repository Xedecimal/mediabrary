$(function () {
	$('.movie-item').live('hover', function (e) {
		if (e.type == 'mouseenter')
		{
			$(window.current).find('.movie-details').hide();
			$(this).find('.movie-details').show();
			window.current = this;
		}
	});

	$('.a-movie-item').live('click', function () {
		movieDetail($(this).attr('href'));
		return false;
	});

	$('#movie-items').load('movie/items');
});

function moveieDetail(id)
{
	$('<div id="detail-dialog" />').dialog({
		width: '80%',
		height: 500,
		position: 'top',
		title: 'Movie Details',

		close: function () { $('#detail-dialog').remove(); }
	}).load('movie/detail/'+id);
}
