<?php 
	namespace App\RestWebServices; 
	class Database extends MyRestClient
	{
		private $path = '';
		private $token ='';
		private $account_reference_number ='';

		public function __construct($path)
		{
			parent::__construct();
			$this->path = $path;
		}

		public function predict($contract)
		{			
			parent::request('get', $this->path.'eskatos/predict/'.$contract->id);
		}

		public function resendVerifyToken($client)
		{
			parent::request('get', $this->path.'triggers/resend-verify-token/'.$client->id);
		}
	}