<?php namespace App\Messengers;

use App\Services\DirectSms;

class DirectSmsMessenger extends Messenger
{
	private $direct_sms ='';

	public function __construct()
	{
		parent::__construct();
		$this->direct_sms = new DirectSms();
		$this->remove_items = array(' ', '(', ')'); 
	}

	public function send($message)
	{
		$direct_sms = $this->models->load('DirectSms')->first();

		if(object_exists( $direct_sms ))
		{
			$this->direct_sms->setUsername( $direct_sms->username );
	 		$this->direct_sms->setPassword( $direct_sms->password );

			if( $this->direct_sms->join() )
			{
		      	if( $this->direct_sms->connect() )
		      	{ 
		      		  $arrays = array_chunk($this->numbers, $this->direct_sms->getMaxSms(), true );

		      		  foreach( $arrays as $array )
		      		  {
			      		  	$this->direct_sms->setId($this->id);
				          	$this->direct_sms->setMessage($message);
				          	$this->direct_sms->setMobiles($array);
				          	$this->direct_sms->oneWayMessage();
		      		  }
		      		 
		      		  $this->direct_sms->disconnect();	        
		     	}
			}
		}
	}
}