$(function () {
	$(document).on('click', '.a-tv-item', function () {
		$('<div id="detail-dialog" />').dialog({
			'modal': true,
			width: '80%',
			height: 600,
			position: 'top',
			title: 'Details for '+$(this).attr('title'),

			close: function () { $('#detail-dialog').remove(); }
		}).load(app_abs+'/tv-series/detail/'+$(this).attr('href'));

		return false;
	});
	
	$(document).on('click', '.a-remove', function () {
		$(this).load($(this).attr('href'));
	});

	$('#a-tv-scrape-all').click(function () {
		$("#progressbar").progressbar({value: 0});
		window.scrape_total = 0;
		window.scraped = 0;
		$('.a-tv-item').each(function (ix, item) {
			window.scrape_total += 1;
			$.get('tv/search', { series: $(item).attr('href') },
				function () {
				window.scraped += 1;
				$("#progressbar").progressbar('option', 'value',
					window.scraped * 100 / window.scrape_total);
				if (window.scraped == window.scrape_total) window.location.reload();
			});
		});
	});

	$('#tv-items').load(window.app_abs+'/tv/items');
});
