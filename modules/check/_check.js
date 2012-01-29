$(function () {
	$('#a-scan').click(function () {
		startProcess('check/run');
		return false;
	});
	$('.a-fix').click(function () {
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
	$.get(app_abs+'/check/prepare', function () { checkStep() });
}

function checkStep()
{
	$.get(app_abs+'/check/one', function (data) {
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

var prevDataLength = 0;
var nextLine = null;

function createRequestObject() {
	var ro;
	if (window.XMLHttpRequest) { ro = new XMLHttpRequest(); }
	else { ro = new ActiveXObject("Microsoft.XMLHTTP"); }
	if (!ro) debug("Couldn't start XMLHttpRequest object");
	return ro;
}

function startProcess(dataUrl) {
	http = createRequestObject();
	http.open('get', dataUrl);
	http.onreadystatechange = handleResponse;
	http.send(null);
	pollTimer = setInterval(handleResponse, 1000);
}

function handleResponse() {
    if (http.readyState != 4 && http.readyState != 3) return;
    if (http.readyState == 3 && http.status != 200) return;
    if (http.readyState == 4 && http.status != 200) {
        clearInterval(pollTimer);
        inProgress = false;
    }

    if (http.responseText === null) return;

    while (prevDataLength != http.responseText.length) {
        if (http.readyState == 4
		&& prevDataLength == http.responseText.length) break;
        prevDataLength = http.responseText.length;
        var response = http.responseText.substring(nextLine);
        var lines = response.split('\n');
        nextLine = nextLine + response.lastIndexOf('\n') + 1;
        if (response[response.length-1] != '\n') lines.pop();
        for (var i = 0; i < lines.length; i++) $('#output').append(lines[i]);
    }

    if (http.readyState == 4 && prevDataLength == http.responseText.length)
        clearInterval(pollTimer);

    inProgress = false;
}