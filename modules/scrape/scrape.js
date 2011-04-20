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
		$('.find-result:checked').each(function (ix, item) {
			var scraper = $(item).attr('name');
			var id = $(item).val();
			dat = { path: $('#movie_path').val(), 'id': id }
			$.get(scraper+'/scrape', dat, function (data) {
				console.dir(data);
			}, 'json');
			$('#scrape-'+scraper+' .results').hide(500, function () {
				$(this).html('Scraping...').show(500);
			});
		});
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
	else dat['title'] = $('#movie_title').val();

	$.get('scrape/find', dat, function (data) {
		$('#movie-details').html(data);
	}, 'html');

	return false;
}
