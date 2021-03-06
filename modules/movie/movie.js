$(function () {
	$('#movie-items').on({
		mouseenter: function () {
			$(window.current).find('.movie-details').hide();
			$(this).find('.movie-details').show();
			window.current = this;
		},
		mouseleave: function() {
			$(window.current).find('.movie-details').hide();
			window.current = null;
		}
	}, '.movie-item');

	$(document).on('click', '.a-movie-item', function (e) {
		movieDetail($(this).attr('href').match(/([^/?]+)\?.*$/)[1]);
		e.preventDefault();
	});

	$('#movie-items').load('movie/items');
});

function movieDetail(id) {
	$.ajax({url: app_abs+'/movie/detail/'+id, dataType: 'text'}).done(function (data) {
		$div = $(data).filter('div');

		$div.dialog({
			width: '80%',
			height: 500,
			position: 'top',

			close: function () { $('#detail-dialog').remove(); }
		});

		$(data).filter('script').appendTo($div);
	});
}