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
}

Module::RegisterModule('ModCategory');

?>
