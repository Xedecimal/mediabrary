<?php

class TVDB extends Module implements Scraper
{
	public $Name = 'TVDB';

	const _tvdb_key = '138419DAB0A9141D';
	const _tvdb_find = 'http://www.thetvdb.com/api/GetSeries.php?seriesname=';

	//http://www.thetvdb.com/api/138419DAB0A9141D/series/75897/all/en.zip

	# Module extension

	function Link()
	{
		global $_d;

		$_d['tv.cb.check.series'][$this->Name] = array(&$this, 'cb_tv_check_series');
	}

	# Scraper implementation

	public function CanAuto() {

	}

	public function Details($id) {

	}

	public function GetDetails($t, $g, $a) {

	}

	public function GetName() {

	}

	public function Scrape($item, $id = null) {	}

	function Find($path, $full = false)
	{
		global $_d;

		$sid = TVDB::GetSID($path);
		$key = TVDB::_tvdb_key;

		$ret = '';
		$dst = $path.'/.info.zip';
		$src = "http://www.thetvdb.com/api/{$key}/series/{$sid}/all/en.zip";
		if (!@file_put_contents($dst, file_get_contents($src)))
			return 'Unable to write tvdb metadata.';

		$za = new ZipArchive;
		$za->open($dst);
		$xml = $za->getFromName('banners.xml');
		$za->close();

		$sx = simplexml_load_string($xml);
		list($ban) = $sx->xpath("//Banners/Banner[BannerType='series']/BannerPath");
		$pi = pathinfo($ban);
		$series = basename($path);
		File::MakeFullDir($_d['config']['paths']['tv']['meta']);
		file_put_contents($_d['config']['paths']['tv']['meta']."/thm_$series",
			file_get_contents("http://www.thetvdb.com/banners/{$ban}"));

		die($ret."Grabbed");
	}

	# Callbacks

	function cb_tv_check_series($series, &$msgs)
	{
		global $_d;

		$errors = 0;

		$se = new TVSeriesEntry($series);

		$creps = $_d['entry.ds']->find(array(
			'type' => 'tv-episode',
			'parent' => $se->Title
		));

		foreach ($creps as $ep)
			$dbeps[$ep['season']][$ep['episode']] = $ep;

		$tvdbeps = TVDB::GetInfo($series);

		foreach ($tvdbeps['eps'] as $s => $eps)
		{
			foreach ($eps as $e => $ep)
			{
				$dbep = @$dbeps[$s][$e];

				# This entry is in the filesystem.
				if (!empty($dbep['path']))
				{
					# Check series.
					$errors += $this->CheckDetails($msgs, $dbep);

					# Check filename.
					$errors += $this->CheckFilename($msgs, $dbep);
				}

				# TVDB is already appended to this.
				if (isset($dbeps[$s][$e]['details'][$this->Name])) continue;

				$tve = new TVEpisodeEntry(null);

				$msgs['TVDB/Metadata'][] = "Adding missing database entry for $series of $s $e.";

				if (!empty($dbeps[$s][$e])) $tve->Data = $dbeps[$s][$e];

				$tve->Data['details'][$this->Name] = $ep;

				if (!empty($ep['aired']))
				{
					$tve->Data['released'] = new MongoDate($ep['aired']);
				}

				if (!empty($ep['title'])) $tve->Data['title'] = $ep['title'];
				if (empty($tve->Data['path'])) $tve->Data['path'] = '';
				$tve->Data['type'] = 'tv-episode';
				$tve->Data['series'] = $se->Title;
				$tve->Data['season'] = $s;
				$tve->Data['episode'] = $e;
				$tve->Data['parent'] = $se->Title;
				$tve->Data['index'] = "S{$s}E{$e}";
				$tve->Title = @$ep['title'];
				$tve->Parent = $se->Title;

				$tve->save_to_db();
			}
		}

		return $errors;
	}

	function CheckDetails(&$msgs, $ep)
	{
		# No TVDB data to reference
		if (!isset($ep['details'][$this->Name])) return;

		if ($ep['title'] != $ep['details'][$this->Name]['title'])
		{
			var_dump("Title mismatch: {$ep['title']} to {$ep['details'][$this->Name]['title']}");
		}
	}

	function CheckFilename(&$msgs, $ep)
	{
		$epname = '';
		if (!empty($ep['title']))
			$epname = $ep['title'];
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

		$infoloc = "$series/.info.zip";
		if ($download || !file_exists($infoloc))
		{
			$url = 'http://www.thetvdb.com/api/138419DAB0A9141D/series/'
				.$sid.'/all/en.zip';
			file_put_contents($infoloc, file_get_contents($url));
		}
		$za = new ZipArchive;
		$za->open($infoloc);
		$xml = $za->getFromName('en.xml');
		$za->close();
		return Arr::FromXML($xml);
	}

	static function GetInfo($series)
	{
		$info = TVDB::GetXML($series);
		if (empty($info)) return array();
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
		return $ret;
	}
}

Module::Register('TVDB');
Scrape::Reg('tv', 'TVDB');

?>
