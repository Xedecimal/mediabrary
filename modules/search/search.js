$(function () {
	$('#movie-search').autocomplete({source: function (sender) {
		$.get('movie', {query: sender.term}, function (data) {
			$('#movies').html(data);
		}, 'html');
	}});
});