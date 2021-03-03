<?php namespace App\Responders;

use GuzzleHttp\Client as GuzzleHttp; 

class GuzzleResponder extends Responder
{
	private $guzzle;

	public function __construct()
	{
		parent::__construct();
		$this->guzzle = new GuzzleHttp();
	}

	public function request($type, $path, $parameters=array())
	{
		return $this->guzzle->request($type, $path, $parameters);
	}
}