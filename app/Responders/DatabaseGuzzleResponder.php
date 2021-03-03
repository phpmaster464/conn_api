<?php namespace App\Responders;

class DatabaseGuzzleResponder extends GuzzleResponder
{
	private $guzzle;
	private $id;

	public function __construct()
	{
		parent::__construct();
		$this->parameters = ['allow_redirects'=>false, 'auth'=>[config('services.crm.username'), config('services.crm.password')]];
		$this->path = config('services.crm.host');
	}

	public function request($type, $path, $parameters=array())
	{
		$parameters = array_replace($this->parameters, $parameters);
		$path = $this->path.$path;
		$request = parent::request($type, $path, $parameters);
		$this->id = head( $request->getHeader('id'), '') ;
		return $request;
	}

	public function get_id()
	{
		return $this->id;
	}

	public function logout()
	{
		//return parent::request('GET', $this->path.'users/logout');	
	}
}