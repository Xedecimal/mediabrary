$(function () {
	$('#a-scan').click(function () {
		$('#output').html('');
		$('#output').addClass('loading');
		startProcess($(this).attr('href'));
		return false;
	});
	$('.a-fix').live('click', function () {
		$(this).load($(this).attr('href'));
		return false;
	});
});

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
		$('#output').removeClass('loading');
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
	{
		$('#output').removeClass('loading');
        clearInterval(pollTimer);
	}

    inProgress = false;
}