<?php namespace App\Services;

use View, App;

class Template {

	private $method ='';
	private $parent ='';
	private $folder ='';
	private $parameters = array();

	public function set_method($method)
	{
		$this->method = str_replace(array('any', 'get', 'post', 'patch', 'put', 'delete'), '', $method);
	}

	public function set_controller($controller)
	{
		$this->controller = $controller;
	}

	public function set_folder($folder)
	{
		$this->folder = $folder;
	}

	public function parameters($parameters)
	{
		$this->parameters = $parameters;
	}

	public function render($method ='', $controller='')
	{
		if( $method =='' ) $method = $this->method;
		if( $controller =='' ) $controller = $this->controller;
		$controller = strtolower($controller); $method=strtolower($method);
		if( $this->folder != '' ) $controller = $this->folder.'/'.$controller;
		if(!view()->exists($controller.'/'.$method)) $controller = (( $this->folder != '' ) ? $this->folder.'/' : '').'defaults';
		if(!view()->exists($controller.'/'.$method)) return App::abort(404, 'View matching '.$controller.' '.$method.' not found');
		return view($controller.'/'.$method, $this->parameters);
	}
}
