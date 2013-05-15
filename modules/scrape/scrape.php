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

			$this->_item = MediaEntry::FromID($_d['q'][2]);

			$t->Set('path', $this->path);
			$t->Set('title', $this->title);
			die($t->ParseFile(Module::L('scrape/find.xml')));
		}

		if (@$_d['q'][1] == 'scrape')
		{
			$ids = Server::GetVar('ids');
			$id = $_d['q'][2];

			# This is automated
			if (empty($ids))
			{
				$md = MediaEntry::FromID($id);

				$scraper = Server::GetVar('scraper');
				if (!empty($scraper)) $ids[$scraper] = null;
				else

				/* @var $s Scraper */
				foreach ($_d['scrape.scrapers'][$type] as $s)
					if ($s->CanAuto()) $ids[$s->Name] = null;
			}

			# Collect generic information
			$item = MediaEntry::FromID($id);
			if (!empty($ds)) $item->Data += $ds;

			# Collect scraper information
			foreach ($ids as $sc => $id)
				$_d['scrape.scrapers'][$item->Data['type']][$sc]->Scrape($item, $id);

			# Save details
			$_d['entry.ds']->save($item->Data, array('safe' => 1));

			$cover = Server::GetVar('cover');
			if (!empty($cover))
			{
				$item->SaveCover($cover);
				#$item->NoExt = basename($item->Filename, '.'.$item->Ext);
				#$dst = VarParser::Parse($_d['config']['paths'][$type]['meta'], $item);
				#file_put_contents($dst, file_get_contents($cover));
			}

			die(json_encode($item));
		}

		if (@$_d['q'][1] == 'remove')
		{
			$q['_id'] = new MongoId($_d['q'][2]);
			$_d['entry.ds']->remove($q);
			die(json_encode(array('msg' => 'ok')));
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

		if (@$_d['q'][1] == 'cover')
		{
			$dir = dirname($_GET['path']);
			file_put_contents($dir.'/folder.jpg',
				file_get_contents($_GET['cover']));
			die();
		}
	}

	function Get()
	{
		$js = Module::P('scrape/scrape.js');
		$css = Module::P('scrape/scrape.css');
		$r['head'] = '<link type="text/css" rel="stylesheet" href="'.$css.'" />';
		$r['js'] = '<script type="text/javascript" src="'.$js.'"></script>';
		return $r;
	}

	# Callbacks

	function cb_detail_buttons($t, $a)
	{
		$img = Module::P('img/find.png');
		$ret = '<a href="{{Data._id}}" id="a-scrape-find"><img src="'.$img.'"
			alt="Find" /></a>';

		if (!empty($t->vars['Data']['details']))
		{
			$img_d = Module::P('img/database_delete.png');
			$img_c = Module::P('movie/img/images.png');
			$ret .= <<<EOF
<a href="{{Data._id}}" class="a-scrape-remove"><img src="$img_d"
	alt="Remove" /></a><a href="{{Data._id}}" class="a-scrape-covers"><img
	src="$img_c" alt="Select New Cover" title="Select New Cover" /></a>
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
			$t->Set('Icon', Module::P($s->Icon));
			$ret .= $t->GetString($g);
		}

		return $ret;
	}

	function TagFindResult($t, $g)
	{
		$res = $this->_scraper->Find($this->_item, $this->title);
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
	function CanAuto();
	function Find(&$me, $title);
	function GetCovers($item);
	function Details($id);
	function Scrape(&$item, $id = null);
	function GetDetails($t, $g, $a);
}

?>
