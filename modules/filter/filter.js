$(function () {
	$.get('filter/get', function (data) {
		//console.dir(data);
		for (key in data)
		{
			//console.log('Key: '+key+', Val: '+data[key]);
			$('.a-filter[href="{\"'+key+'\": \"'+data[key]+'\"}"]').addClass('selected');
		}
	}, 'json');

	$('.a-filter').click(function () {
		$(this).addClass('selected');
		$.get('filter/set', {mask: $(this).attr('href')}, function () {
			window.refreshAll();
		});

		return false;
	});

	$('.filter li').click(function () {
		$(this).children().toggle(500);
		return false;
	});
});
