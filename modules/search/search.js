$(function () {
	$('#movie-search').autocomplete({minLength: 0, source: function (sender) {
		$.get('search', {term: sender.term}, function (data) {
			window.refreshAll();
		}, 'html');
	}});

	$('#movie-sort').autocomplete({minLength: 0, source: function (sender) {
		$.get('search', {sort: sender.term}, function (data) {
			window.refreshAll();
		}, 'html');
	}});
});