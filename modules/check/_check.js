$(function () {
	$('#a-scan').button();
	$('#a-scan').click(function () { checkPrepare(); });
	$('.a-fix').button().click(function () {
		$.get($(this).attr('href'));
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
	$('#output').addClass('loading');
	$.get('check/prepare', function () { checkStep() });
}

function checkStep()
{
	$.get('check/one', function (data) {
		if (!data || data.stop)
		{
			window.proceed = false;
			$('#output').removeClass('loading');
		}

		if (data.msg) {
			var entry = $('<div class="entry"><span class="source">'+data.source
				+'</span><span class="msg">'+data.msg+'</span></div>');
			$('#output').prepend(entry);
			if (window.proceed) checkStep();
		}
		else if (window.proceed) { window.proceed = false; checkPrepare(); }
		else $('#output').removeClass('loading');
	}, 'json');
}