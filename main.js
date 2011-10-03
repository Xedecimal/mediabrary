window.refreshAll = function() {
	$('.refresh').each(function () {
		$('#'+$(this).attr('target')).load($(this).attr('href'));
	});
};

$(function () {
	$('#left').animate({'marginLeft': -190});
	$('#left').mouseenter(function () { $('#left').css({'marginLeft': 0}); });
	$('#left').mouseleave(function () { $('#left').css({'marginLeft': -190}); });
});