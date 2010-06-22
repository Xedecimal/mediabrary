<?php

require_once('h_main.php');

class ModCategory extends MediaLibrary
{
	function __construct()
	{
		global $_d;
		$_d['cat.ds'] = new DataSet($_d['db'], 'category');
		$this->_template = 'modules/category/t_category.xml';
	}

	function Link()
	{
		global $_d;

		$_d['movie.cb.head'][] = array(&$this, 'cb_movie_head');
		$_d['movie.cb.query']['joins']['category'] = new Join($_d['cat.ds'],
			'cat_movie = med_id', 'LEFT JOIN');

		$cat = GetVar('category');

		if ($cat == 'Remove Filter') unset($_SESSION['category']);
		if ($cat == 'Unscraped')
		{
			$_d['movie.cb.query']['match'] = 1;
			$_d['movie.cb.lqc']['unscraped'] =
				array(&$this, 'cb_movie_lqc');
			$_d['movie.cb.filter']['unscraped'] =
				array(&$this, 'cb_movie_filter');
		}
		else if (!empty($cat) && $cat != 'All')
		{
			$_d['movie.cb.query']['match']['cat_name'] = $cat;
			$_d['movie.skipfs'] = true;
		}
	}

	function Prepare()
	{
		global $_d;

		$joins = array(new Join($_d['movie.ds'], 'med_id = cat_movie'));
		$cols = array('med_title' => SqlUnquote('DISTINCT cat_name'),
			'med_count' => SqlUnquote('COUNT(med_id)'));

		$cats = $_d['cat.ds']->Get(array('columns' => $cols, 'joins' => $joins,
			'group' => 'cat_name'));

		foreach ($cats as $c)
		{
			$this->_items[] = $c['med_title'];
			$this->_metadata[$c['med_title']] = array(
				'med_count' => $c['med_count'],
				'med_title' => $c['med_title'],
				'med_thumb' => "img/category-{$c['med_title']}.jpg"
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
		return $t->ParseFile(l('category/t.xml'));
	}

	function cb_movie_filter($ds_items, $fs_items)
	{
		foreach ($ds_items as $ds)
			unset($fs_items[$ds['med_path']]);
		return $fs_items;
	}

	function cb_movie_lqc($query)
	{
		unset($query['match']);
		return $query;
	}

	function GetItems()
	{
		global $_d;
		$t = new Template($_d);
		$t->ReWrite('category', array(&$this, 'TagCategory'));
		return $t->ParseFile(l('category/item.xml'));
	}

	function TagCategory($t, $g)
	{
		global $_d;

		$query = $_d['movie.cb.query'];

		unset($query['match']['cat_name']);
		$query['columns'] = array('cat_name' => SqlUnquote('DISTINCT cat_name'),
			'cat_count' => SqlUnquote('COUNT(med_id)'));
		$query['group'] = 'cat_name';

		$cats = $_d['movie.ds']->Get($query);

		$cats[] = array('cat_name' => 'All', 'cat_count' => 0);
		$cats[] = array('cat_name' => 'Unscraped', 'cat_count' => 0);
		$cats[] = array('cat_name' => 'Remove Filter', 'cat_count' => 0);

		$curcat = GetVar('category');

		// Get relative sizes for a tag cloud display
		foreach ($cats as $ix => $c)
		{
			if (empty($c['cat_name'])) $c['cat_name'] = $cats[$ix]['cat_name'] = 'Uncategorized';
			if ($cats[$ix]['cat_name'] == $curcat) $cats[$ix]['cat_class'] = 'category current';
			else $cats[$ix]['cat_class'] = 'category';
			$trel[$c['cat_name']] = $c['cat_count'];
		}
		$sizes = get_relative_sizes($trel, 12, 24);

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
