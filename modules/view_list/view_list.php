<?php

class ViewList extends Module
{
	public $Name = 'view_list';

	private $_limit = 500;
	private $_page = 0;
	private $_sort = array();
	private $_q = array();

	function __construct()
	{
		$this->CheckActive($this->Name);
		$this->_trans = array('parent' => function ($id) { return !empty($id) ? new MongoID($id) : ''; });
	}

	function Link()
	{
		global $_d;

		$_d['nav.links']['List/Everything'] = '{{app_abs}}/view_list';

		foreach ($_d['entry-types'] as $type => $info)
			$_d['nav.links']["List/{$info['text']}"] = "{{app_abs}}/view_list?q[type]=$type";

		$_d['cb.list.item']['list'] = array(&$this, 'cb_list_item');
	}

	function Prepare()
	{
		if (!$this->Active) return;

		global $_d;

		$this->_q = array();
		if (!empty($_GET['q'])) $this->_q += @$_GET['q'];
		if (!empty($_POST['q'])) $this->_q += $_POST['q'];

		if (@$_d['q'][1] == 'items')
		{
			$this->_page = Server::GetVar('page', 0);
			$this->_sort = Server::GetVar('sort', array());
			die($this->cb_list_item());
		}
	}

	function Get()
	{
		if (!$this->Active) return;

		$t = new Template();
		return $t->ParseFile(Module::L('view_list/view_list.xml'));
	}

	function cb_list_item($t = null)
	{
		global $_d;

		$g = file_get_contents(Module::L('view_list/view_list_item.xml'));

		foreach ($this->_sort as &$v) $v = (int)$v;

		if (!empty($this->_q))
		foreach ($this->_q as $qk => $qv)
			if (isset($this->_trans[$qk]))
				$this->_q[$qk] = call_user_func($this->_trans[$qk], $qv);

		$m = array();

		if (!empty($this->_q))
		{
			$wheres = '';
			$ix = 0;
			foreach ($this->_q as $col => $q)
			{
				if (empty($q)) continue;

				$wheres .= $ix++ > 0 ? ' &&' : '';
				if (is_string($q))
					$wheres .= ' /'.preg_quote($q).'/i.test(this.'.$col.')';
			}
			$m['$where'] = new MongoCode($wheres);
		}

		$cr = $_d['entry.ds']->find($m)
			->sort($this->_sort)
			->skip($this->_page*$this->_limit)
			->limit($this->_limit)
		;

		$res = array();
		foreach ($cr as $i)
		{
			if (!isset($i['index'])) $i['index'] = '';

			$i['icon'] = @$_d['entry-types'][$i['type']]['icon'];

			# We do not have this item.
			if (empty($i['path']))
			{
				# Release date is available.
				if (!empty($i['released']))
					# Has not yet been released, we can't get it.
					if ($i['released']->sec > time()) $i['class'] = 'unavail';
					# Has been released.
					else $i['class'] = 'missing';
				# Should be available.
				else $i['class'] = 'missing';
			}
			else $i['class'] = 'existing';

			if (!empty($i['released']))
			{
				if (is_object($i['released']))
					$i['released'] = date('Y-m-d', $i['released']->sec);
			}
			else $i['released'] = '';

			$i['detail'] = '';

			if (!empty($i['parent']))
				$i['parentobj'] = $_d['entry.ds']->findOne(array(
					'_id' => $i['parent']));

			$i['errors'] = !empty($i['errors']);

			$res[] = $i;
		}

		return VarParser::Concat($g, $res);
	}
}

Module::Register('ViewList');

?>
