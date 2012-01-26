<?php

class TVDB extends Module implements Scraper
{
	public $Name = 'TVDB';

	const _tvdb_key = '138419DAB0A9141D';
	const _tvdb_find = 'http://www.thetvdb.com/api/GetSeries.php?seriesname=';
	const _tvdb_api = 'http://www.thetvdb.com/api/138419DAB0A9141D/';

	# http://www.thetvdb.com/api/138419DAB0A9141D/mirrors.xml
	# http://www.thetvdb.com/api/138419DAB0A9141D/series/75897/banners.xml

	# Module extension

	function Link()
	{
		global $_d;

		$_d['tv.cb.check.series'][$this->Name] = array(&$this, 'cb_tv_check_series');
	}

	function Prepare()
	{
		global $_d;

		if (!$this->Active) return;

		if (@$_d['q'][1] == 'covers')
		{
			$id = Server::GetVar('id');

			# Collect Information
			$xml = self::GetRemoteBanners($id);
			$sx = simplexml_load_string($xml);
			$xp = $sx->xpath("//Banners/Banner[BannerType='poster']");

			# Process Information
			$ret = array('id' => $this->Name);
			foreach ($xp as $ban)
			{
				$fn = (string)$ban->BannerPath;
				file_put_contents('temp/'.basename($fn), file_get_contents('http://thetvdb.com/banners/'.$fn));
				$ret['covers'][] = 'temp/'.basename($fn);
			}

			die(json_encode($ret));
		}
	}

	# Scraper implementation

	public $Link = 'http://www.thetvdb.com';
	public $Icon = 'modules/det_tvdb/img/tvdb.png';

	public function CanAuto() {

	}

	public function Details($id)
	{
		global $_d;

		if ($id == -1) {
			echo "Could not locate this series $series";
			return null;
		}

		$za = TVDB::GetRemoteZip($id);

		$ret['detail'] = $za->getFromName('en.xml');
		$ret['mirrors'] = $za->getFromName('mirrors.xml');
		$ret['banners'] = $za->getFromName('banners.xml');
		$za->close();

		return $ret;

		/*if (empty($info['Episode'])) return array();
		foreach ($info['Episode'] as $ep)
		{
			$sn = (int)$ep['SeasonNumber'];
			$en = (int)$ep['EpisodeNumber'];

			$item['details']['TVDB'] = $ep;

			if (!empty($ep['FirstAired']))
				$item['aired'] = Database::MyDateTimestamp($ep['FirstAired']);
			if (!empty($ep['EpisodeName']))
				$item['title'] = MediaLibrary::CleanString($ep['EpisodeName']);

			# Build TVDB url
			$eid = $ep['id'];
			$snid = $ep['seasonid'];
			$srid = $ep['seriesid'];
			$item['links']['TVDB'] = "http://thetvdb.com/index.php?tab=episode&amp;seriesid=$srid&amp;seasonid=$snid&amp;id=$eid";

			$ret['eps'][$sn][$en] = $item;
		}
		return $ret;*/
	}

	public function GetDetails($t, $g, $a) {
	}

	public function GetName() {

	}

	public function Scrape(&$item, $id = null)
	{
		$dst = $item['path'].'/.info.xml';
		$src = self::_tvdb_api."/series/{$id}/all/en.xml";
		if (!@file_put_contents($dst, file_get_contents($src)))
			return 'Unable to write tvdb metadata.';
	}

	function Find(&$tvse, $title)
	{
		global $_d;

		$info = array();

		if (is_file($tvse->Path)) $p = dirname($path);
		else $p = $path;

		# Manually specified title.
		if (empty($title)) $title = $tvse->Title;

		$url = TVDB::_tvdb_find.rawurlencode($title);
		$ctx = stream_context_create(array('http' => array('timeout' => 5)));
		$sx = @simplexml_load_string(file_get_contents($url, false, $ctx));
		$seriess = $sx->xpath('//Data/Series');

		$items = array();
		foreach ($seriess as $series)
		{
			$url = '';
			$item = array(
				'id' => $series->id,
				'title' => $series->SeriesName,
				'date' => $series->FirstAired,
				'covers' => 'http://thetvdb.com/banners/'.$series->banner,
				'ref' => 'http://thetvdb.com/?tab=series&id='.$series->id
			);
			$items[] = $item;
		}

		return $items;
	}

	function GetCovers($item) {}

	# Callbacks

	function cb_tv_check_series(&$series)
	{
		global $_d;

		$errors = 0;

		$tvdbeps = TVDB::GetInfo($series->Path);

		if (!empty($tvdbeps['eps']))
		foreach ($tvdbeps['eps'] as $s => $eps)
		{
			foreach ($eps as $e => $ep)
			{
				# No record of this entry, let us make it.
				if (empty($series->ds[$s][$e]))
				{
					$tve = new TVEpisodeEntry(null);
					$tve->Data['details'][$this->Name] = $ep['details'][$this->Name];
					if (!empty($ep['aired']))
						$tve->Data['released'] = new MongoDate($ep['aired']);
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
					echo "Added missing episode data for {$series->Title} of $s $e from TVDB.";
					flush();
				}
				else $dbep = $series->ds[$s][$e];

				# Check series.
				$errors += $this->CheckDetails($msgs, $dbep, $ep);

				# This entry is in the filesystem.
				if (!empty($dbep['path']))
				{
					# Check filename.
					$errors += $this->CheckFilename($msgs, $dbep, $ep);
				}
			}
		}

		return $errors;
	}

