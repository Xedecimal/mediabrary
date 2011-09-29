window.refreshAll = function() {
	$('.refresh').each(function () {
		$('#'+$(this).attr('target')).load($(this).attr('href'));
	});
};

$(function () {
	window.refreshAll();
	$('.nav a').click(function () {
		$('#content').load($(this).attr('href'));
		return false;
	});
});