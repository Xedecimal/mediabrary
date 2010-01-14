$(function () {
	var active = null;

	$('.movie-item').live('click', function () {
		if (active != null) deselect(active);
		active = $(this);
		select(active);
	});

	$('.search').live('click', function () {
		target = $(this).parent().parent().parent().parent();
		target.animate({width: '370px', height: '550px'}, 1000);
		path = target.attr('title');
		target.load('movie/search', {target: path});
		return false;
	});

	$('.scrape').live('click', function () {
		m_id = $(this).attr('id');
		target = $(this).parent().parent().parent().parent();
		path = target.attr('title');
		target.load('movie/scrape', {target: path, tmdb_id: m_id}, function () {
			target.animate({width: '185px', height: '275px'});
		});
		return false;
	});
});

function select(target) 
{
	id = target.attr('id').match(/\S+:(\S+)/)[1];
	html = '<div class="movie-detail" id="div:'+id+'"></div>';
	dat = $(html).load('movie/detail/'+id);
	dat.appendTo(target);
}

function deselect(target) 
{
	target.css({width: '185px', height: '275px', overflow: 'hidden'});
	target.find('.movie-detail').remove();
}
