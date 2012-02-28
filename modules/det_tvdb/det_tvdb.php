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
	function __construct()
	{
		$this->CheckActive($this->Name);
	}

	function Link()
	{
		global $_d;

		$_d['tv.cb.ignore'][] = '/\.tvdb_cache\.json/';
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
				#file_put_contents('temp/'.basename($fn), file_get_contents('http://thetvdb.com/banners/'.$fn));
				$ret['covers'][] = 'http://'.$_SERVER['HTTP_HOST']
					.$_d['app_abs'].'/'.$this->Name.'/cover?p='.$fn;
			}

			die(json_encode($ret));
		}

		# Bypass referrer security on tvdb.
		if (@$_d['q'][1] == 'cover')
		{
			session_write_close();
			header('Content-Type: image/jpeg');
			echo file_get_contents('http://thetvdb.com/banners/'.$_GET['p']);
			die();
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

	public function GetDetails($t, $g, $a) { }

	public function GetName() {

	}

	public function Scrape(&$item, $id = null)
	{
		$dst = $item->Data['path'].'/.tvdb_cache.json';
		$src = self::_tvdb_api."/series/{$id}/all/en.xml";
		$xml = file_get_contents($src);
		$arr = Arr::FromXML($xml);
		if (!@file_put_contents($dst, json_encode($arr)))
			return 'Unable to write tvdb metadata.';
	}

	function Find(&$tvse, $title)
	{
		global $_d;

		$info = array();

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

		$this->CheckSeriesFilename($series, $tvdbeps);

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
					TV::OutErr("Added missing episode data for {$series->Title} of $s $e from TVDB.", $tve);
				}
				else $dbep = $series->ds[$s][$e];

				# Check series.
				$errors += $this->CheckDetails($msgs, $dbep, $ep);

				# This tv-episode is in the filesystem.
				if (!empty($dbep['path']))
				{
					# Check filename.
					$errors += $this->CheckFilename($tvdbeps, $dbep, $ep);
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
			TV::OutErr("Adding metadata for {$ep['path']}", $nep);
		}

		# Release Date
		if (!empty($ep['details'][$this->Name]['FirstAired']))
		{
			if (empty($ep['released']))
			{
				$nep = new TVEpisodeEntry($ep['path']);
				$nep->CollectDS();
				$nep->Data['released'] = $ep['details'][$this->Name]['FirstAired'];
				$nep->SaveDS();
				TV::OutErr("Filled release date for {$ep['path']}", $nep);
			}

			# Already aired, missing.
			if (empty($ep['path'])
			&& !empty($ep['season'])
			&& strtotime($ep['details'][$this->Name]['FirstAired']) < time())
				TV::OutErr("Missing episode {$ep['series']} {$ep['season']}x{$ep['episode']} (Aired: {$ep['details'][$this->Name]['FirstAired']})", $ep);
		}
	}

	function CheckSeriesFilename(&$series, &$data)
	{
		$src = dirname($series->Path).'/'.basename(realpath($series->Path));
		$title = MediaLibrary::CleanTitleForFile($data['Series']['SeriesName']);
		$dst = dirname($series->Path).'/'.$title;
		$url = Module::L('tv/rename?path='.urlencode($src).'&amp;target='.urlencode($dst));
		if ($src != $dst) TV::OutErr("<a href=\"$url\" class=\"a-fix button\">Fix</a> Series '$src' should be '$dst'");
	}

	function CheckFilename(&$series, $ep, $dvdbep)
	{
		if (empty($ep['details'][$this->Name])) return;

		$epname = @$ep['details'][$this->Name]['EpisodeName'];

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
			$url = Module::L('tv/rename?path='.urlencode($ep['path']).'&amp;target='.urlencode("$dir/$fname.$ext"));
			TV::OutErr("<a href=\"$url\" class=\"a-fix button\">Fix</a> File {$ep['path']} has invalid name, should be \"$dir/$fname.$ext\"", $ep);
			return false;
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

		$sc = "$path/.tvdb_cache.json";
		if (file_exists($sc))
		{
			$info = json_decode(file_get_contents($sc), true);
			if (isset($info['Series']['id']))
				return $info['Series']['id'];
		}
		else $info = array();
		var_dump('NO SID!');
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

		$infoloc = "$series/.tvdb_cache.json";
		if ($download || !file_exists($infoloc))
		{
			$url = self::_tvdb_api.'/series/'.$sid.'/all/en.xml';
			$xml = file_get_contents($url);
			$arr = Arr::FromXML($xml);
			if (!empty($arr)) file_put_contents($infoloc, json_encode($arr));
		}

		return json_decode(file_get_contents($infoloc), true);
	}

	static function GetInfo($series)
	{
		$info = TVDB::GetXML($series);

		if (empty($info)) return $info;

		$ret['Series'] = $info['Series'];

		foreach ($info['Episode'] as $ep)
		{
			$sn = (int)$ep['SeasonNumber'];
			$en = (int)$ep['EpisodeNumber'];

			foreach (array_keys($ep) as $k) if (empty($ep[$k])) unset($ep[$k]);

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
