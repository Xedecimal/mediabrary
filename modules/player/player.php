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
			$m['path'] = $p = Server::GetVar('path');
			$m['encpath'] = rawurlencode($p);
			$m['trans'] = ModPlayer::GetTrans($p);
			#if (is_file($p)) $m['trans'] .= '/'.basename($p);
			$t->Set($m);
			die($t->ParseFile(Module::L('player/select.xml')));
		}

		# Return a translated path.
		if (@$_d['q'][1] == 'translate')
			die(ModPlayer::GetTrans($p));

		if (@$_d['q'][1] == 'js')
		{
			$t = new Template($_d);
			$t->use_getvar = true;
			die($t->ParseFile(Module::L('player/player.js')));
		}

		# Here down will download an M3U file.

		$rp = Server::GetVar('path');

		$ret = "#EXTM3U\r\n";

		# Iterate all the paths we will be playing and add them to the m3u.
		if (is_dir($rp)) $files = glob($rp.'/*');
		else $files[] = $rp;
		foreach ($files as $p)
		{
			if (is_file($p)) $d = dirname($p);
			else $d = $p;
			$f = basename($p);

			# Translate a path to a faster source

			$np = ModPlayer::GetTrans($p);
			
			# Locate an use any regioning data

			$regions = ModPlayer::GetRegions($d);

			# Create an M3U File

			if (is_dir($p))
				foreach (glob($p.'/*.*') as $ix => $xp)
					foreach ($trans as $t)
						$ret .= $this->AddM3U($ix, "$np",
							@$regions[$xf]);
			else $ret .= $this->AddM3U(1, $np, @$regions[$f]);
		}

		Server::SendDownloadStart(File::GetFile(basename($p)).'.m3u');
		#die('<pre>'.$ret.'</pre>');
		die($ret);
	}

	function Get()
	{
		$ret['head'] = '<script type="text/javascript"
			src="{{app_abs}}/player/js"></script>';
		$ret['default'] = '<div id="player-dialog"></div>';
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

		$p = $t->vars['Path'];

		$icon = Module::P('player/img/play.png');
		return '<a class="a-play" href="'.urlencode($p).'">
			<img src="'.$icon.'" alt="Play Series" /></a>';
	}

	function cb_buttons_cover($t)
	{
		return ' <a href="{{RuePath}}" class="a-play"><img
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

	static function GetRegions($p)
	{
		$regions = array();

		if (file_exists($p.'/.regions.xml'))
		{
			$sx = simplexml_load_file($p.'/.regions.xml');
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

		return $regions;
	}

	static function GetTrans($p)
	{
		global $_d;

		foreach ($_d['config']['player']['trans'] as $t)
		{
			if (isset($t['client']))
				if (preg_match($t['client'], $_SERVER['REMOTE_ADDR']))
					return str_replace($t['source'], $t['target'], $p);
			if (isset($t['agent']))
				if (preg_match($t['agent'], $_SERVER['HTTP_USER_AGENT']))
					return str_replace($t['source'], $t['target'], $p);
		}
		
		return $p;
	}
}

Module::Register('ModPlayer');

?>
