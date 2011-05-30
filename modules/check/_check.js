$(function () {
	$('.a-fix').click(function () {
		var up = $(this);
		$.get($(this).attr('href'), function () {
			up.html('Complete');
			if (window.hitall)
			{
				up.attr('class', '');
				stepFix();
			}
		});
		$(this).attr('href', '#').html('<img src="img/load.gif" alt="loading" />');
		return false;
	});

	$('.a-nogo').click(function () {
		$.get($(this).attr('href'));
		return false;
	});

	$('.a-fixthese').click(function () {
		window.fixparent = '#fg-'+$(this).attr('href');
		window.hitall = true;
		stepFix();
		return false;
	});

	$('.aCheckCat').click(function () {
		$('#grp-'+$(this).attr('href')).toggle();
		return false;
	});
});

function stepFix()
{
	$(window.fixparent).find('.a-fix:first').click();
}
