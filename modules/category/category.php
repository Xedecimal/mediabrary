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
		$_d['movie.cb.query']['joins'][] = new Join($_d['cat.ds'],
			'cat_movie = med_id', 'LEFT JOIN');
		$cat = GetVar('category');
		if (!empty($cat)) $_d['movie.cb.query']['match']['cat_name'] = $cat;
		$_d['movie.skipfs'] = true;
	}

	function Prepare()
	{
		global $_d;

		$joins = array(new Join($_d['movie.ds'], 'med_id = cat_movie'));
		$cols = array('med_title' => SqlUnquote('DISTINCT cat_name'),
			'med_count' => SqlUnquote('COUNT(med_id)'));

		$cats = $_d['cat.ds']->Get(array('cols' => $cols, 'joins' => $joins,
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

		//if (empty($_d['q'][0])) return parent::Get();
		if ($_d['q'][0] != 'category') return;
	}

	function cb_movie_head()
	{
		$t = new Template();
		$t->ReWrite('category', array(&$this, 'TagCategory'));
		return $t->ParseFile('modules/category/t_category.xml');
	}

	function TagCategory($t, $g)
	{
		global $_d;

		$joins = array(new Join($_d['movie.ds'], 'med_id = cat_movie'));
		$cols = array('cat_name' => SqlUnquote('DISTINCT cat_name'),
			'cat_count' => SqlUnquote('COUNT(med_id)'));

		$cats = $_d['cat.ds']->Get(array('cols' => $cols, 'joins' => $joins,
			'group' => 'cat_name'));

		// Get relative sizes for a tag cloud display
		foreach ($cats as $c) $trel[$c['cat_name']] = $c['cat_count'];
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

Module::RegisterModule('ModCategory');

?>
