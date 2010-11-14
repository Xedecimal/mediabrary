<?php

class ModScrapeTVRage
{
	const _tvrage_key = 'ouF0qPaRHNf7MXPMrQZv';
	const _tvrage_find = 'http://services.tvrage.com/myfeeds/search.php?key=ouF0qPaRHNf7MXPMrQZv&show=';
	const _tvrage_info = 'http://services.tvrage.com/myfeeds/showinfo.php?key=ouF0qPaRHNf7MXPMrQZv&sid=';
	const _tvrage_list = 'http://services.tvrage.com/myfeeds/episode_list.php?key=ouF0qPaRHNf7MXPMrQZv&sid=';

	function Find($path)
	{
		$sid = ModScrapeTVRage::GetSID($path);
	}

	static function GetSID($path)
	{
		$sc = "$path/.tvrage.season.xml";

		# Overridable title
		$file_title = "$path/.title.txt";
		if (file_exists($file_title)) $realname = file_get_contents($file_title);
		else $realname = basename($path);

		# Cache data
		if (!file_exists($sc))
		{
			$url = ModScrapeTVRage::_tvrage_find.rawurlencode($realname);
			$sx = simplexml_load_string(file_get_contents($url));
			$iurl = ModScrapeTVRage::_tvrage_info.rawurlencode($sx->show->showid);
			file_put_contents($sc, file_get_contents($iurl));
		}

		# Process data
		$sx = simplexml_load_string(file_get_contents($sc));
		return (int)$sx->showid;
	}

	static function GetXML($series, $download = false)
	{
		global $_d;

		$sid = ModScrapeTVRage::GetSID($series);
		if ($sid == -1) {
			echo "Could not locate this series $series";
			return null;
		}

		$infoloc = "$series/.tvrage.series.xml";
		if ($download || !file_exists($infoloc))
		{
			$url = ModScrapeTVRage::_tvrage_list.$sid;
			$out = file_get_contents($url);
			file_put_contents($infoloc, $out);
		}

		return simplexml_load_string(file_get_contents($infoloc));
	}

	static function GetEps($series)
	{
		$ret = array();

		$sx = ModScrapeTVRage::GetXML($series);
		foreach ($sx->xpath('//Season') as $s)
		{
			$sn = (int)$s->attributes()->no;
			foreach ($s->xpath('episode') as $ep)
			{
				$eout['aired'] = MyDateTimestamp($ep->airdate);
				$eout['title'] = $ep->title;
				$en = (int)$ep->seasonnum;
				$ret[$sn][$en] = $eout;
			}
		}

		return $ret;
	}
}

?>
