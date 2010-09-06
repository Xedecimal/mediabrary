$(function () {
	$('.a-fix').click(function () {
		$(this).load($(this).attr('href'));
		return false;
	});
	
	$('.aCheckCat').click(function () {
		$('#grp-'+$(this).attr('href')).toggle();
		return false;
	});
});