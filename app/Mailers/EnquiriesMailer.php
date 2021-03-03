<?php namespace App\Mailers;

class EnquiriesMailer extends Mailer
{
	public function __construct()
	{
		parent::__construct();
	}

	public function send_email($content)
	{
		$view = 'emails.'.$this->get_business_assets_folder().'.enquiries';

		if(view()->exists($view)) {
			return $this->send('Website Enquiry', $view, array('content'=>$content));
		}
	}
}