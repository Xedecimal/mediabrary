<null>
var remote_ip = '{{REMOTE_ADDR}}';

$(function () {
	$('.a-play').live('click', function () {

		// Present options for playing this file.
		$('#player-dialog').load('player/select?path='+$(this).attr('href')).dialog();
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