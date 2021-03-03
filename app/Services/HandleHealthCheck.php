<?php 	
namespace App\Services;

use App\RestWebServices\HealthChecks;

class HandleHealthCheck
{
	private $healthcheck = '';

	public function __construct($code) {
		$this->healthcheck = new HealthChecks( "https://hc-ping.com", $code , config('services.healthcheck.debug') );
	}

	public function start() {
		return $this->healthcheck->get('start');
	}

	public function finished() {
		return $this->healthcheck->get('finished');	
	}

	public function failed() {
		return $this->healthcheck->get('failed');
	}
}