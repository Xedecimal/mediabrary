<?php

class ModRate extends Module
{
	function __construct()
	{
		global $_d;

		$_d['rate.ds'] = new DataSet($_d['db'], 'rate');
	}

	function Link()
	{
		global $_d;

		$_d['movie.cb.head'][] = array($this, 'cb_movie_head');
		$_d['movie.cb.detail']['rate'] = array(&$this, 'cb_movie_detail');
		$_d['movie.cb.cover']['rate'] = array(&$this, 'cb_movie_cover');

		$_d['movie.cb.query']['joins']['rate'] = new Join($_d['rate.ds'],
			'rate_for = med_id AND rate_from = '.sprintf('%u', ip2long(GetVar('REMOTE_ADDR'))), 'LEFT JOIN');

		if (GetVar('hide_rate'))
			$_d['movie.cb.query']['match']['rate_amount'] = SqlIs('NULL');
	}

	function Prepare()
	{
		global $_d;

		if ($_d['q'][0] != 'rate') return;
		if ($_d['q'][1] == 'hide') $_SESSION['hide_rate'] = true;
		if ($_d['q'][1] == 'show') $_SESSION['hide_rate'] = false;
	}

	function Get()
	{
		global $_d;

		if (@$_d['q'][0] == 'rate')
		{
			$id = $_d['q'][1];
			$vote = $_d['q'][2];
			$ip = sprintf('%u', ip2long(GetVar('REMOTE_ADDR')));
			$_d['rate.ds']->Add(array(
				'rate_for' => $id,
				'rate_from' => $ip,
				'rate_amount' => $vote,
			), true);
			die($id);
		}
	}

	function cb_movie_head()
	{
		$t = new Template();
		$t->Set('hide_rate', GetVar('hide_rate', ''));
		return $t->ParseFile(l('rate/filter.xml'));
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
<a href="rate/{{med_id}}/2" class="a-rate"><img src="modules/rate/img/good.png" alt="Good" /></a>
<a href="rate/{{med_id}}/1" class="a-rate"><img src="modules/rate/img/bad.png" alt="Bad" /></a>
EOF;
	}
}

Module::Register('ModRate');

?>
