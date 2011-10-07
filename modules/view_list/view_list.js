var page_loading = false;
var page_current = 0;
var sort = {};

$(function () {
	$('.a-title').live('click', function () {
		$('<div />').dialog({
			width: '80%',
			height: 500,
			position: 'top',
			title: 'Item Details'
		}).load($(this).attr('href'));

		return false;
	});

	$('#table-list').tablesorter();
	$('#table-list').bind('sortEnd', function () {
		// Send sort columns

		$('.headerSortDown .dbname').each(function () { sort[$(this).val()] = -1; });
		$('.headerSortUp .dbname').each(function () { sort[$(this).val()] = 1; });

		$('#table-list tbody').load('view_list/items', {'sort': sort});
	});

	$(window).scroll(function (e) {
		if (page_loading) return;
		var target = $('#table-list tr:last');
		if (target.length < 1) return;

		if (target.position().top <
			($(window).scrollTop() + $(window).height())) {
			page_loading = true;
			$.get('view_list/items', { 'sort': sort, page: ++page_current }, function (d) {
				$('#table-list tbody').append(d);
				page_loading = false;
			})
		}
	});
});
