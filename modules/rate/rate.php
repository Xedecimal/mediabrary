<?php

class ModRate extends Module
{
	function __construct()
	{
		global $_d;
		$this->CheckActive('rate');
	}

	function Link()
	{
		global $_d;

		$_d['movie.cb.head'][] = array($this, 'cb_movie_head');
		$_d['movie.cb.detail']['rate'] = array(&$this, 'cb_movie_detail');
		$_d['movie.cb.cover']['rate'] = array(&$this, 'cb_movie_cover');

		$ip = sprintf('%u', ip2long(Server::GetVar('REMOTE_ADDR')));

		if ($this->Active && $_d['q'][1] == 'hide')
			$_SESSION['hide_rate'] = true;
		else if ($this->Active && $_d['q'][1] == 'show')
			$_SESSION['hide_rate'] = false;
		if (Server::GetVar('hide_rate'))
			$_d['movie.cb.query']['match']["rates.$ip"] = null;
	}

	function Prepare()
	{
		global $_d;

		if (!$this->Active) return;
		else
		{
			$id = new MongoID($_d['q'][1]);
			$vote = $_d['q'][2];
			$ip = sprintf('%u', ip2long(Server::GetVar('REMOTE_ADDR')));

			$item = $_d['entry.ds']->findOne(array('_id' => $id));
			$item['rates'][$ip] = (int)$vote;

			# Update the database
			$_d['entry.ds']->save($item);

			die($id);
		}
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

	function cb_movie_detail($details, $item)
	{
		$ip = sprintf('%u', ip2long(Server::GetVar('REMOTE_ADDR')));
		$liked = $seen = '';
		if (empty($item->Data['rates'][$ip])) $liked = $seen = '';
		else if ($item->Data['rates'][$ip] > 1) $liked = ' checked="checked"';
		else if ($item->Data['rates'][$ip] > 0) $seen = ' checked="checked"';
		$details['Seen'] = '<input type="checkbox" id="chk-seen"'.$seen.' />';
		$details['Liked'] = '<input type="checkbox" id="chk-liked"'.$liked.' />';
		return $details;
	}

	function cb_movie_cover($t)
	{
		return <<<EOF
<a href="rate/{{Data._id}}/2" class="a-rate"><img src="modules/rate/img/good.png" alt="Good" /></a>
<a href="rate/{{Data._id}}/1" class="a-rate"><img src="modules/rate/img/bad.png" alt="Bad" /></a>
EOF;
	}
}

Module::Register('ModRate');

?>
