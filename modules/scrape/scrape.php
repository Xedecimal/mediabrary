<?php

if (empty($_d['scrape.scrapers'])) $_d['scrape.scrapers'] = array();

class Scrape extends Module
{
	public $Name = 'Scrape';

	function Link()
	{
		global $_d;

		$_d['cb.detail.buttons']['scrape'] = array(&$this, 'cb_detail_buttons');
		$_d['cb.detail.entry']['scrape'] = array(&$this, 'cb_detail_entry');

		# @TODO: Details should work for anything.
		$_d['tv.cb.buttons']['scrape'] = array(&$this, 'cb_detail_buttons');
	}

	function Prepare()
	{
		global $_d;

		$this->CheckActive('scrape');
		if (!$this->Active) return;

		# Collecting a list of possibilities.
		if (@$_d['q'][1] == 'find')
		{
			$t = new Template;
			$t->ReWrite('scraper', array(&$this, 'TagFindScraper'));

			$this->path = $_GET['path'];
			$this->type = $_GET['type'];
			$this->title = Server::GetVar('title', '');

			$t->Set('path', $this->path);
			$t->Set('title', $this->title);
			die($t->ParseFile(Module::L('scrape/find.xml')));
		}

		if (@$_d['q'][1] == 'scrape')
		{
			$ids = Server::GetVar('ids');
			$path = Server::GetVar('path');
			$type = Server::GetVar('type');

			$auto = false;

			# This is automated
			if (empty($ids))
			{
				$auto = true;

				$md = MediaEntry::GetEntryByType($path, $type);

				$scraper = Server::GetVar('scraper');
				if (!empty($scraper)) $ids[$scraper] = null;
				else

				/* @var $s Scraper */
				foreach ($_d['scrape.scrapers'][$type] as $s)
					if ($s->CanAuto()) $ids[$s->Name] = null;
			}

			# Collect generic information
			$q['path'] = $path;
			$item = new MediaEntry($path);
			$ds = $_d['entry.ds']->findOne($q);
			$ds['type'] = $type;
			if (!empty($ds)) $item->Data += $ds;

			# Collect scraper information
			foreach ($ids as $sc => $id)
				$_d['scrape.scrapers'][$type][$sc]->Scrape($item->Data, $id);

			# Save details
			$_d['entry.ds']->save($item->Data, array('safe' => 1));

			# TODO: Save first cover on auto-scrape.
			if (!$auto)
			{
				$item->NoExt = basename($item->Filename, '.'.$item->Ext);
				$dst = VarParser::Parse($_d['config']['paths'][$type]['meta'], $item);
				file_put_contents($dst, file_get_contents(Server::GetVar('cover')));
			}

			die(json_encode($item));
		}

		# Collecting just covers from all known sources.
		if (@$_d['q'][1] == 'covers')
		{
			$id = Server::GetVar('id');
			$type = Server::GetVar('type');

			$me = MediaEntry::FromID($id);

			$covers = array();
			foreach ($_d['scrape.scrapers'][$type] as $s => $ss)
			{
				$covs = $ss->GetCovers($me);
				if (!empty($covs)) $covers += $covs;
			}
			die(json_encode($covers));
		}
	}

	function Get()
	{
		$js = Module::P('scrape/scrape.js');
		$css = Module::P('scrape/scrape.css');
		$r['head'] = <<<EOF
<script type="text/javascript" src="$js"></script>
<link type="text/css" rel="stylesheet" href="$css" />
EOF;

		return $r;
	}

	# Callbacks

	function cb_detail_buttons($t, $a)
	{
		$img = Module::P('img/find.png');
		$ret = '<a href="{{Path}}" id="a-scrape-find"><img src="'.$img.'"
			alt="Find" /></a>';

		if (!empty($t->vars['Data']['details']))
		{
			$ret .= <<<EOF
<a href="{{Data._id}}" class="a-scrape-remove"><img src="img/database_delete.png"
	alt="Remove" /></a><a href="{{Data._id}}" class="a-scrape-covers"><img
	src="modules/movie/img/images.png" alt="Select New Cover" title="Select New Cover" /></a>
EOF;
		}
		return $ret;
	}

	function cb_detail_entry($t, $g, $a)
	{
		global $_d;

		$details = '';
		foreach ($_d['scrape.scrapers'][$a['TYPE']] as $sn)
		{
			$s = new $sn;
			$details .= $s->GetDetails($t, $g, $a);
		}

		return $details;
	}

	# Tags

	function TagFindScraper($t, $g)
	{
		global $_d;

		$t->ReWrite('result', array(&$this, 'TagFindResult'));

		$ret = '';
		foreach ($_d['scrape.scrapers'][$this->type] as $s)
		{
			$this->_scraper = $s;
			$t->Set('Name', $s->Name);
			$t->Set('Link', $s->Link);
			$t->Set('Icon', $s->Icon);
			$ret .= $t->GetString($g);
		}

		return $ret;
	}

	function TagFindResult($t, $g)
	{
		$res = $this->_scraper->Find($this->path, $this->title);
		if (!empty($res)) return VarParser::Concat($g, $res);
	}

	# Static Tooling

	static function Reg($type, $class)
	{
		global $_d;
		$_d['scrape.scrapers'][$type][$class] = new $class;
	}
}

Module::Register('Scrape');

interface Scraper
{
	function GetName();
	function CanAuto();
	function Find($path, $title);
	function GetCovers($item);
	function Details($id);
	function Scrape(&$item, $id = null);
	function GetDetails($t, $g, $a);
}

?>
