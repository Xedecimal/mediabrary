$(function () {
	$.get('filter/get', function (data) {
		if (data.source == 'obtained') { data.step = 2592000; data.type = 'slider'; }
		else if (data.source == 'rating') { data.step = 0.5; data.type = 'slider' }
		else { data.step = 1; data.type = 'slider'; }
		showValue(data.cmin, data.cmax);

		if (data.type == 'slider') createSlider(data);
		else if (data.type == 'checks') createChecks(data);

		$('#selFilterType').val(data.source);
	}, 'json');

	$('#selFilterType').change(function () {
		$.get('filter/set/'+$(this).val(), function () {
			window.refreshAll();
		});
	});
});

function createSlider(data)
{
	$("#date-slider").slider({
		range: true,
		step: data.step,
		min: parseInt(data.min),
		max: parseInt(data.max),
		slide: function(event, ui) {
			showValue(ui.values[0], ui.values[1]);
		},
		stop: function(event, ui) {
			setFilter(ui.values[0], ui.values[1]);
		}
	});

	$('#date-slider').slider('values', 0, data.cmin);
	$('#date-slider').slider('values', 1, data.cmax);
}

function showValue(ts1, ts2)
{
	t = $('#selFilterType').val();

	// Date Types
	if (t == 'obtained')
		$("#date-value").text('Date '
			+new Date(ts1*1000).getFullYear()+' - '
			+new Date(ts2*1000).getFullYear());
	else // Numeric Types
		$("#date-value").text('Value: '+ts1+' - '+ts2);
}

function setFilter(min, max) {
	var type = $('#selFilterType').val();
	var range = $('#date-slider').slider('option', 'values');
	$.get('filter/set/'+type+'/'+range[0]+'/'+range[1], function () {
		window.refreshAll();
	});
}