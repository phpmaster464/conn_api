<?php namespace App\Mailers;

class SecurityMailer extends Mailer
{
	public function __construct()
	{
		parent::__construct();
	}

	public function send_email($code)
	{
		$view = 'emails.'.$this->get_business_assets_folder().'.security';

		if(view()->exists($view)) {
			return $this->send('Essential [ security code ]', $view, array('title'=>'Security code', 'content'=>$code));
		}
	}
}