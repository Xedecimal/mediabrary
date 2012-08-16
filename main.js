window.refreshAll = function() {
	$('.refresh').each(function () {
		$('#'+$(this).attr('target')).load($(this).attr('href'));
	});
};

$(function () {
	$(document).on('click', '.shower', function () {
		$('#'+$(this).attr('href')).toggle();
		return false;
	});

	$(document).on('click', '.halt', function (e) { e.preventDefault(); })
});