	function CheckDetails(&$msgs, $ep, $tvdbep)
	{
		# No TVDB data to reference
		if (!isset($ep['details'][$this->Name]))
		{
			$nep = new TVEpisodeEntry($ep['path']);
			$nep->CollectDS();
			$nep->Data['details'][$this->Name] = $tvdbep['details'][$this->Name];
			$nep->SaveDS();
			$msgs['TVDB'][] = "Adding metadata for {$ep['path']}";
		}

		if (!empty($ep['details'][$this->Name]['FirstAired']))
		{
			if (empty($ep['released']))
			{
				$nep = new TVEpisodeEntry($ep['path']);
				$nep->CollectDS();
				$nep->Data['released'] = $ep['details'][$this->Name]['FirstAired'];
				$nep->SaveDS();
				$msgs['TVDB'][] = "Filled release date for {$ep['path']}";
			}

			# Already aired, missing.
			if (empty($ep['path'])
				&& !empty($ep['season'])
				&& strtotime($ep['details'][$this->Name]['FirstAired']) < time())
				$msgs['TVDB'][] = "Missing episode {$ep['series']} {$ep['season']}x{$ep['episode']}";
		}
	}

	function CheckFilename(&$msgs, $ep, $dvdbep)
	{
		if (empty($ep['details'][$this->Name])) return;

		$epname = @$ep['details'][$this->Name]['EpisodeName'];

		if (!empty($epname))
			$epname = MediaLibrary::CleanTitleForFile($epname, false);
		if (!empty($eps['series']))
			$eps['series'] = MediaLibrary::CleanTitleForFile($eps['series'], false);

		$preg = "@([^/]+)/({$ep['series']}) - S([0-9]{2,3})E([0-9]{2,3}) - "
			.preg_quote($epname).'\.([^.]+)$@';

		# <series> / <series> - S<season>E<episode> - <title>.avi
		if (!preg_match($preg, $ep['path']))
		{
			$dir = dirname($ep['path']);
			$ext = File::ext($ep['path']);
			$fns = sprintf('%02d', $ep['season']);
			$fne = sprintf('%02d', $ep['episode']);
			$fname = "{$ep['series']} - S{$fns}E{$fne} - {$epname}";
			$url = Module::L('tv/rename?path='.urlencode($ep['path']).'&amp;target='.urlencode("$dir/$fname.$ext"));
			$msgs['Filename Compliance'][] = "<a href=\"$url\" class=\"a-fix\">Fix</a> File {$ep['path']} has invalid name, should be \"$dir/$fname.$ext\"";
			return 1;
		}
	}

	# Statics

	static function GetRemoteBanners($id)
	{
		return file_get_contents(self::_tvdb_api.'/series/'.$id.'/banners.xml');
	}

	static function GetSID($path)
	{
		global $_d;

		$sc = "$path/.info.dat";
		if (file_exists($sc))
		{
			$info = unserialize(file_get_contents($sc));
			if (isset($info['sid'])) return $info['sid'];
		}
		else $info = array();
		$file_title = "$path/.title.txt";
		if (file_exists($file_title)) $realname = file_get_contents($file_title);
		else $realname = basename($path);
		$url = TVDB::_tvdb_find.rawurlencode($realname);
		$sx = simplexml_load_string(file_get_contents($url));
		$sids = $sx->xpath('//Data/Series/seriesid');
		if (empty($sids)) return -1;
		$sid = (int)$sids[0];
		if (empty($sid)) return -1;
		$info['sid'] = $sid;
		file_put_contents($path.'/.info.dat', serialize($info));
		return $sid;
	}

	static function GetXML($series, $download = false)
	{
		global $_d;

		$sid = TVDB::GetSID($series);
		if ($sid == -1) {
			echo "Could not locate this series $series";
			return null;
		}

		$infoloc = "$series/.tvdb.xml";
		if ($download || !file_exists($infoloc))
		{
			$url = 'http://www.thetvdb.com/api/138419DAB0A9141D/series/'
				.$sid.'/all/en.xml';
			file_put_contents($infoloc, file_get_contents($url));
		}
		$xml = file_get_contents($infoloc);
		return Arr::FromXML($xml);
	}

	static function GetInfo($series)
	{
		$info = TVDB::GetXML($series);
		if (empty($info['Episode'])) return array();
		foreach ($info['Episode'] as $ep)
		{
			$sn = (int)$ep['SeasonNumber'];
			$en = (int)$ep['EpisodeNumber'];

			foreach (array_keys($ep) as $k)
				if (empty($ep[$k])) unset($ep[$k]);

			$item['details']['TVDB'] = $ep;

			if (!empty($ep['FirstAired']))
				$item['aired'] = Database::MyDateTimestamp($ep['FirstAired']);
			if (!empty($ep['EpisodeName']))
				$item['title'] = MediaLibrary::CleanString($ep['EpisodeName']);

			# Build TVDB url
			$eid = $ep['id'];
			$snid = $ep['seasonid'];
			$srid = $ep['seriesid'];
			$item['links']['TVDB'] = "http://thetvdb.com/index.php?tab=episode&amp;seriesid=$srid&amp;seasonid=$snid&amp;id=$eid";

			$ret['eps'][$sn][$en] = $item;
		}
		return $ret;
	}
}

Module::Register('TVDB');

Scrape::Reg('tv', 'TVDB');
Scrape::Reg('tv-series', 'TVDB');
Scrape::Reg('tv-episode', 'TVDB');

?>
