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
		path = $(this).attr('href').replace("'", "\\'");
		$('a[href="'+path+'"] img[class=movie-image]').attr('src', 'modules/movie/img/loading.jpg');
		basename = path.match(/([^/]+)\.([^.]+)$/)[0];
		$.get('tmdb/scrape', {target: path, tmdb_id: m_id}, function (data) {
			$('a[href="'+data.med_path.replace("'","\\'")+'"] img[class=movie-image]').attr('src', data.med_thumb);
			//$('#dialog-movie').html(data);
		}, 'json');
		$('#dialog-movie').dialog('close');

		return false;
	});
	$('#tmdb-aCovers').live('click', function () {
		$.get('tmdb/covers', {path: $(this).attr('href')}, function (data) {
			$('#movie-details').html(data);
		}, 'html');
		return false;
	});
	$('.tmdb-aCover').live('click', function () {
		path = $(this).attr('href');
		img = $(this).find('img').attr('src');
		$.get('movie/cover', {path: path, img: img}, function (data) {
			$('a[href="'+data.fs_path+'"] img[class=movie-image]').css('opacity', 1);
		//$('#movie-details').html(data);
		}, 'json');
		$('a[href="'+path+'"] img[class=movie-image]').attr('src', img).css('opacity', 0.25);
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

	if (event.data.source != undefined)
		dat['title'] = $(event.data.source).attr(event.data.attr);

	$.get('tmdb/find', dat, function (data) {
		$('#movie-details').html(data);
	}, 'html');
	return false;
}
