<?php namespace App\Mailers;

class ForgotPasswordMailer extends Mailer
{
	public function __construct()
	{
		parent::__construct();
	}

	public function send_email($url, $hash)
	{
		$view = 'emails.'.$this->get_business_assets_folder().'.forgot';

		if(view()->exists($view)) {
			$link = $url.'?hash='.$hash.'&email='.urlencode($this->email);
			$contact_us_link = str_replace('/reset', '/contact', $url);
			$content = 'A request has been made to change the password on your '.config('general.website_name').' online account. To complete this process please click the reset password link </a> and follow the steps.';
			return $this->send(config('general.business_short_name').' forgot password', $view, array('title'=>'Forgot Password', 'name'=>$this->name, 'website_link'=>config('general.website_url'), 'link'=>$link,  'contact_us_link'=>$contact_us_link, 'content'=>$content));
		}
	}
}