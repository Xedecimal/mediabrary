var sel = null;

$(function () {
	$('.tv-item').live('click', function () {
		$('#tv-series').remove();
		sel = $(this);
		title = $(this).attr('title');
		jQuery.get('tv/series', {'name': title}, function (dat) {
			sel.after(dat);
		}, 'html');
		return false;
	});

	$('#scrape-tv-link').live('click', function () {
		$.get('tv/search/'+$(this).attr('title'), function (data) {
			$('#scrape-tv-link').after(data);
		}, 'html')
		return false;
	});
});
