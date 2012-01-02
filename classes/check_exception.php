<?php

class CheckException extends Exception
{
	public $msg;

	public function __construct($msg, $code = 0)
	{
		$this->msg = $msg;
	}
}

?>
