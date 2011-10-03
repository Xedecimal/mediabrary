$(function () {
	$('#dialog-tv').dialog({
		'modal': true,
		autoOpen: false,
		width: '80%',
		height: 600,
		position: 'top'
	});

	$('.a-tv-item').live('click', function () {
		$('#dialog-tv').dialog('option', 'title',
			'Details for '+$(this).attr('title'));

		$('#dialog-tv').load('tv/series',
			{'name': $(this).attr('href')},
			function () {
				$('#dialog-tv').dialog('open');
			}
		);
		return false;
	});

	$('#scrape-tv-link').live('click', function () {
		$.get('tv/search', {series: $(this).attr('href')}, function (data) {
			$('#scrape-tv-link').after(data);
		}, 'html')
		return false;
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
