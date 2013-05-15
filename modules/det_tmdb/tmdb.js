$(function () {
	$(document).on('click', '#tmdb-aRemove', function () {
		if (confirm('Are you sure?'))
		{
			$.get('movie/remove/', {id: $(this).attr('href')}, function () {
				$('#dialog-movie').dialog('close');
			});
		}
		return false;
	});

	$(document).on('click', '.tmdb-aScrape', function () {
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

	$(document).on('click', '#tmdb-aCovers', function () {
		$('#movie-details').load('tmdb/covers/'+$(this).attr('href'));
		return false;
	});

	$(document).on('click', '.tmdb-aCover', function () {
		id = $(this).attr('href');
		img = $(this).find('img').attr('src');
		$.get('movie/cover/'+id, {image: img}, function (data) {
			$('div[title="'+data.fs_path+'"]').css(
				'background-image', 'url("'+img+'")');
		}, 'json');
		$('#dialog-movie').dialog('close');
		return false;
	});

	$(document).on('click', '.tmdb-aFixCover', function () {
		$(this).load('tmdb/fixCover', {path: $(this).attr('href')});
		return false;
	});
});
