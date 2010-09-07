$(function () {
	$('#dialog-tv').dialog({
		'modal': true,
		autoOpen: false,
		width: '80%',
		height: 600,
		position: 'top'
	});

	$('.tv-item').live('click', function () {
		$('#dialog-tv').dialog('option', 'title',
			'Details for '+$(this).attr('title'));

		$('#dialog-tv').load('tv/series',
			{'name': $(this).attr('title')},
			function () {
				$('#dialog-tv').dialog('open');
			}
		);
		return false;
	});

	$('#scrape-tv-link').live('click', function () {
		console.log($(this).attr('href'));
		$.get('tv/search', {series: $(this).attr('href')}, function (data) {
			$('#scrape-tv-link').after(data);
		}, 'html')
		return false;
	});

	$('#a-tv-scrape-all').click(function () {
		$("#progressbar").progressbar({value: 0});
		window.scrape_total = 0;
		window.scraped = 0;
		$('.tv-item').each(function (ix, item) {
			window.scrape_total += 1;
			$.get('tv/search/'+$(item).attr('title'), function (data) {
				window.scraped += 1;
				$("#progressbar").progressbar('option', 'value', window.scraped * 100 / window.scrape_total);
				if (window.scraped == window.scrape_total) window.location.reload();
			});
		});
	});

	$('#tv-items').load('tv/items');
});
