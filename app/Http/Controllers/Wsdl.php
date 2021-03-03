<?php namespace App\Http\Controllers;

use Input, SoapServer, File, Response;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\PublicControllers\PublicController;

class Wsdl extends PublicController {

    private $env;

	public function __construct()
	{
		parent::__construct();
        $this->middleware('ipcheck');
        $this->env = config('app.env');
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */

	public function getIndex() {}

	public function anyEssauth()
	{		
		ini_set("soap.wsdl_cache_enabled", 0);

        if(request()->has('xsd') && request()->get('xsd'))
        {
            $content = File::get('xsd/essauth.xsd');
            return Response::make($content, '200')->header('Content-Type', 'text/xml');
        }

        $url = url('api/wsdl/essauth.wsdl');

//        if(File::exists('api/'.$this->env.'/wsdl/essauth.wsdl')) {
//            $url = url('api/'.$this->env.'/wsdl/essauth.wsdl');
//        }

        $options = array('trace'=>1);
        $server = new SoapServer($url, $options);
        $server->setClass('App\Services\Api\EssAuth');
        return $server->handle();
	}

	public function anyEssclient()
	{		
		ini_set("soap.wsdl_cache_enabled", 0);

        if(request()->has('xsd') && request()->get('xsd'))
        {
            $content = File::get('xsd/essclient.xsd');
            return Response::make($content, '200')->header('Content-Type', 'text/xml');
        }

        $url = url('api/wsdl/essclient.wsdl');

//        if(File::exists('api/'.$this->env.'/wsdl/essclient.wsdl')) {
//            $url = url('api/'.$this->env.'/wsdl/essclient.wsdl');
//        }

        $options = array('trace'=>1);
        $server = new SoapServer($url, $options);
        $server->setClass('App\Services\Api\EssClient');
        return $server->handle();
	}

	public function anyEsscontract()
	{
		ini_set("soap.wsdl_cache_enabled", 0);

        if(request()->has('xsd') && request()->get('xsd'))
        {
            $content = File::get('xsd/esscontract.xsd');
            return Response::make($content, '200')->header('Content-Type', 'text/xml');
        }

        $url = url('api/wsdl/esscontract.wsdl');

//        if(File::exists('api/'.$this->env.'/wsdl/esscontract.wsdl')) {
//            $url = url('api/'.$this->env.'/wsdl/esscontract.wsdl');
//        }

        $options = array('trace'=>1);
        $server = new SoapServer($url, $options);
        $server->setClass('App\Services\Api\EssContract');
        return $server->handle();
	}

    public function anyEssuser()
    {       
        ini_set("soap.wsdl_cache_enabled", 0);

        if(request()->has('xsd') && request()->get('xsd'))
        {
            $content = File::get('xsd/essuser.xsd');
            return Response::make($content, '200')->header('Content-Type', 'text/xml');
        }

        $url = url('api/wsdl/essuser.wsdl');

//        if(File::exists('api/'.$this->env.'/wsdl/essuser.wsdl')) {
//            $url = url('api/'.$this->env.'/wsdl/essuser.wsdl');
//        }

        $options = array('trace'=>1);
        $server = new SoapServer($url , $options);
        $server->setClass('App\Services\Api\EssUser');
        return $server->handle();
    }
}
