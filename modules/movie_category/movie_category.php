<?php

require_once('h_main.php');

class ModCategory extends MediaLibrary
{
	function __construct()
	{
		global $_d;
		$_d['cat.ds'] = new DataSet($_d['db'], 'movie_category');
		$this->_template = Module::L('movie_category/t_category.xml');
	}

	function Link()
	{
		global $_d;

		$_d['movie.cb.head'][] = array(&$this, 'cb_movie_head');
		$_d['movie.cb.query']['joins']['category'] = new Join($_d['cat.ds'],
			'cat_movie = mov_id', 'LEFT JOIN');
		$_d['tmdb.cb.scrape']['category'] = array(&$this, 'cb_tmdb_scrape');
		$_d['tmdb.cb.postscrape']['category'] = array(&$this, 'cb_tmdb_postscrape');

		$cat = Server::GetVar('category');

		if ($cat == 'Remove Filter') unset($_SESSION['category']);
		else if ($cat == 'Unscraped')
		{
			$_d['movie.cb.fsquery']['limit'] = array(0, 100);
			$_d['movie.cb.lqc']['unscraped'] =
				array(&$this, 'cb_movie_unscraped_lqc');
			$_d['movie.cb.nolimit'] = true;
			$_d['movie.cb.filter']['unscraped'] =
				array(&$this, 'cb_movie_unscraped_filter');
		}
		else if ($cat == 'All') $_d['movie.cb.query']['match'] = 1;
		else if (!empty($cat))
		{
			$_d['movie.cb.query']['match']['cat_name'] = $cat;
			$_d['movie.skipfs'] = true;
		}
	}

	function Prepare()
	{
		global $_d;

		$joins = array(new Join($_d['movie.ds'], 'mov_id = cat_movie'));
		$cols = array('mov_title' => Database::SqlUnquote('DISTINCT cat_name'),
			'mov_count' => Database::SqlUnquote('COUNT(mov_id)'));

		$cats = $_d['cat.ds']->Get(array('columns' => $cols, 'joins' => $joins,
			'group' => 'cat_name'));

		foreach ($cats as $c)
		{
			$this->_items[] = $c['mov_title'];
			$this->_metadata[$c['mov_title']] = array(
				'mov_count' => $c['mov_count'],
				'mov_title' => $c['mov_title'],
				'med_thumb' => "img/category-{$c['mov_title']}.jpg"
			);
		}
	}

	function Get()
	{
		global $_d;

		if ($_d['q'][0] != 'category') return;
		if ($_d['q'][1] == 'items')
		{
			die($this->GetItems());
		}

		$_SESSION['category'] = $_d['q'][1];
	}

	function cb_movie_head()
	{
		$t = new Template();
		return $t->ParseFile(Module::L('movie_category/t.xml'));
	}

	function cb_movie_unscraped_filter($ds_items, $fs_items)
	{
		foreach ($ds_items as $ds)
			unset($fs_items[$ds['mov_path']]);
		return $fs_items;
	}

	function cb_movie_unscraped_lqc($query)
	{
		unset($query['match']);
		return $query;
	}

	function cb_tmdb_scrape($item, $xml)
	{
		$sx = simplexml_load_string($xml);

		$elcats = $sx->xpath('//movies/movie/categories/category');
		$this->cats = array();
		foreach ($elcats as $e)
			$this->cats[] = (string)$e['name'];
	}

	function cb_tmdb_postscrape($item)
	{
		global $_d;

		foreach ($this->cats as $c)
		{
			$_d['cat.ds']->Add(array(
				'cat_movie' => $item['mov_id'],
				'cat_name' => $c
			), true);
		}
	}

	function GetItems()
	{
		global $_d;
		$t = new Template($_d);
		$t->ReWrite('category', array(&$this, 'TagCategory'));
		return $t->ParseFile(Module::L('movie_category/item.xml'));
	}

	function TagCategory($t, $g)
	{
		global $_d;

		$query = $_d['movie.cb.query'];

		unset($query['match']['cat_name']);
		$query['columns'] = array('cat_name' => Database::SqlUnquote('DISTINCT cat_name'),
			'cat_count' => Database::SqlUnquote('COUNT(mov_id)'));
		$query['group'] = 'cat_name';

		$cats = $_d['movie.ds']->Get($query);

		$cats[] = array('cat_name' => 'All', 'cat_count' => 0);
		$cats[] = array('cat_name' => 'Unscraped', 'cat_count' => 0);
		$cats[] = array('cat_name' => 'Remove Filter', 'cat_count' => 0);

		$curcat = Server::GetVar('category');

		// Get relative sizes for a tag cloud display
		foreach ($cats as $ix => $c)
		{
			if (empty($c['cat_name'])) $c['cat_name'] = $cats[$ix]['cat_name'] = 'Uncategorized';
			if ($cats[$ix]['cat_name'] == $curcat) $cats[$ix]['cat_class'] = 'category current';
			else $cats[$ix]['cat_class'] = 'category';
			$trel[$c['cat_name']] = $c['cat_count'];
		}
		$sizes = Math::RespectiveSize($trel, 12, 24);

		$vp = new VarParser();
		foreach ($cats as $c)
		{
			$c['cat_size'] = $sizes[$c['cat_name']];
			@$ret .= $vp->ParseVars($g, $c);
		}
		return $ret;
	}
}

Module::Register('ModCategory');

?>
