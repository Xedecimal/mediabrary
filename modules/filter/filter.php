<?php

require_once('h_main.php');

class ModFilter extends Module
{
	function __construct()
	{
		$this->Filters[] = new FilterReleased();
		$this->CheckActive('filter');
	}

	function Prepare()
	{
		global $_d;

		if (!$this->Active) return;

		if ($_d['q'][1] == 'set')
		{
			$mask = Server::GetVar('mask');
			$_SESSION['filter.mask'] = $mask;
		}

		/*SetVar('date.min', $_d['q'][2]);
		SetVar('date.max', $_d['q'][3]);
		$_d['movie.skipfs'] = true;*/
	}

	function Link()
	{
		global $_d;
		$_d['movie.cb.head'][] = array(&$this, 'cb_movie_head');

		if (!empty($_SESSION['filter.mask']))
			$_d['movie.cb.query']['match']['$or'][] = $_SESSION['filter.mask'];

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
		return $t->ParseFile(Module::L('filter/t.xml'));
	}

	function Get()
	{
		global $_d;

		if (!$this->Active) return;

		if (@$_d['q'][1] == 'content')
		{
			$t = new Template();
			die($t->ParseFile(Module::L('filter/content.xml')));
		}
		else if (@$_d['q'][1] == 'get')
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
		/*else if (@$_d['q'][1] == 'set')
		{
			$type = $_SESSION['filter.type'] = $_d['q'][2];
			if ($type == 'cert')
				$_SESSION['filter.checks'] = Server::GetVar('checks');
			else // Date
			{
				$_SESSION['filter.min'] = isset($_d['q'][3]) ? $_d['q'][3] : 0;
				$_SESSION['filter.max'] = isset($_d['q'][4]) ? $_d['q'][4] : 0;
			}
			die(session_write_close());
		}*/
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
