$(function () {
	$('#tmdb-aSearch').live('click', movie_find);
	$('#tmdb-butFind').live('click', { source: '#inTitle', attr: 'value' }, movie_find);
	$('#tmdb-aRemove').live('click', function () {
		if (confirm('Are you sure?'))
		{
			$.get('movie/remove/', {path: $(this).attr('href')}, function () {
				$('#dialog-movie').dialog('close');
			});
		}
		return false;
	});

	$('.tmdb-aScrape').live('click', function () {
		m_id = $(this).attr('id');
		path = $(this).attr('href');
		$('div[title="'+path+'"]').css('background-image', 'url("modules/movie/img/loading.jpg")');
		$.getJSON('tmdb/scrape', {target: path, tmdb_id: m_id}, function (data) {
			if (data.error)
				var img = 'modules/movie/img/missing';
			else
				var img = data.med_thumb;
			$('div[title="'+data.mov_path+'"]').css('background-image', 'url("'+img+'")');
		});
		$('#dialog-movie').dialog('close');
		return false;
	});

	$('#tmdb-aCovers').live('click', function () {
		$('#movie-details').load('tmdb/covers', {path: $(this).attr('href')});
		return false;
	});

	$('.tmdb-aCover').live('click', function () {
		path = $(this).attr('href');
		img = $(this).find('img').attr('src');
		$.get('movie/cover', {path: path, img: img}, function (data) {
			$('div[title="'+data.fs_path+'"]').css('opacity', 1);
		}, 'json');
		$('div[title="'+path+'"]').css('background-image', 'url("'+img+'")').css('opacity', 0.25);
		$('#dialog-movie').dialog('close');
		return false;
	});

	$('.tmdb-aFixCover').live('click', function () {
		$(this).load('tmdb/fixCover', {path: $(this).attr('href')});
		return false;
	});
});

function movie_find(event) {
	dat = { path: $('#movie_path').val() };

	if ($('#inTitle').val())
	{
		dat['manual'] = 1;
		dat['title'] = $('#inTitle').val();
	}
	else dat['title'] = $('#movie_title').val();

	$.get('tmdb/find', dat, function (data) {
		$('#movie-details').html(data);
	}, 'html');
	return false;
}
