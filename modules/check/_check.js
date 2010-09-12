$(function () {
	$('.a-fix').click(function () {
		$(this).load($(this).attr('href'));
		$(this).attr('href', '').html('<img src="img/load.gif" alt="loading" />')
		return false;
	});
	
	$('.aCheckCat').click(function () {
		$('#grp-'+$(this).attr('href')).toggle();
		return false;
	});
});