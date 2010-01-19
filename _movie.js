$(function () {
	$('#dialog-movie').dialog({'modal': true, autoOpen: false});

	$('.movie-item').live('click', function () {
		$('#dialog-movie').load('movie/detail',
			{path: $(this).attr('href')},
			function () {
				$('#dialog-movie').dialog('open');
			}
		);
		return false;
	});

	$('.search').live('click', function () {
		$('.movie-result').remove();
		path = $(this).attr('href');
		$.get('movie/find', {'path': path}, function (data) {
			$('#dialog-movie').append(data);
		}, 'html');
		return false;
	});

	$('.scrape').live('click', function () {
		m_id = $(this).attr('id');
		path = $(this).attr('href');
		basename = path.match(/([^/]+)\.([^.]+)$/)[0];
		$.get('movie/scrape', {target: path, tmdb_id: m_id}, function (data) {
			$('#dialog-movie').html(data);
			//$('a[href="'+path+' img').attr('src', IMAGE_SOURCE_HERE);
			//$('#dialog-movie').dialog('close');
		}, 'html');
		return false;
	});
});
