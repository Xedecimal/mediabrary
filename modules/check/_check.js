$(function () {
	$('.a-fix').click(function () {
		var up = $(this);
		$.get($(this).attr('href'), function () {
			up.html('Complete');
			if (window.hitall)
			{
				$(this).attr('class', '');
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

	$('.a-fixall').click(function () {
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
	$('.a-fix:first').click();
}
