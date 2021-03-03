<?php namespace App\Mailers;

class NoContractMailer extends Mailer
{
	private $products = array();

	public function __construct()
	{
		parent::__construct();
	}

	public function send_email($client, $products)
	{
		$view = 'emails.'.$this->get_business_assets_folder().'.nocontract';

		if(view()->exists($view)) {

			$content = 'Hi '.$client->name_first.',<br />';
			$content .= 'You recently made an online application on our website but you forgot the most important part, selecting your new product. Provided in this email are a couple of our popular items that are available in your area and a link where you can view the rest of our range. If you have any problems / questions with the application process please contact us on '.config('general.business_contact_number').'.';

			$this->name = $client->name_first;
			$this->email = $client->email;

			return $this->send($this->name.', need a new appliance?', $view, array('title'=>'', 'content'=>$content, 'client'=>$client, 'products'=>$products));
		}
	}
}