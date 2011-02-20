<null>
var remote_ip = '{{REMOTE_ADDR}}';

$(function () {
	$('.a-play').live('click', function () {
		var path = $(this).attr('href').match(/player\?path=(.*)/)[1];
		$('#player-dialog').load('player/select?path='+path).dialog();
		// Attempt to control client's VLC

		// We need a translated destination.
		/*window.req = $(this).attr('href').toString();

		/*

		// Direct a request through the webserve to the client's VLC server.
		v = $.ajax('{{app_abs}}/player/try_play?path='+path, {
			timeout: 1500,
			complete: function (jq, stat) {
				// Couldn't find VLC, send browser to m3u file.
				if (jq.statusText == 'timeout') {
					window.location.href = window.req;
				}
			}
		})

		*/
		return false;
	});

	// Use VLC HTTP server.
	$('#a-vlc').live('click', function () {
		var tpath = $(this).attr('href');

		var url = 'http://'+remote_ip+':8080'
		url += '/requests/status.xml?command=in_play&input='+tpath;

		// Attempt in javascript to request through client's vlc player.
		$.get(url);

		return false;
	});
});
</null>