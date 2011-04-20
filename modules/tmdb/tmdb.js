$(function () {
	$('#tmdb-aRemove').live('click', function () {
		if (confirm('Are you sure?'))
		{
			$.get('movie/remove/', {id: $(this).attr('href')}, function () {
				$('#dialog-movie').dialog('close');
			});
		}
		return false;
	});

	$('.tmdb-aScrape').live('click', function () {
		m_id = $(this).attr('id');
		path = $(this).attr('href');
		$('div[title="'+path+'"]').css('background-image', 'url("modules/movie/img/loading.jpg")');
		var img;
		$.getJSON('tmdb/scrape', {target: path, tmdb_id: m_id}, function (data) {
			if (data.error) img = 'modules/movie/img/missing';
			else img = data.med_thumb;
			$('div[title="'+data.fs_path+'"]').css('background-image', 'url("'+img+'")');
		});
		$('#dialog-movie').dialog('close');
		return false;
	});

	$('#tmdb-aCovers').live('click', function () {
		$('#movie-details').load('tmdb/covers/'+$(this).attr('href'));
		return false;
	});

	$('.tmdb-aCover').live('click', function () {
		id = $(this).attr('href');
		img = $(this).find('img').attr('src');
		$.get('movie/cover/'+id, {image: img}, function (data) {
			$('div[title="'+data.fs_path+'"]').css(
				'background-image', 'url("'+img+'")');
		}, 'json');
		$('#dialog-movie').dialog('close');
		return false;
	});

	$('.tmdb-aFixCover').live('click', function () {
		$(this).load('tmdb/fixCover', {path: $(this).attr('href')});
		return false;
	});
});
