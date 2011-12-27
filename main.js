window.refreshAll = function() {
	$('.refresh').each(function () {
		$('#'+$(this).attr('target')).load($(this).attr('href'));
	});
};

$(function () {
	$('.shower').live('click', function () {
		$('#'+$(this).attr('href')).toggle();
		return false;
	});
});
