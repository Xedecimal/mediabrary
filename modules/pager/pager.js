window.loading = false;
window.page = 0;

$(function () {
	$(window).scroll(function (e) {
		if (window.loading) return;
		if ($('.movie-item-grid:last').length < 1) return;
		if ($('.movie-item-grid:last').position().top < ($(window).scrollTop() +
		$(window).height()))
		{
			window.loading = true;
			$.get('movie/items?page='+(++window.page), function (d) {
				$('#movie-items').append(d);
				window.loading = false;
			})
		}
	});
});