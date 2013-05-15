$(function () {
	$(document).on('click', '.a-play', function () {
		// Present options for playing this file.
		$('<div title="Choose Player" />').load('player/select?path='+$(this).attr('href')).dialog();
		return false;
	});

	// Use VLC HTTP server.
	$(document).on('click', '#a-vlc', function () {
		var tpath = $(this).attr('href');

		var url = 'http://'+remote_ip+':8080/requests/status.xml';
		url += '?command=in_play';
		url += '&input='+tpath.replace(/'/g, "\\'");

		// Attempt in javascript to request through client's vlc player.
		$.get(url);

		return false;
	});
});
