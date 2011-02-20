<?php

class ModPlayer extends Module
{
	function Link()
	{
		global $_d;

		$_d['tv.cb.buttons']['player'] = array(&$this, 'cb_buttons_player');
		$_d['movie.cb.buttons']['player'] = array(&$this, 'cb_buttons_player');
		$_d['movie.cb.cover']['player'] = array(&$this, 'cb_buttons_cover');
	}

	function Prepare()
	{
		global $_d;

		if ($_d['q'][0] != 'player') return;

		# Present player selections.
		if (@$_d['q'][1] == 'select')
		{
			$t = new Template($_d);
			$p = Server::GetVar('path');
			$t->Set('path', urlencode($p));

			$f = basename($p);
			$tp = ModPlayer::GetTrans($p);
			$t->Set('tpath', "$tp/$f");

			die($t->ParseFile(Module::L('player/select.xml')));
		}

		# Return a translated path.
		if (@$_d['q'][1] == 'translate')
		{
			$p = Server::GetVar('path');
			$f = basename($p);
			die(ModPlayer::GetTrans($p).'/'.$f);
		}
		if (@$_d['q'][1] == 'js')
		{
			$t = new Template($_d);
			$t->use_getvar = true;
			die($t->ParseFile(Module::L('player/player.js')));
		}
		if (@$_d['q'][1] == 'try_play')
		{
			$p = Server::GetVar('path');
			$f = basename($p);
			$trans = ModPlayer::GetTrans($p)."/$f";
			$url = 'http://'.$_SERVER['REMOTE_ADDR'].':8080/requests/status.xml'
				.'?command=in_play&input='.rawurlencode($trans);
			$xml = @file_get_contents($url);
			if (!empty($xml)) $res = 'success';
			else $res = 'failure';
			die($res);
		}

		$p = Server::GetVar('path');

		if (is_file($p)) $d = dirname($p);
		else $d = $p;
		$f = basename($p);

		# Translate a path to a faster source

		$np = ModPlayer::GetTrans($p);

		# Locate an use any regioning data

		if (file_exists($d.'/.regions.xml'))
		{
			$sx = simplexml_load_file($d.'/.regions.xml');
			foreach ($sx->media as $m)
			{
				foreach ($m->region as $r)
				{
					$reg[(string)$r->attributes()->title] = array(
						(int)$r->attributes()->start,
						(int)$r->attributes()->end
					);
				}
				$regions[(string)$m->attributes()->match] = $reg;
			}
		}

		# Create an M3U File

		$ret = "#EXTM3U\r\n";
		if (is_dir($p))
		{
			foreach (glob($p.'/*.*') as $ix => $xp)
				foreach ($trans as $t)
				{
					$xf = basename($xp);
					$ret .= $this->AddM3U($ix, $np.'/'.$xf,
						@$regions[$xf]);
				}
		}
		else $ret .= $this->AddM3U(1, $np, @$regions[$f]);

		Server::SendDownloadStart(File::GetFile(basename($p)).'.m3u');
		die($ret);
	}

	function Get()
	{
		$ret['head'] = '<script type="text/javascript"
			src="{{app_abs}}/player/js"></script>';
		return $ret;
	}

	/**
	 *
	 * @global array $_d
	 * @param Template $t
	 */
	function cb_buttons_player($t)
	{
		global $_d;

		$p = !empty($t->vars['med_path']) ? $t->vars['med_path'] : $t->vars['fs_path'];

		$icon = Module::P('player/img/play.png');
		return '<a href="player?path='.urlencode($p).'"><img src="'.$icon.'"
			alt="Play Series" /></a>';
	}

	function cb_buttons_cover($t)
	{
		return ' <a href="player?path={{url}}" class="a-play"><img
			src="modules/player/img/play.png" alt="Play" /></a> ';
	}

	function AddM3U($ix, $path, $regs = null)
	{
		$ret = null;

		if (!empty($regs))
		foreach ($regs as $t => $r)
		{
			$opts = "\r\n#EXTVLCOPT:start-time=$r[0]\r\n";
			$opts .= "#EXTVLCOPT:stop-time=$r[1]";
			$ret .= $this->AddM3UFile($ix, $path, File::GetFile(basename($path)).' - '.$t, $opts);
		}
		else $ret = $this->AddM3UFile($ix, $path, File::GetFile(basename($path)));

		return $ret;
	}

	function AddM3UFile($ix, $path, $title, $opts = null)
	{
		$pi = pathinfo($path);
		$p = dirname($path).'/'.rawurlencode($pi['basename']);
		return <<<EOF
#EXTINF:-1,{$title}{$opts}
{$p}


EOF;
	}

	static function GetTrans($p)
	{
		global $_d;

		if (is_file($p)) $d = dirname($p);
		else $d = $p;

		$trans = array($p);
		foreach ($_d['config']['player']['trans'] as $t)
		{
			$c = $t['client'];
			if (preg_match($c, $_SERVER['REMOTE_ADDR']))
				return str_replace($t['source'], $t['target'], $d);
		}
	}
}

Module::Register('ModPlayer');

?>
