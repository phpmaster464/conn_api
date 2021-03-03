<?php namespace App\Mailers;

use Log;
use Mail;
use Models;

abstract class Mailer
{
	protected $email ='';
	protected $name = '';
	protected $from_email = '';
	protected $from_name = '';
	protected $business_assets_folder = '';

	public function __construct()
	{
		$this->from_email = config('mail.from.address');
		$this->from_name = config('mail.from.name');
		$this->business_assets_folder = config('general.business_assets_folder');
	}

	public function set_name($name)
	{
		$this->name = $name;
	}

	public function set_email($email)
	{
		$this->email = $email;
	}

	public function set_from_name($from_name)
	{
		$this->from_name = $from_name;
	}

	public function set_from_email($from_email)
	{
		$this->from_email = $from_email;
	}

	public function get_business_assets_folder() {
		return $this->business_assets_folder;
	}

	public function send($subject, $view, $data=[])
	{
		$email = $this->email; $name = $this->name;

		try
		{
			return Mail::send($view, $data, function($message) use ($email, $name, $subject)
			{
				$message->from($this->from_email, $this->from_name);

				$message->to($email, $name)->subject($subject);

			});
		}
		catch(\Exception $ex)
		{
			Log::info('email failed to send '.$email.' '.$ex->getMessage());
		}
	}
}

?>