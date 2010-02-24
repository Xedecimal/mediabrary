$(function () {
	$.get('date/get', function (data) {
		$("#date-slider").slider({
			range: true,
			min: parseInt(data.min),
			max: parseInt(data.max),
			stop: function(event, ui) {
				$("#date-value").html('Date ' + ui.values[0] + ' - ' + ui.values[1]);
				setDate(ui.values[0], ui.values[1]);
			}
		});

		$('#date-slider').slider('values', 0, data.cmin);
		$('#date-slider').slider('values', 1, data.cmax);
	}, 'json');
});

function setDate(min, max) {
	$.get('date/set/'+min+'/'+max, function () {
		$('#movie-items').load('movie/items');
	});
}