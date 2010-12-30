<?php

class ModSimilar extends Module
{
	function Get()
	{
		global $_d;

		if ($_d['q'][0] != 'similar') return;

		$t = new Template();
		$t->ReWrite('item', array(&$this, 'TagItem'));
		die($t->ParseFile(l('similar/t.xml')));
	}

	function Link()
	{
		global $_d;

		$_d['cb.head']['similar'] = array(&$this, 'cb_head');
		$_d['movie.cb.buttons']['similar'] =
			array(&$this, 'cb_buttons_similar');
		$_d['tv.cb.buttons']['similar'] =
			array(&$this, 'cb_buttons_similar');
	}

	function cb_buttons_similar($t)
	{
		if (!isset($t->vars['med_title'])) return;
		$icon = p('similar/img/icon.png');
		return '<a href="{{med_title}}" id="a-similar"><img src="'.
			$icon.'" alt="icon" /></a>';
	}

	function TagItem($t, $g)
	{
		$token = Glue::GetToken();
		$keys = Glue::FindObject($token, Server::GetVar('s'));
		$items = Glue::GetSimilar($token, $keys[0]);

		$vp = new VarParser();
		$ret = null;
		foreach ($items as $i)
		{
			$ret .= $vp->ParseVars($g, $i);
		}
		return $ret;
	}

	function cb_head()
	{
		$js = p('similar/js.js');
		$css = p('similar/css.css');

		return <<<EOF
<script type="text/javascript" src="$js"></script>
<link type="text/css" rel="stylesheet" href="$css" />
EOF;
	}
}

class Glue
{
	static function GetToken()
	{
		global $_d;

		$dat = file_get_contents('http://api.getglue.com/v2/user/login?'
			.http_build_query(array(
				'userId' => (string)$_d['config']->glue->attributes()->user,
				'password' => (string)$_d['config']->glue->attributes()->pass
			))
		);
		$sx = simplexml_load_string($dat);
		$token = $sx->xpath('//response/ping/token');
		$token = (string)$token[0];
		return $token;
	}

	static function FindObject($token, $query)
	{
		$dat = file_get_contents('http://api.getglue.com/v2/glue/findObjects?'
			.http_build_query(array('q' => $query, 'token' => $token)));

		$sx = simplexml_load_string($dat);

		$keys = array();
		foreach ($sx->xpath('//response/matches/tv_shows') as $m)
			$keys[] = (string)$m->tv_shows->key;
		foreach ($sx->xpath('//response/matches/movies') as $m)
			$keys[] = (string)$m->movies->key;

		return $keys;
	}

	static function GetSimilar($token, $key)
	{
		$dat = file_get_contents('http://api.getglue.com/v2/object/similar?'
			.http_build_query(array('objectId' => $key, 'token' => $token)));

		$sx = simplexml_load_string($dat);
		$res = $sx->xpath('//response/similar/glue/items/item');
		$ret = array();
		foreach ($res as $i)
		{
			$ret[] = array(
				'key' => (string)$i[0]->key,
				'url' => (string)$i[0]->url,
				'title' => (string)$i[0]->title,
				'image' => (string)$i[0]->image,
				'modelName' => (string)$i[0]->modelName
			);
		}

		return $ret;
	}
}

Module::Register('ModSimilar');

?>
