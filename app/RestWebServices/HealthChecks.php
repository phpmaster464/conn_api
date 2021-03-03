<?php namespace App\RestWebServices;

class HealthChecks extends MyRestClient
{
	private $uuid;
	private $url;
	private $debug;

	public function __construct($url, $uuid, $debug=false)
	{
		parent::__construct();
		$this->url = $url;
		$this->uuid = $uuid;
		$this->debug = $debug;
	}

	public function get($request) {

		if($request == 'start') {
			
			if(!$this->debug) {
				
				$this->request('GET', $this->url.'/'.$this->uuid.'/start');
			
			} else {

				\Log::info($this->url.'/'.$this->uuid.'/start called');
			}

		} else if( $request == 'finished') {
			
			if(!$this->debug) {
				
				$this->request('GET', $this->url.'/'.$this->uuid);
			
			} else {

				\Log::info($this->url.'/'.$this->uuid.' called');
			}

		} else if($request == 'failed') {
			
			if(!$this->debug) {
				
				$this->request('GET', $this->url.'/'.$this->uuid.'/fail');
			
			} else {

				\Log::info($this->url.'/'.$this->uuid.'/fail called');
			}
		}
	}
}