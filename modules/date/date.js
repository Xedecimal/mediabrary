$(function () {
	$.get('date/values', function (data) {
		$("#date-slider").slider({
			range: true,
			min: parseInt(data.min),
			max: parseInt(data.max),
			stop: function(event, ui) {
				$("#date-value").html('Date ' + ui.values[0] + ' - ' + ui.values[1]);
				$('#movie-items').load('movie/items');
			}
		});

		$('#date-slider').slider('values', 0, parseInt(data.cmin));
		$('#date-slider').slider('values', 1, parseInt(data.cmax));
	}, 'json');
});