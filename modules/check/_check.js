$(function () {
	$('#a-scan').button();
	$('#a-scan').click(function () { checkPrepare(); });

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

window.proceed = true;
function checkPrepare()
{
	twopass = false;
	$.get('check/prepare', function () { checkStep() });
}

function checkStep()
{
	$.get('check/one', function (data) {
		if (data.msg) {
			var entry = $('<div class="entry"><span class="source">'+data.source
				+'</span><span class="msg">'+data.msg+'</span></div>');
			$('#output').append(entry.fadeIn());
			entry[0].scrollIntoView();
			checkStep();
			window.proceed = true;
		}
		else if (window.proceed) { window.proceed = false; checkPrepare(); }
	}, 'json');


}