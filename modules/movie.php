<?php

require_once('medialibrary.php');

class ModMovie extends MediaLibrary
{
	function __construct()
	{
		global $_d;
		$_d['movie.ds'] = new DataSet($_d['db'], 'movie');

		$this->_class = 'movie';
		$this->_fs_scrapes = array(
			'#([^/\(]+)\s*\((\d+)\)\.(\S+)$#' => array(
				1 => 'med_title',
				2 => 'med_date',
				3 => 'med_ext'),
			'#([^.]+)\.(\S+)$#' => array(
				1 => 'med_title',
				2 => 'med_ext'
			)
		);
	}

	function Get()
	{
		global $_d;

		//$t = new Template();

		if (@$_d['q'][0] != 'movie') return;

		if (@$_d['q'][1] == 'play')
		{
			$file = filenoext($_d['q'][2]);

			$url = $_d['config']['movie_url'].'/'.rawurlencode($_d['q'][2]);
			$data = <<<EOF
#EXTINF:-1,{$file}
{$url}
EOF;

			SendDownloadStart("{$file}.m3u");
			die($data);
		}
		else if (@$_d['q'][1] == 'detail')
		{
			$t = new Template();
			$m = array('med_id' => $_d['q'][2]);
			$t->Set($mov = $_d['movie.ds']->GetOne(array('match' => $m)));
			die($t->ParseFile('t_movie_detail.xml'));
		}
		else if (@$_d['q'][1] == 'search')
		{
			$m = $_d['movie.ds']->GetOne(array('match' => array(
				'med_path' => $_POST['target'])));
			if (empty($m)) $m = $this->ScrapeFS($_POST['target']);
			die(ModScrapeTMDB::Find($m));
		}
		else if (@$_d['q'][1] == 'scrape')
		{
			$item = $_d['movie.ds']->GetOne(array(
				'match' => array('med_path' => $_POST['target']),
				'args' => GET_ASSOC
			));

			// No database entry, we need some fresh data.
			if (empty($item))
				$item = $this->ScrapeFS($_POST['target']);

			$item = ModScrapeTMDB::Scrape($item, $_POST['tmdb_id']);

			unset($item['med_thumb']);
			$_d['movie.ds']->Add($item, true);

			$p = $item['med_path'];
			$this->_items[0] = $p;
			$this->_metadata[$p] = array_merge($item, $this->ScrapeFS($p));
			$t = new Template();
			$t->ReWrite('item', array($this, 'TagItem'));
			die($t->ParseFile('t_movie.xml'));
		}
		else
		{
			// Load up and present ourselves fully.

			$this->_template = 't_movie.xml';

			$this->_items = glob($_d['config']['movie_path'].'/*');
			$this->_metadata = DataToArray($_d['movie.ds']->Get(), 'med_path');
			
			foreach ($this->_items as $i) $this->ScrapeFS($i);

			return parent::Get();
		}
	}

	function Check()
	{
		return array('StrictNames' => 'File X has an incorrect name');
	}
}

Module::RegisterModule('ModMovie');

?>
