<?php

class ModFilter extends Module
{
	public $Name = 'filter';

	function __construct()
	{
		$this->Filters[] = new FilterReleased();
		$this->CheckActive($this->Name);
	}

	function Prepare()
	{
		global $_d;

		if (!$this->Active) return;

		if (@$_d['q'][1] == 'set')
		{
			$mask = json_decode(Server::GetVar('mask'), true);
			if ($mask == null) unset($_SESSION['filter.mask']);
			else
			{
				$fm = @$_SESSION['filter.mask'];
				$_SESSION['filter.mask'] = is_array($fm)
					? array_merge_recursive($fm, $mask) : $mask;
				var_dump($_SESSION['filter.mask']);
			}
			session_write_close();
			die(json_encode($_SESSION['filter.mask']));
		}

		if (@$_d['q'][1] == 'unset')
		{
			$target = json_decode(Server::GetVar('mask'), true);
			foreach (array_keys($target) as $key)
				unset($_SESSION['filter.mask'][$key]);
			session_write_close();
			die(json_encode($_SESSION['filter.mask']));
		}

		if (@$_d['q'][1] == 'get')
		{
			if (empty($_SESSION['filter.mask']))
				$_SESSION['filter.mask'] = array();
			die(json_encode($_SESSION['filter.mask']));
		}
	}

	function Link()
	{
		global $_d;
		$_d['movie.cb.head'][] = array(&$this, 'cb_movie_head');

		if (!empty($_SESSION['filter.mask']))
			$_d['movie.cb.query']['match'] = array_merge($_d['movie.cb.query']['match'], $_SESSION['filter.mask']);

		if (!$this->Active) return;

		$type = Server::GetVar('filter.type');

		if ($type == 'cert')
		{
			$checks = Server::GetVar('filter.checks');
			if (!empty($checks))
				$_d['movie.cb.query']['match']['md_value'] =
					Database::SqlIn($checks);
		}

		$min = Server::GetVar('filter.min');
		$max = Server::GetVar('filter.max');

		if (!empty($min) && !@$_d['movie.exclusive'])
		{
			if ($type == 'rating')
			{
				$_d['movie.cb.query']['joins']['mf'] =
					new Join($_d['movie_float.ds'], "mf_name = 'rating' AND mf_movie = mov_id", 'LEFT JOIN');
				$_d['movie.cb.query']['match']['mf_value'] =
					Database::SqlBetween($min, $max);
				$_d['movie.cb.query']['order'] = array('mf_value' => 'DESC');
			}
			else $_d['movie.cb.query']['match'][$type] =
				array('$gte' => $min, '$lte' => (string)((int)$max+1));
		}
	}

	function cb_movie_head()
	{
		$t = new Template();
		$t->Rewrite('filter', array(&$this, 'TagFilter'));
		return $t->ParseFile(Module::L('filter/t.xml'));
	}

	function Get()
	{
		global $_d;

		$r['head'] = '<link type="text/css" rel="stylesheet"
			href="'.Module::P('filter/filter.css').'" />';

		if (!$this->Active) return $r;

		if (@$_d['q'][1] == 'get')
		{
			$type = Server::GetVar('filter.type', 'date');

			if ($type == 'date')
			{
				$d = $_d['entry.ds']->find(array(), array('date' => 1))->sort(array('date' => 1))->limit(1)->getNext();
				$item['min'] = date('Y', strtotime($d['date']));
				$d = $_d['entry.ds']->find(array(), array('date' => 1))->sort(array('date' => -1))->limit(1)->getNext();
				$item['max'] = date('Y', strtotime($d['date']));
			}
			else if ($type == 'obtained')
			{
				$min = $_d['entry.ds']->find(array(), array('details.obtained' => 1))->sort(array('details.obtained' => 1))->getNext();
				$items[0]['min'] = date('Y', strtotime($min['details']['obtained']));
				$max = $_d['entry.ds']->find(array(), array('details.obtained' => 1))->sort(array('details.obtained' => -1))->getNext();
				$items[0]['max'] = date('Y', strtotime($max['details']['obtained']));
			}
			else if ($type == 'rating')
			{
				$q['columns'] = array(
					'min' => Database::SqlUnquote('min(CAST(md_value as DECIMAL))'),
					'max' => Database::SqlUnquote('max(CAST(md_value as DECIMAL))')
				);
				$q['match']['md_name'] = 'rating';

				$items = $_d['movie_detail.ds']->Get($q);
			}
			else if ($type == 'cert')
			{
				$items[0] = array(
					'source' => $type,
					'checks' => Server::GetVar('filter.checks', array())
				);
				die(json_encode($items[0]));
			}
			else
			{
				$item = array('min' => 0, 'max' => 1);
			}
			$item['cmin'] = Server::GetVar('filter.min', $item['min']);
			$item['cmax'] = Server::GetVar('filter.max', $item['max']);
			$item['source'] = $type;
			die(json_encode($item));
		}
	}

	function TagFilter($t, $g)
	{
		global $_d;

		$filters = U::RunCallbacks($_d['filter.cb.filters']);
		foreach ($filters as $n => &$v)
		{
			if (is_string($v)) $v = array('href' => $v, 'class' => 'a-filter');
			if (is_array($v) && isset($v['class']))
				$v['class'] .= ' a-filter';
			$v['class'] = 'a-filter';
		}
		return ModNav::GetLinks(ModNav::LinkTree($filters), 'filter');
		return VarParser::Concat($g, $filters);
	}
}

class Filter
{
	static function Register($filter)
	{
		$_d['filter.filters'][] = $filter;
	}
}

class FilterReleased
{
	function __construct()
	{
		$this->_text = 'Released';
		$this->_col = 'YEAR(mov_date)';
		$this->_match = Database::SqlBetween(Server::GetVar('filter.min'),
			Server::GetVar('filter.max'));
	}
}

Filter::Register(new FilterReleased);

Module::Register('ModFilter');

?>
