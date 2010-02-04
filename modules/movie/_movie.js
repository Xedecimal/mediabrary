$(function () {
	$('#dialog-movie').dialog({
		'modal': true,
		autoOpen: false,
		width: '80%',
		position: 'top'
	});

	$('.movie-item').live('click', function () {
		$('#dialog-movie').load(app_abs+'/movie/detail',
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
		$('a[href="'+path+'"] img[class=movie-image]').attr('src', 'modules/movie/img/loading.jpg');
		basename = path.match(/([^/]+)\.([^.]+)$/)[0];
		$.get('movie/scrape', {target: path, tmdb_id: m_id}, function (data) {
			$('a[href="'+data.fs_path.replace("'","\\'")+'"] img[class=movie-image]').attr('src', data.med_thumb);
			//$('#dialog-movie').html(data);
		}, 'json');
		 $('#dialog-movie').dialog('close');

		return false;
	});

	$('#movie-search').autocomplete({source: function (sender) {
		$.get('movie', {query: sender.term}, function (data) {
			$('#movies').html(data);
		}, 'html');
	}});

	$('#a-movie-cover').live('click', function () {
		$.get('movie/covers', {path: $(this).attr('href')}, function (data) {
			$('#movie-details').html(data);
		});
		return false;
	});

	$('#a-grab-cover').live('click', function () {
		path = $(this).attr('href');
		img = $(this).find('img').attr('src');
		$.get('movie/cover', {path: path, img: img}, function (data) {
			$('a[href="'+data.fs_path.replace("'","\\'")+'"] img[class=movie-image]').attr('src', data.med_thumb);
		}, 'json');
		$('a[href="'+path+'"] img[class=movie-image]').attr('src', img);
		$('#dialog-movie').dialog('close');
		return false;
	});
});
