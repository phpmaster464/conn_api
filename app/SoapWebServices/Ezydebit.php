<?php
namespace App\SoapWebServices;

use App\SoapWebServices\MySoapClient;
use stdClass;

class Ezydebit extends MySoapClient
{
	private $digital_key = '';
	private $public_key ='';
	private $sms_payment_reminder ='NO';
	private $sms_failed_notification='NO';
	private $sms_expired_card='NO';
	private $user = 'Online user';

	public function __construct($wsdl=null, $options=array())
	{
		parent::__construct($wsdl, $options);
		$this->digital_key = getenv('EZYPAY_DIGITAL_KEY'); 
		$this->public_key = getenv('EZYPAY_PUBLIC_KEY');
	}

	public function functions()
	{
		return $this->__getFunctions();
	}

	public function add_customer($id, $name_first, $name_last, $address_line_1, $address_suburb, $address_state, $address_postcode, $email, $mobile, $address_line_2='' )
	{
		$login = new stdClass();
		$login->AddCustomer = new stdClass();
		$login->AddCustomer->DigitalKey = $this->digital_key;
		$login->AddCustomer->YourSystemReference = $id;
		$login->AddCustomer->YourGeneralReference = $id;
		$login->AddCustomer->LastName = $name_last;
		$login->AddCustomer->FirstName = $name_first;
		$login->AddCustomer->AddressLine1 = $address_line_1;
		$login->AddCustomer->AddressSuburb = $address_suburb;
		$login->AddCustomer->AddressState = $address_state;
		$login->AddCustomer->AddressPostCode = $address_postcode;
		$login->AddCustomer->EmailAddress = $email;
		$login->AddCustomer->MobilePhoneNumber = $mobile;
		$login->AddCustomer->SmsPaymentReminder = $this->sms_payment_reminder;
		$login->AddCustomer->SmsFailedNotification = $this->sms_failed_notification;
		$login->AddCustomer->SmsExpiredCard = $this->sms_expired_card;
		$login->AddCustomer->ContractStartDate = myToday();
		$login->AddCustomer->Username = $this->user;
		return $this->__soapCall('AddCustomer',  ( array ) $login);
	}
}