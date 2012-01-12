<?php

class CheckException extends Exception
{
	public $msg;
	public $source;

	public function __construct($msg, $code = null, $source = null)
	{
		$this->msg = $msg;
		$this->source = $source;
	}
}

?>
