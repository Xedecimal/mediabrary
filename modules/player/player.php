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

		$p = GetVar('path');

		if (is_file($p))
			$d = dirname($p);
		else $d = $p;
		$f = basename($p);

		# Translate a path to a faster source

		$trans = array($p);
		foreach ($_d['config']->player->trans as $t)
		{
			$c = $t->attributes()->client;
			if (preg_match($c, $_SERVER['REMOTE_ADDR']))
			{
				$np = str_replace($t->attributes()->source,
					$t->attributes()->target, $d);
				break;
			}
		}

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
		else $ret .= $this->AddM3U(1, $np.'/'.$f, @$regions[$f]);

		SendDownloadStart(filenoext(basename($p)).'.m3u');
		die($ret);
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

		return '<a href="player?path='.urlencode($p).'"><img src="img/play.png"
			alt="Play Series" /></a>';
	}

	function cb_buttons_cover($t)
	{
		return '<a href="player?path={{url}}" class="ui-icon ui-icon-play"></a>';
	}

	function AddM3U($ix, $path, $regs = null)
	{
		$ret = null;

		if (!empty($regs))
		foreach ($regs as $t => $r)
		{
			$opts = "\r\n#EXTVLCOPT:start-time=$r[0]\r\n";
			$opts .= "#EXTVLCOPT:stop-time=$r[1]";
			$ret .= $this->AddM3UFile($ix, $path, filenoext(basename($path)).' - '.$t, $opts);
		}
		else $ret = $this->AddM3UFile($ix, $path, filenoext(basename($path)));

		return $ret;
	}

	function AddM3UFile($ix, $path, $title, $opts = null)
	{
		return <<<EOF
#EXTINF:-1,{$title}{$opts}
{$path}


EOF;
	}

}

Module::Register('ModPlayer');

?>
