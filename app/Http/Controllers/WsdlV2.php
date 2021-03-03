<?php namespace App\Http\Controllers;

use Input, SoapServer, File, Response;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\PublicControllers\PublicController;

class WsdlV2 extends PublicController {

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
            $content = File::get('v2/xsd/essauth.xsd');
            return Response::make($content, '200')->header('Content-Type', 'text/xml');
        }

        $url = url('api/v2/wsdl/essauth.wsdl');

        $options = array('trace'=>1);
        $server = new SoapServer($url, $options);
        $server->setClass('App\Services\ApiV2\EssAuth');
        return $server->handle();
	}

	public function anyEssclient()
	{		
		ini_set("soap.wsdl_cache_enabled", 0);

        if(request()->has('xsd') && request()->get('xsd'))
        {
            $content = File::get('v2/xsd/essclient.xsd');
            return Response::make($content, '200')->header('Content-Type', 'text/xml');
        }

        $url = url('api/v2/wsdl/essclient.wsdl');

        $options = array('trace'=>1);
        $server = new SoapServer($url, $options);
        $server->setClass('App\Services\ApiV2\EssClient');
        return $server->handle();
	}

	public function anyEsscontract()
	{
		ini_set("soap.wsdl_cache_enabled", 0);

        if(request()->has('xsd') && request()->get('xsd'))
        {
            $content = File::get('v2/xsd/esscontract.xsd');
            return Response::make($content, '200')->header('Content-Type', 'text/xml');
        }

        $url = url('api/v2/wsdl/esscontract.wsdl');

        $options = array('trace'=>1);
        $server = new SoapServer($url, $options);
        $server->setClass('App\Services\ApiV2\EssContract');
        return $server->handle();
	}

    public function anyEssuser()
    {               
        ini_set("soap.wsdl_cache_enabled", 0);

        if(request()->has('xsd') && request()->get('xsd'))
        {
            $content = File::get('v2/xsd/essuser.xsd');
            return Response::make($content, '200')->header('Content-Type', 'text/xml');
        }

        $url = url('api/v2/wsdl/essuser.wsdl');

        $options = array('trace'=>1);
        $server = new SoapServer($url, $options);
        $server->setClass('App\Services\ApiV2\EssUser');
        return $server->handle();
    }
}
