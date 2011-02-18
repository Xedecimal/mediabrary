<null>
var remote_ip = '{{REMOTE_ADDR}}';

$(function () {
	$('.a-play').live('click', function () {
		// Attempt to control client's VLC

		// We need a translated destination.
		window.req = $(this).attr('href');
		path = window.req.match(/player\?path=(.*)/)[1];
//		$.get('{{app_abs}}/player/try_play?path='+path, function (data) {
//			if (data != 'success')
//				// Couldn't find VLC, send browser to m3u file.
//				document.location.href = window.req;
//		})

		return true;
	});
});
</null>
