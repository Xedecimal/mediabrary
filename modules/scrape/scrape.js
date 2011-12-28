$(function () {
	$('#a-scrape-find').live('click', scape_find);

	$('.but-scrape-research').live('click', scape_find);

	// A found result has been selected (not yet chosen).
	$('.find-result').live('click', function () {
		var name = $(this).attr('name');
		$('.covers-'+name).hide(500, function () {$(this).remove()});

		// Have the owning scraper get us some covers for this selection.
		$.get(window.app_abs+'/'+name+'/covers', {id: $(this).val()},
			function (data, stat, d2) {
				$.each(data.covers, function (ix, url) {
					var ins = $('<input type="image" src="'+url
						+'" class="scrape-cover covers-'+data.id+'" width="185"'
						+' height="275" />');
					$(ins).appendTo('#div-covers').hide().show(500);
				});
			}, 'json'
		);
	});

	$('.a-scrape-covers').live('click', function () {
		var id = $(this).attr('href');

		var dat = { type: $('#detail-type').val(), 'id': id };

		$.get(window.app_abs+'/scrape/covers', dat, function (covers) {
			$.each(covers, function (ix, url) {
				var ins = $('<input type="image" src="'+url
					+'" class="save-cover" width="185"'
					+' height="275" data-target="'+id+'" />');
				$(ins).appendTo('#details').hide().show(500);
			});
		}, 'json');

		return false;
	});

	$('.save-cover').live('click', function () {
		var cov = $(this).attr('src');
		var fs_path = $('#detail-path').val();
		$.get(window.app_abs+'/scrape/cover', dat, function () {
			
		});
	});

	$('.scrape-cover').live('click', function () {
		var fs_path = $('#detail-path').val();

		var cov = $(this).attr('src');

		var ids = {};

		$('.find-result:checked').each(function (ix, item) {
			ids[$(item).attr('name')] = $(item).val();
		});

		dat = {
			'type': $('#detail-type').val(),
			path: fs_path,
			cover: cov,
			'ids': ids
		};

		// Tell the scrapers to get to work.

		$('#details').html('<p>Collecting and saving the selected data...</p>');

		$.get(window.app_abs+'/scrape/scrape', dat, function (data) {
			// Restore dialog contents with new details.
			$('#detail-dialog').load(window.app_abs+'/'+dat['type']+'/detail/'
				+$('#detail-id').val());
		}, 'json');
	});
});

function scape_find(event) {
	dat = {
		type: $('#detail-type').val(),
		path: $('#detail-path').val()
	};

	// Typed in a title specifically to find.

	if ($('#in-title').val())
	{
		dat['manual'] = 1;
		dat['title'] = $('#in-title').val();
	}

	// Initial automatic search.

	else
	{
		dat['title'] = $('#movie_title').val();
		dat['date'] = $('#movie_released').val();
	}

	$('#details').html('<p>Currently searching...</p>');

	$.get(window.app_abs+'/scrape/find', dat, function (data) {
		$('#details').html(data);
	}, 'html');

	return false;
}
