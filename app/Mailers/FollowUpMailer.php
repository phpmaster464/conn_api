<?php namespace App\Mailers;

class FollowUpMailer extends Mailer
{
	private $products = array();

	public function __construct()
	{
		parent::__construct();
	}

	public function send_email($name, $email)
	{
		$view = 'emails.'.$this->get_business_assets_folder().'.followup';

		if(view()->exists($view)) {
			$this->name = $name;
			$this->email = $email;
			return $this->send($this->name.', need a new appliance?', $view, array('title'=>'', 'name'=>$name, 'email'=>$email));
		}
	}
}