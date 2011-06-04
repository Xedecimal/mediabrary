$(function () {
	$.get('filter/get', function (data) {
		//console.dir(data);
		for (key in data)
		{
			//console.log('Key: '+key+', Val: '+data[key]);
			$('.a-filter[href="{\"'+key+'\": \"'+data[key]+'\"}"]').addClass('selected');
		}
	}, 'json');

	$('#selFilterType').change(function () {
		$.get('filter/set/'+$(this).val(), function () {
			window.refreshAll();
		});
		return false;
	});
});
