$(function () {
	$('#dialog-movie').dialog({
		'modal': true,
		autoOpen: false,
		width: '80%',
		position: 'top'
	});

	$('.movie-item').live('click', function () {
		$('#dialog-movie').load('movie/detail',
			{path: $(this).attr('href')},
			function () { $('#dialog-movie').dialog('open'); }
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

	$('.remove').live('click', function () {
		if (confirm('Are you sure?'))
		{
			$.get('movie/remove/', {path: $(this).attr('href')}, function () {
				$('#dialog-movie').dialog('close');
			});
		}
		return false;
	});

	$('.scrape').live('click', function () {
		m_id = $(this).attr('id');
		path = $(this).attr('href').replace("'", "\\'");
		$('a[href="'+path+'"] img[class=movie-image]').attr('src', 'modules/movie/img/missing.jpg');
		basename = path.match(/([^/]+)\.([^.]+)$/)[0];
		$.get('movie/scrape', {target: path, tmdb_id: m_id}, function (data) {
			//$('#dialog-movie').html(data);
			$('a[href="'+data.med_path.replace("'","\\'")+'"] img[class=movie-image]').attr('src', data.med_thumb);
			$('#dialog-movie').dialog('close');
		}, 'json');
		return false;
	});
});
