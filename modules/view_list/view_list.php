<?php

class ViewList extends Module
{
	public $Name = 'view_list';

	function __construct() { $this->CheckActive($this->Name); }

	function Link()
	{
		global $_d;

		$_d['nav.links']['Everything/List'] = 'view_list';
	}

	function Get()
	{
		if (!$this->Active) return;

		$t = new Template();
		$t->Rewrite('item', array(&$this, 'TagItem'));
		return $t->ParseFile(Module::L('view_list/view_list.xml'));
	}

	function TagItem($t, $g)
	{
		global $_d;

		$cr = $_d['entry.ds']->find()->limit(100);
		$cr->sort(array('index' => -1, 'title' => 1));

		$res = array();
		foreach ($cr as $i)
		{
			# We do not have this item.
			if (empty($i['path']))
			{
				# Release date is available.
				if (!empty($i['released']))
					# Has not yet been released, we can't get it.
					if ($i['released']->sec > time())
						$i['class'] = 'unavail';
				# Should be available.
				else $i['class'] = 'missing';
			}
			else $i['class'] = 'existing';

			if (!empty($i['released']))
				if (is_object($i['released']))
					$i['released'] = date('Y-m-d', $i['released']->sec);

			$res[] = $i;
		}

		return VarParser::Concat($g, $res);
	}
}

Module::Register('ViewList');

?>
