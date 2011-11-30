<?php

class Incoming extends Module
{
	function Check(&$msgs)
	{
		global $_d;

		$ratios = array(
			'TVEpisodeEntry' => 6,
			'MovieEntry' => 4
		);

		$files = File::Comb($_d['config']['paths']['incoming'],
			'/\.txt$|\.r[ra0-9]{2}$|\.sfv$|\.srt$|\.nfo$|\.torrent$|\.ico$|\.png$|\.sub$|\.idx$|\.diz$|sample|\.jpg$|\.url$/i', SCAN_FILES);

		foreach ($files as $f)
		{
			$found = null;
			$nf = null;

			foreach ($ratios as $c => $r)
			{
				$dat = MediaEntry::ScrapeFS($f, $c::GetFSPregs());
				if (isset($dat['debug_matched']) && $dat['debug_matched'] <= $r)
				{
					$nf = $dat;
					$found = $dat;
					$found['type'] = $c;
					break;
				}
			}

			if (!empty($found))
			{
				$target = $this->GetTarget($found);
				$urlfix = "movie/rename?path=".urlencode($f);
				$urlfix .= '&amp;target='.urlencode($target).'/'.urlencode(basename($f));
				$msgs['Incoming'][] = "[<a href=\"$urlfix\" class=\"a-fix\">Fix</a>] Matched {$f} to a {$c} want to place it at '{$target}'.";
			}
			else
			{
				//var_dump("Could not identify {$f}");
			}
		}
	}

	function GetTarget($found)
	{
		global $_d;

		if ($found['type'] == 'TVEpisodeEntry')
		{
			foreach ($_d['config']['paths']['tv-series']['paths'] as $p)
			{
				$dirs = File::Comb($p, null, SCAN_DIRS);
				$sims = array();
				foreach ($dirs as $dir)
				{
					similar_text($found['series'], basename($dir), $perc);
					$sims[$dir] = $perc;
				}
				arsort($sims);
				$ret = array_keys($sims);
				return $ret[0];
			}
		}

		if ($found['type'] == 'MovieEntry')
		{
			return $_d['config']['paths']['movie']['paths'][0].'/'.$found['Title'].'('.$found['Released'].')';
		}
	}
}

Module::Register('Incoming');

?>
