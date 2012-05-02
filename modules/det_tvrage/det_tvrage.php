<?php

class TVRage extends Module implements Scraper
{
	public $Name = 'TVRage';

	const _tvrage_key = 'ouF0qPaRHNf7MXPMrQZv';
	const _tvrage_find = 'http://services.tvrage.com/myfeeds/search.php?key=ouF0qPaRHNf7MXPMrQZv&show=';
	const _tvrage_info = 'http://services.tvrage.com/myfeeds/showinfo.php?key=ouF0qPaRHNf7MXPMrQZv&sid=';
	const _tvrage_list = 'http://services.tvrage.com/myfeeds/episode_list.php?key=ouF0qPaRHNf7MXPMrQZv&sid=';

	# Module extension

	function Link()
	{
		global $_d;

		$_d['tv.cb.check.series']['TVRage'] = array(&$this, 'cb_tv_check_series');
	}

	# Scraper implementation

	public $Link = 'http://www.tvrage.com';
	public $Icon = 'modules/det_tvrage/img/tv-rage.png';

	function Find(&$tvse, $title)
	{
		$sid = $this->GetSID($tvse->Path, $title);
	}

	function GetCovers($item) {}

	static function GetSID($path, $title = null)
	{
		$tvse = new TVSeriesEntry($path);

		if (empty($title)) $title = $tvse->Title;

		$url = TVRage::_tvrage_find.rawurlencode($title);
		$xml = file_get_contents($url);
		if (empty($xml)) return null;
		$sx = simplexml_load_string($xml);

		foreach ($sx->show as $s)
		{
			$url = '';
			$item = array(
				'id' => $s->showid,
				'title' => $s->name,
				'date' => $s->started,
				'ref' => $s->link
			);
			$items[] = $item;
		}

		return $items;
	}

	static function GetData($series, $download = false)
	{
		global $_d;

		$infoloc = $series->Path."/.tvrage_cache.json";
		if ($download || !file_exists($infoloc))
		{
			$sids = TVRage::GetSID($series->Title);

			if (empty($sids))
			{
				echo "Could not locate this series {$series->Title}";
				return null;
			}

			$sid = $sids[0]['id'];

			$url = TVRage::_tvrage_list.$sid;
			$out = json_encode(Arr::FromXML(file_get_contents($url)));
			file_put_contents($infoloc, $out);
		}

		return json_decode(file_get_contents($infoloc), true);
	}

	static function GetInfo($series)
	{
		$ret = array();

		$json = TVRage::GetData($series);

		if (empty($json)) die();

		$ret['series'] = trim($json['name']);

		# Single season, make it uniform.
		if ($json['totalseasons'] == 1)
			$json['Episodelist']['Season'] = array($json['Episodelist']['Season']);

		foreach ($json['Episodelist']['Season'] as $s)
		{
			if (empty($s['episode'])) continue;

			if (array_key_exists('epnum', $s['episode']))
				$s['episode'] = array($s['episode']);

			foreach ($s['episode'] as $ep)
			{
				foreach (array_keys($ep) as $k) if (empty($ep[$k])) unset($ep[$k]);

				$sn = $ep['seasonnum'] = (int)$ep['seasonnum'];
				$ep['epnum'] = (int)$ep['epnum'];
				$en = (int)$ep['seasonnum'];
				if (!empty($ep['airdate'])) $ep['airdate'] =
					Database::MyDateTimestamp($ep['airdate']);
				if (!empty($ep['rating'])) $ep['rating'] = (float)$ep['rating'];
				$ep['details']['TVRage'] = $ep;
				$ret['eps'][$sn][$en] = $ep;
			}
		}

		return $ret;
	}

	public function CanAuto() {}
	public function Details($id) {}
	public function GetDetails($t, $g, $a) {}
	public function GetName() {}
	public function Scrape(&$item, $id = null) {}

	# Checking

