<?php

class ModSimilar extends Module
{
	function __construct()
	{
		$this->CheckActive('similar');
		
		if (!$this->Active) return;

		$this->finders[] = 'TasteKid';

		$t = new Template();
		$t->ReWrite('item', array(&$this, 'TagItem'));
		die($t->ParseFile(Module::L('similar/t.xml')));
	}

	function Link()
	{
		global $_d;

		$_d['movie.cb.buttons']['similar'] =
			array(&$this, 'cb_buttons_similar');
		$_d['tv.cb.buttons']['similar'] =
			array(&$this, 'cb_buttons_similar');
	}

	function Get()
	{
		global $_d;

		$js = Module::P('similar/js.js');
		$css = Module::P('similar/css.css');

		$ret['head'] =  <<<EOF
<script type="text/javascript" src="$js"></script>
<link type="text/css" rel="stylesheet" href="$css" />
EOF;

		return $ret;
	}

	function cb_buttons_similar($t)
	{
		if (!isset($t->vars['_id'])) return;
		$icon = Module::P('similar/img/icon.png');
		return '<a href="{{title}}" id="a-similar"><img src="'.
			$icon.'" alt="icon" /></a>';
	}

	function TagItem($t, $g)
	{
		$items = array();
		foreach ($this->finders as $f)
		{
			$if = new $f;
			$items += $if->FindObject(Server::GetVar('s'));
		}

		$vp = new VarParser();
		return $vp->Concat($g, $items);
	}
}

Module::Register('ModSimilar');

class TasteKid
{
	const api_url = 'http://www.tastekid.com/ask/ws?verbose=1&';

	static function FindObject($query)
	{
		$dat = file_get_contents(TasteKid::api_url.http_build_query(array(
			'q' => $query)));

		$sx = simplexml_load_string($dat);

		$keys = array();
		foreach ($sx->results->resource as $m)
		{
			$i['title'] = (string)$m->name;
			$i['teaser'] = (string)$m->wTeaser;
			$i['wiki'] = (string)$m->wUrl;
			$i['fullname'] = (string)$m->yTitle;
			$i['trailer'] = (string)$m->yUrl;
			$i['id'] = (string)$m->yID;
			$keys[] = $i;
		}

		return $keys;
	}
}

class Glue
{
	const conskey = 'accb344de4052d061cb0f33b2446054e';
	const conssec = 'f244602af7e5e7b1aaa78a75a18d165f';

	const req_url = 'http://api.getglue.com/oauth/request_token';
	const authurl = 'http://getglue.com/oauth/authorize';
	const acc_url = 'http://api.getglue.com/oauth/access_token';
	const api_url = 'http://api.getglue.com/v2';

	static function GetToken()
	{
		global $_d;

		$dat = file_get_contents('http://api.getglue.com/v2/user/login?'
			.http_build_query(array(
				'userId' => $_d['config']['glue']['user'],
				'password' => $_d['config']['glue']['pass']
			))
		);
		$sx = simplexml_load_string($dat);
		$token = $sx->xpath('//response/ping/token');
		$token = (string)$token[0];
		return $token;
	}

	function __construct()
	{
 		$this->oauth = new OAuth(Glue::conskey,Glue::conssec,
			OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
		$this->oauth->enableDebug();
	}

	function Authorize()
	{
		# We've already authorized.
		if (!empty($_SESSION['glue_token'])) return $_SESSION['glue_token'];

		# We just accepted the app and need to save the token.
		if (isset($_SESSION['glue_secret']) && isset($_GET['oauth_token']))
		{
			$this->oauth->setToken($_GET['oauth_token'], $_SESSION['glue_secret']);
			$access_token_info = $oauth->getAccessToken(Glue::acc_url);
			U::VarInfo($_SESSION);
			var_dump($access_token_info);
			$_SESSION['glue_token'] = $access_token_info['oauth_token'];
			$_SESSION['glue_secret'] = $access_token_info['oauth_token_secret'];
		}

		# Apparently no authorization happened.
		return false;
	}

	function GetAuthURL()
	{
		$request_token_info = $this->oauth->getRequestToken(Glue::req_url);
		$_SESSION['glue_secret'] = $request_token_info['oauth_token_secret'];
		$q = http_build_query(array(
			'oauth_token' => $request_token_info['oauth_token'],
			'oauth_callback' => "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}"
		));
		return Glue::authurl."?$q";
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

?>
