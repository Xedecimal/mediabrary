$(function () {
	$('#a-scrape-find').live('click', movie_find);
	//TODO: Do we need this source/attr data?
	$('#but-scrape-find').live('click', { source: '#inTitle', attr: 'value' }, movie_find);

	// A found result has been selected (not yet chosen).
	$('.find-result').live('click', function () {
		var name = $(this).attr('name');
		$('.covers-'+name).hide(500, function () {$(this).remove()});

		// Have the owning scraper get us some covers for this selection.
		$.get(name+'/covers', {id: $(this).val()}, function (data, stat, d2) {
			$.each(data.covers, function (ix, url) {
				var ins = $('<input type="image" src="'+url
					+'" class="scrape-cover covers-'+data.id+'" width="185"'
					+' height="275" />');
				$(ins).appendTo('#div-covers').hide().show(500);
				//$().append();
			});
		}, 'json');
	});

	$('.scrape-cover').live('click', function () {
		var fs_path = $('#movie_path').val();

		var cov = $(this).attr('src');

		// Place cover image
		$('.movie-item[title="'+fs_path+'"]').css('background',
			'url("'+cov+'")');

		var ids = {};

		$('.find-result:checked').each(function (ix, item) {
			ids[$(item).attr('name')] = $(item).val();
		});

		dat = { path: fs_path, cover: cov, 'ids': ids }
		$.get('scrape/scrape', dat, function (data) {
		}, 'json');

		// Close movie dialog
		$('#dialog-movie').dialog('close');
	});
});

function movie_find(event) {
	dat = { path: $('#movie_path').val() };

	// Re-searching manually.
	if ($('#inTitle').val())
	{
		dat['manual'] = 1;
		dat['title'] = $('#inTitle').val();
	}
	// Initial automatic search.
	else
	{
		dat['title'] = $('#movie_title').val();
		dat['date'] = $('#movie_date').val();
	}

	$.get('scrape/find', dat, function (data) {
		$('#movie-details').html(data);
	}, 'html');

	return false;
}