	function cb_tv_check_series($series)
	{
		$tvreps = TVRage::GetInfo($series);

		if (empty($tvreps)) return;

		$this->CheckSeriesFilename($series, $tvreps);

		foreach ($tvreps['eps'] as $s => $eps)
		{
			foreach ($eps as $e => $ep)
			{
				# No database entry for this episode.
				if (empty($series->ds[$s][$e]))
				{
					$tve = new TVEpisodeEntry(null);
					$tve->Data['details'][$this->Name] = $ep['details'][$this->Name];
					if (!empty($ep['airdate']))
						$tve->Data['released'] = new MongoDate($ep['airdate']);
					if (isset($ep['title'])) $tve->Data['title'] = $ep['title'];
					$tve->Data['type'] = 'tv-episode';
					$tve->Data['series'] = $series->Title;
					$tve->Data['season'] = $s;
					$tve->Data['episode'] = $e;
					$tve->Data['index'] = sprintf('S%02dE%02d', $s, $e);
					$tve->Title = $ep['title'];
					$tve->Data['parent'] = $series->Data['_id'];
					$dbep = $tve->Data;
					$tve->SaveDS(true);
					ModCheck::Out("Added missing episode data for {$series->Title} of $s $e from {$this->Name}.");
				}
				else $dbep = $series->ds[$s][$e];

				# Check series.
				$this->CheckDetails($dbep, $ep);

				# This tv-episode is in the filesystem.
				if (!empty($dbep['path']))
				{
					# Check filename.
					$this->CheckFilename($tvdbeps, $dbep, $ep);
				}
			}
		}
	}

	function CheckSeriesFilename(&$series, &$data)
	{
		$src = dirname($series->Path).'/'.basename(realpath($series->Path));
		$title = MediaLibrary::CleanTitleForFile($data['series']);
		$dst = dirname($series->Path).'/'.$title;
		$url = Module::L('tv/rename?path='.urlencode($src).'&amp;target='.urlencode($dst));
		if ($src != $dst) ModCheck::Out("<a href=\"$url\" class=\"a-fix button\">Fix</a> Series '$src' should be '$dst'");
	}

	function CheckFilename(&$series, $ep, $dvdbep)
	{
		global $_d;

		if (empty($ep['details'][$this->Name])) return;

		$epname = @$ep['details'][$this->Name]['title'];

		if (!empty($epname))
			$epname = MediaLibrary::CleanTitleForFile($epname, false);

		$sn = MediaLibrary::CleanTitleForFile($series['Series']['SeriesName']);

		$preg = '@([^/]+)/('.preg_quote($sn,'@')
			.') - S([0-9]{2,3})E([0-9]{2,3}) - '
			.preg_quote($epname, '@').'\.([^.]+)$@';

		# <series> / <series> - S<season>E<episode> - <title>.avi
		if (!preg_match($preg, $ep['path']))
		{
			$dir = dirname($ep['path']);
			$ext = File::ext($ep['path']);
			$fns = sprintf('%02d', $ep['season']);
			$fne = sprintf('%02d', $ep['episode']);
			$fname = "$sn - S{$fns}E{$fne} - {$epname}";
			$url = $_d['app_abs'].'/tv/rename?path='.urlencode($ep['path']).'&amp;target='.urlencode("$dir/$fname.$ext");
			TV::OutErr("<a href=\"$url\" class=\"a-fix button\">Fix</a> File {$ep['path']} has invalid name, should be \"$dir/$fname.$ext\"", $ep);
			return false;
		}
	}

	function CheckDetails($ep, $tvrep)
	{
		global $_d;

		# No data to reference
		if (!isset($ep['details'][$this->Name]))
		{
			$ep['details'][$this->Name] = $tvrep;
			$_d['entry.ds']->Save($ep);
			#$nep->SaveDS();
			ModCheck::Out("Adding metadata for {$ep['series']} {$ep['season']}x{$ep['episode']}", $ep);
		}

		# Release Date
		if (!empty($ep['details'][$this->Name]['airdate']))
		{
			if (empty($ep['released']))
			{
				$ep['released'] = $ep['details'][$this->Name]['airdate'];
				//$nep->SaveDS();
				ModCheck::Out("TODO: NOT Filled release date for {$ep['series']} {$ep['season']}x{$ep['episode']}");
			}

			# Already aired, missing.
			if (empty($ep['path'])
			&& !empty($ep['season'])
			&& !empty($ep['episode'])
			&& strtotime($ep['details'][$this->Name]['airdate']) < time())
			{
				$title = @$ep['details'][$this->Name]['title'];
				if (empty($title)) $title = "[Yet unknown]";

				ModCheck::Out("Missing episode {$ep['series']}"
					." S{$ep['season']}E{$ep['episode']} {$title}"
					." on {$ep['details'][$this->Name]['airdate']}");
			}
		}
	}
}

Module::Register('TVRage');

Scrape::Reg('tv', 'TVRage');
Scrape::Reg('tv-series', 'TVRage');
Scrape::Reg('tv-episode', 'TVRage');

?>
