<?php namespace App\Responders;

abstract class Responder
{
	protected $path ='';
	protected $type ='';
	protected $parameters = array();

	public function __construct() { }

	public function set_type($type)
	{
		$this->type = $type;
	}

	public function set_path($path)
	{
		$this->path = $path;
	}

	public function set_parameters($parameters)
	{
		$this->parameters = $parameters;
	}

	public function send($path, $parameters) { }
}