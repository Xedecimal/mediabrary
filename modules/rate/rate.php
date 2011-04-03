<?php

class ModRate extends Module
{
	function __construct()
	{
		global $_d;

		$_d['rate.ds'] = $_d['db']->rate;
		$_d['rate.ds']->ensureIndex(array('entry' => 1, 'from' => 1),
			array('unique' => true, 'dropDups' => true));
	}

	function Link()
	{
		global $_d;

		$_d['movie.cb.head'][] = array($this, 'cb_movie_head');
		$_d['movie.cb.detail']['rate'] = array(&$this, 'cb_movie_detail');
		$_d['movie.cb.cover']['rate'] = array(&$this, 'cb_movie_cover');

		$_d['movie.cb.query']['joins']['rate'] = new Join($_d['rate.ds'],
			'rate_for = mov_id AND rate_from = '.sprintf('%u', ip2long(Server::GetVar('REMOTE_ADDR'))), 'LEFT JOIN');

		if (Server::GetVar('hide_rate'))
			$_d['movie.cb.query']['match']['rate_amount'] = Database::SqlIs('NULL');
	}

	function Prepare()
	{
		global $_d;

		if ($_d['q'][0] != 'rate') return;
		if ($_d['q'][1] == 'hide') $_SESSION['hide_rate'] = true;
		if ($_d['q'][1] == 'show') $_SESSION['hide_rate'] = false;

		$id = $_d['q'][1];
		$vote = $_d['q'][2];
		$ip = sprintf('%u', ip2long(Server::GetVar('REMOTE_ADDR')));

		$insert = array(
			'movie' => $id,
			'from' => $ip,
			'like' => $vote,
		);

		# Update the database
		$res = $_d['db']->command(array('findAndModify' => 'rate',
			'query' => array('entry' => $id),
			'update' => $insert, 'new' => 1, 'upsert' => 1));

		die($id);
	}

	function Get()
	{
		global $_d;
	}

	function cb_movie_head()
	{
		$t = new Template();
		$t->Set('hide_rate', Server::GetVar('hide_rate', ''));
		return $t->ParseFile(Module::L('rate/filter.xml'));
	}

	function cb_movie_detail($item)
	{
		if (!array_key_exists('rate_amount', $item)) return $item;
		$seen = '';
		$liked = '';
		if ($item['rate_amount'] > 0) $seen = ' checked="checked"';
		if ($item['rate_amount'] > 1) $liked = ' checked="checked"';
		$item['details']['Seen'] = '<input type="checkbox" id="chk-seen"'.$seen.' />';
		$item['details']['Liked'] = '<input type="checkbox" id="chk-liked"'.$liked.' />';
		return $item;
	}

	function cb_movie_cover($t)
	{
		return <<<EOF
<a href="rate/{{_id}}/2" class="a-rate"><img src="modules/rate/img/good.png" alt="Good" /></a>
<a href="rate/{{_id}}/1" class="a-rate"><img src="modules/rate/img/bad.png" alt="Bad" /></a>
EOF;
	}
}

Module::Register('ModRate');

?>
