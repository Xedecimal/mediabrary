var page_loading = false;
var sub = { page: 0 };

$(function () {
	$('.a-title').live('click', function () {
		$('<div id="detail-dialog" />').dialog({
			width: '80%',
			height: 500,
			position: 'top',
			title: 'Item Details',
			close: function (e, ui) { $(e.target).remove(); }
		}).load($(this).attr('href'));

		return false;
	});

	$('.in-list-filter').autocomplete({
		minLength: 0,
		source: function (sender) {
			sub['q'] = {};
			$('.in-list-filter').each(function () {
				sub['q'][$(this).data('col')] = $(this).val();
			});
			clearItems();
		}
	});

	$('#table-list').tablesorter();
	$('#table-list').bind('sortEnd', function () {
		// Send sort columns

		sub['sort'] = {};

		$('.headerSortDown').each(function () { sub['sort'][$(this).data('col')] = -1; });
		$('.headerSortUp').each(function () { sub['sort'][$(this).data('col')] = 1; });

		clearItems();
	});

	$(window).scroll(function (e) {
		if (page_loading) return;
		var target = $('#table-list tr:last');
		if (target.length < 1) return;

		if (target.position().top <
			($(window).scrollTop() + $(window).height())) {
			page_loading = true;
			++sub['page'];
			$.get('view_list/items', sub, function (d) {
				$('#table-list tbody').append(d);
				page_loading = false;
			})
		}
	});
});

function clearItems() {
	sub['page'] = 0;
	$('#table-list tbody').load('view_list/items', sub);
}