<?php namespace App\Services\ApiV2;

use stdClass, Log, MyAuth;
use App\Responders\DatabaseGuzzleResponder as GuzzleResponder;
use App\SoapWebServices\Ezydebit as Ezydebit;
use App\RestWebServices\Database as DatabseRequest;
use App\Services\Api\Api;

class EssClient extends Api {

	public function __construct () 
    {
       	parent::__construct();
    }

    public function editContract(stdClass $parameters=null)
	{
    	if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
        if(!$this->verify_details($parameters)) return $this->fault($this->parameters_failed_message, 'InvalidParameterException');

        $hash = $parameters->verify_details->hash;
        if($this->has_expired($hash)) return $this->fault($this->expired_message, 'TokenExpiredException');

        $email = $parameters->verify_details->email;
        $ip_address = $parameters->verify_details->ip_address;

        $user = $this->user($this->read_hash($hash, $ip_address), $email);
		if(!object_exists($user)) return $this->fault($this->invalid_user_message, 'InvalidUserException');

        $client = $this->models->load('Client', $user->client_id);
		if(!object_exists($client)) return $this->fault($this->invalid_client_message, 'InvalidClientException');
		if(isset($parameters->client_details->crn) && trim( $parameters->client_details->crn ) !='' ) { $client->crn = $parameters->client_details->crn; $client->save(); unset($parameters->client_details->crn); }
    
		$contract_id = $parameters->contract_details->contract_id;
		$contract = $client->contracts()->where('contracts.id', $contract_id)->first();

		if(object_exists($contract))
		{
			$parameters->contract_details->cash_on_delivery = $contract->cash_on_delivery;
			$parameters->contract_details->last_viewed = '0000-00-00';
			$parameters->contract_details->online_user_id = $user->id;
			$parameters->contract_details->date_signed = $contract->date_signed;
			$parameters->contract_details->centrelink_date = $contract->centrelink_date;

			$guzzle = $this->guzzle();
			$result = $guzzle->request('POST', 'clients/'.$client->id.'/contracts/edit/'.$contract->id , ['form_params'=>(array)$parameters->contract_details] );
			$contract = $this->models->load('Contract', $contract->id);
		   	$result = new stdClass();
		   	$result->contract = $this->populate_contract($contract);
		   	$result->hash = MyAuth::encrypt_details($user->id, $ip_address);

		   	$client_credit_cards = $client->clientcreditcards()->select('client_credit_cards.id', 'last_digits')->join('client_ezidebit_accounts', function($join){ $join->on('payable_id', '=', 'client_credit_cards.id') ->where('payable_type', '=', 'ClientCreditCard');})->get();

            if(!empty($client_credit_cards) && count($client_credit_cards) > 0)
            {
                $result->credit_card_details = new stdClass();
                $result->credit_card_details->credit_cards = array();

                foreach($client_credit_cards as $client_credit_card)
                {
                    $ocreditcard = new stdClass();
                    $ocreditcard->id = $client_credit_card->id;
                    $ocreditcard->last_digits = $client_credit_card->last_digits;
                    $result->credit_card_details->credit_cards[] = $ocreditcard;
                }
            }

	   		$client_direct_debits = $client->clientdirectdebits()->select('client_direct_debits.id', 'last_digits')->join('client_ezidebit_accounts', function($join){ $join->on('payable_id', '=', 'client_direct_debits.id') ->where('payable_type', '=', 'ClientDirectDebit');})->get();

            if(!empty($client_direct_debits) && count($client_direct_debits) > 0)
            {
                $result->direct_debit_details = new stdClass();
                $result->direct_debit_details->direct_debits = array();

                foreach($client_direct_debits as $client_direct_debit)
                {
                    $odirectdebit = new stdClass();
                    $odirectdebit->id = $client_direct_debit->id;
                    $odirectdebit->last_digits = $client_direct_debit->last_digits;
                    $result->direct_debit_details->direct_debits[] = $odirectdebit;
                }
            }

            return $result;
	    }

	    return $this->fault($this->invalid_contract_message, 'InvalidContractException');
    }

    public function addCreditCard(stdClass $parameters=null)
    {
    	if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
        if(!$this->verify_details($parameters)) return $this->fault($this->parameters_failed_message, 'InvalidParameterException');

        $hash = $parameters->verify_details->hash;
        if($this->has_expired($hash)) return $this->fault($this->expired_message, 'TokenExpiredException');

        $email = $parameters->verify_details->email;
        $ip_address = $parameters->verify_details->ip_address;

        $user = $this->user($this->read_hash($hash, $ip_address), $email);
		if(!object_exists($user)) return $this->fault($this->invalid_user_message, 'InvalidUserException');

		$client = $this->models->load('Client', $user->client_id);
		if(!object_exists($client)) return $this->fault($this->invalid_client_message, 'InvalidClientException');

		$guzzle = $this->guzzle();

		$guzzle->request('GET', 'clients/'.$client->id.'/credit-cards/load');

		$credit_card = $client->clientcreditcards()->select('client_credit_cards.id', 'last_digits')->join('client_ezidebit_accounts', function($join){ $join->on('payable_id', '=', 'client_credit_cards.id') ->where('payable_type', '=', 'ClientCreditCard');})->orderBy('client_credit_cards.id', 'desc')->first();

		$ezidebit = $this->models->load('ClientEzidebitAccount', $client->ezidebit_customer_id, false);

		if(object_exists($ezidebit)) $guzzle->request('GET', 'clients/'.$client->id.'/ezidebit-accounts/load/'.$ezidebit->id);
		
		$result = new stdClass();
	   	$result->hash = MyAuth::encrypt_details($user->id, $ip_address);
		$result->credit_card_details = new stdClass();
        $result->credit_card_details->credit_cards = array();

		if(object_exists($credit_card))
		{
			$ocreditcard = new stdClass();
            $ocreditcard->id = $credit_card->id;
            $ocreditcard->last_digits = $credit_card->last_digits;
            $result->credit_card_details->credit_cards[] = $ocreditcard;
		}

		return $result;
    }

    public function addDirectDebit(stdClass $parameters=null)
    {
    	if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
        if(!$this->verify_details($parameters)) return $this->fault($this->parameters_failed_message, 'InvalidParameterException');

        $hash = $parameters->verify_details->hash;
        if($this->has_expired($hash)) return $this->fault($this->expired_message, 'TokenExpiredException');

        $email = $parameters->verify_details->email;
        $ip_address = $parameters->verify_details->ip_address;

        $user = $this->user($this->read_hash($hash, $ip_address), $email);
		if(!object_exists($user)) return $this->fault($this->invalid_user_message, 'InvalidUserException');

		$client = $this->models->load('Client', $user->client_id);
		if(!object_exists($client)) return $this->fault($this->invalid_client_message, 'InvalidClientException');

		$guzzle = $this->guzzle();
		$guzzle->request('GET', 'clients/'.$client->id.'/direct-debit/load');

		$direct_debit = $client->clientdirectdebits()->select('client_direct_debits.id', 'last_digits')->join('client_ezidebit_accounts', function($join){ $join->on('payable_id', '=', 'client_direct_debits.id') ->where('payable_type', '=', 'ClientDirectDebit');})->orderBy('client_direct_debits.id', 'desc')->first();

		$ezidebit = $this->models->load('ClientEzidebitAccount', $client->ezidebit_customer_id, false);

		if(object_exists($ezidebit)) $guzzle->request('GET', 'clients/'.$client->id.'/ezidebit-accounts/load/'.$ezidebit->id);

		$result = new stdClass();
	   	$result->hash = MyAuth::encrypt_details($user->id, $ip_address);
		$result->direct_debit_details = new stdClass();
        $result->direct_debit_details->direct_debits = array();

		if(object_exists($direct_debit))
		{
			$odirectdebit = new stdClass();
            $odirectdebit->id = $direct_debit->id;
            $odirectdebit->last_digits = $direct_debit->last_digits;
            $result->direct_debit_details->direct_debits[] = $odirectdebit;
		}

		return $result;
    }

	public function addContract(stdClass $parameters=null)
	{
		if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
        if(!$this->verify_details($parameters)) return $this->fault($this->parameters_failed_message, 'InvalidParameterException');

        $hash = $parameters->verify_details->hash;
        if($this->has_expired($hash)) return $this->fault($this->expired_message, 'TokenExpiredException');

        $email = $parameters->verify_details->email;
        $ip_address = $parameters->verify_details->ip_address;

        $postcode = $this->models->load('Postcode')->where('suburb', trim( $parameters->client_details->delivery_address_city ))->where('postcode', trim( $parameters->client_details->delivery_address_postcode ))->where('state', trim( $parameters->client_details->delivery_address_state ))->first();
        if(!object_exists( $postcode ) ) return $this->fault($this->unserviceable_address_message, 'UnserviceableAddressException');

        $zone = $postcode->zone($this->models);
        if(object_exists($zone)) {

            $products = $this->available_products($postcode, $zone);
            $client_product_list = $parameters->client_products;
	   		
	   		if(isset($client_product_list->products)) 
	   		{
	   			if(!is_array($client_product_list->products)) { $temp = $client_product_list->products; $client_product_list->products = array(); $client_product_list->products[] = $temp; }
   				foreach($client_product_list->products as $product) if(!in_array($product->product_id, $products)) return $this->fault($this->product_not_available_message, 'ProductNotAvailableException');
	   		}
        
        } else {
    		return $this->fault($this->unserviceable_address_message, 'UnserviceableAddressException');
    	}

        $user = $this->user($this->read_hash($hash, $ip_address), $email);
		if(!object_exists($user)) return $this->fault($this->invalid_user_message, 'InvalidUserException');

		$client = $this->models->load('Client', $user->client_id);
		if(!object_exists($client)) return $this->fault($this->invalid_client_message, 'InvalidClientException');

		$google_ads_tags = null;
		$utm_tags = null;
		$service_fee = 0.00;
		$delivery_fee = 0.00;

		if(isset($parameters->contract_details->google_ads_tags)) {
			$google_ads_tags = $parameters->contract_details->google_ads_tags;
			unset($parameters->contract_details->google_ads_tags);
		}

		if(isset($parameters->contract_details->utm_tags)) {
			$utm_tags = $parameters->contract_details->utm_tags;
			unset($parameters->contract_details->utm_tags);
		}

		if(isset($parameters->contract_details->delivery_fee)) { 
			$service_fee = $parameters->contract_details->service_fee; 
		}

		if(isset($parameters->contract_details->delivery_fee)) { 
			$delivery_fee = $parameters->contract_details->delivery_fee; 
		}

		$guzzle = $this->guzzle();

		//IS ROLLOVER 
		$contract = false;
		$rollover_contract = false;

		if(isset($parameters->contract_details->contract_id))
		{
			$contract_id = $parameters->contract_details->contract_id;
			$contract = $client->contracts()->where('id', $contract_id)->first();
			if(object_exists($contract)) $contract->contractproducts()->where('delivered', 0)->delete();
		}

		if(isset($parameters->client_details->crn) && trim( $parameters->client_details->crn ) !='' ) {
			$parameters->contract_details->crn = clean_value($parameters->client_details->crn);
			
			$crn_client = $this->models->load('Client')->where('id', '!=', $client->id)->where('crn', $parameters->contract_details->crn)->first();

			if(object_exists($crn_client)) { 
				return $this->fault($this->user_already_exists_message.' ( crn )', 'UserAlreadyExistsException'); 
			} 
		}
		
		$parameters->contract_details->contract_type_id = 1;

		if(!isset($parameters->contract_details->contract_type_label)) {
			$parameters->contract_details->contract_type_label = "rental";
		}

		$contract_type = $this->models->load('ContractType')->where('name_url', $parameters->contract_details->contract_type_label)->first();

		if(object_exists($contract_type)) { 
			unset($parameters->contract_details->contract_type_label); 
			$parameters->contract_details->contract_type_id = $contract_type->id; 
		}

		//NEED TO TURN BACK ON
		$parameters->contract_details->is_confirmed = 1;
		$parameters->contract_details->cash_on_delivery = 0;
		$parameters->contract_details->last_viewed = '0000-00-00';
		$parameters->contract_details->online_user_id = $user->id;
		$parameters->contract_details->financial_situation = (isset($parameters->contract_details->financial_situation) ? $parameters->contract_details->financial_situation : null );
        $parameters->contract_details->financial_situation_detail = (isset($parameters->contract_details->financial_situation_detail) ? $parameters->contract_details->financial_situation_detail : null );
        $parameters->contract_details->renting_reason = ( isset($parameters->contract_details->renting_reason) ? $parameters->contract_details->renting_reason : null );
        $parameters->contract_details->verify_credit_history = 1;

		if(!object_exists($contract)) { 
			$parameters->contract_details->contract_status = 'Approved Pending Signature'; 
			$parameters->contract_details->date_signed = myToday(); 
		} else { 

			if(!check_date($contract->date_signed)) { 
				$parameters->contract_details->date_signed = myToday(); 
			} else { 
				$parameters->contract_details->date_signed = $contract->date_signed; 
			} 

			$parameters->contract_details->approved_rate = $contract->approved_rate;
		}

		if($client->hasReachedMaxContractsInPeriod($this->get_contract_limit())) {
			return $this->fault($this->max_contracts_message, 'MaxContractReachedException');
		}

		//DUPLICATE CONTRACT CHECK
		$action = object_exists($contract) ? 'edit' : 'add';
		$url = 'clients/' . $client->id . "/contracts/{$action}" . ($action == 'edit' ? "/{$contract->id}" : '');
		$result = $guzzle->request(
		    'POST',
			$url,
			['form_params' => (array) $parameters->contract_details]
		);

		// If adding a contract, id is in guzzle response
		$contract_id = $action == 'edit' ? $contract->id : $guzzle->get_id();
		$contract = $this->models->load('Contract', $contract_id);

		if( object_exists( $contract ))
		{
			$contract_application_detail = $this->models->load('ContractApplicationDetail');
			$contract_application_detail->contract_id = $contract->id;
			$contract_application_detail->ip_address = $ip_address;
			$contract_application_detail->timestamp = date('Y-m-d H:i:s', now_string());
			$contract_application_detail->save();

			if(isset($google_ads_tags) && array_filter((array) $google_ads_tags)) {
				$guzzle->request('POST', 'clients/'.$client->id.'/contracts/add-google-ads-tags/'.$contract->id, ['form_params' => (array) $google_ads_tags]);
			}

			if(isset($utm_tags)  && array_filter((array) $utm_tags)) {
				$guzzle->request('POST', 'clients/'.$client->id.'/contracts/add-utm-tags/'.$contract->id, ['form_params' => (array) $utm_tags]);
			}

			//ADD DELIVERY / SERVICE FEES
			$product = $this->models->load('Product')->where('model_number', 'DELIVERYFEE')->first();

			if(object_exists($product) && $delivery_fee > 0.00) { 
				$contract->contractproducts()->create(['product_id'=>$product->id, 'delivered'=>1, 'actual_rental'=>0.00, 'base_price'=>$delivery_fee, 'description'=>'Delivery fee']); 
			}

			if(object_exists($product) && $service_fee > 0.00) { 
				$contract->contractproducts()->create(['product_id'=>$product->id, 'delivered'=>1, 'actual_rental'=>0.00, 'base_price'=>$service_fee, 'description'=>'Application and establish fee']); 
			}

			$failed_products = array();
			$client_products = $parameters->client_products;

			if(is_object($client_products)) {
				if(isset($client_products->products)) {
					$this->add_contract_product($client_products->products, $client, $contract->id, $failed_products, $guzzle );
				}
			}

			$contract = $this->models->load('Contract', $contract->id);
			$guzzle->request('GET', 'clients/'.$client->id.'/contracts/re-send-online/'.$contract->id );
			$guzzle->logout();

			$result = new stdClass();
			$result->contract = $this->populate_contract($contract);
			$result->hash = MyAuth::encrypt_details($user->id, $ip_address);

			$client_credit_cards = $client->clientcreditcards()->select('client_credit_cards.id', 'last_digits')->join('client_ezidebit_accounts', function($join){ $join->on('payable_id', '=', 'client_credit_cards.id')->where('payable_type', '=', 'ClientCreditCard');})->get();

            if(!empty($client_credit_cards) && count($client_credit_cards) > 0)
            {
                $result->credit_card_details = new stdClass();
                $result->credit_card_details->credit_cards = array();

                foreach($client_credit_cards as $client_credit_card)
                {
                    $ocreditcard = new stdClass();
                    $ocreditcard->id = $client_credit_card->id;
                    $ocreditcard->last_digits = $client_credit_card->last_digits;
                    $result->credit_card_details->credit_cards[] = $ocreditcard;
                }
            }

	   		$client_direct_debits = $client->clientdirectdebits()->select('client_direct_debits.id', 'last_digits')->join('client_ezidebit_accounts', function($join) { $join->on('payable_id', '=', 'client_direct_debits.id')->where('payable_type', '=', 'ClientDirectDebit');})->get();

            if(!empty($client_direct_debits) && count($client_direct_debits) > 0)
            {
                $result->direct_debit_details = new stdClass();
                $result->direct_debit_details->direct_debits = array();

                foreach($client_direct_debits as $client_direct_debit)
                {
                    $odirectdebit = new stdClass();
                    $odirectdebit->id = $client_direct_debit->id;
                    $odirectdebit->last_digits = $client_direct_debit->last_digits;
                    $result->direct_debit_details->direct_debits[] = $odirectdebit;
                }
            }

	   		return $result;
		}
	}

	public function add_contract_product($object, $client, $contract_id, $failed_products, $guzzle )
    {
        if(is_array($object))
        { 
            foreach($object as $cproduct) { $this->add_contract_product($cproduct, $client, $contract_id, $failed_products, $guzzle ); }
        }
        else if(is_object($object))
        {
           $object->additional_info = ''; $object->description = '';
           $product = $this->models->load('Product', $object->product_id);
           
           if(!object_exists($product)) {
           		$failed_products[] = $object->product_id;
           } else {
		   		$product_type = $this->models->load('ProductType', $product->product_type_id, false);
		   		
		   		if(object_exists($product_type)) {
		   			for($x=0; $x<$object->qty; $x++) {
		   				$guzzle->request('POST', 'clients/'.$client->id.'/contracts/'.$contract_id.'/products/add/'.$product_type->product_type_category_id.'/'.$product_type->id.'/'.$product->id, ['form_params'=>(array)$object] );
		   			}
		   		}
		   }
        }
    }

	public function getConfirmation(stdClass $parameters=null)
	{
		$type = trim( $parameters->type );
		$client = $this->models->load('Client');
		$client = $client->where('id', $this->read_hash( $parameters->hash, $parameters->ip_address ));

		if($type == 'email')
		{
			$client = $client->where('email', $parameters->email)->first();

			if(! object_exists($client) ) return $this->fault($this->invalid_client_message, 'InvalidClientException');
				
			if( $parameters->email !='' )
			{
				$token = $this->hash($client, $parameters->ip_address);
				$mailer = new Mailer();
				$mailer->set_name($client->name);
				$mailer->set_email($client->email);
				$mailer->send_email('Your security code is '.$token);
			}
			
		}
		else if($type == 'sms') 
		{ 
			$client = $client->where('mobile', $parameters->mobile)->first();

			if(! object_exists($client) ) return $this->fault($this->invalid_client_message, 'InvalidClientException');

			if( $parameters->mobile !='' )
			{
				$token = $this->hash($client, $parameters->ip_address);
				$messager = new Messenger();
				$messager->add_number($client->mobile);
				$messager->send(' Your security code is '.$token);
			}
		}
	}

	public function getClientContracts(stdClass $parameters=null)
	{
		if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
		if(!$this->verify_details($parameters)) return $this->fault($this->parameters_failed_message, 'InvalidParameterException');
		
		$hash = $parameters->verify_details->hash;
		if($this->has_expired($hash)) return $this->fault($this->expired_message, 'TokenExpiredException');

		$email = $parameters->verify_details->email;
        $ip_address = $parameters->verify_details->ip_address;
		$client = $this->read($hash, $email, $ip_address);

		if(object_exists($client))
		{
			$contracts = $client->contracts();
			
			if(is_array($this->get_exclude_contract_type_list()) && count( $this->get_exclude_contract_type_list() ) > 0) {
				$contracts = $contracts->whereNotIn('contract_type_id', $this->get_exclude_contract_type_list());
			}
			
			$contracts = $contracts->orderBy('id', 'desc')->get();
			$client_contract_result = new stdClass();

			$client_contract_result->contracts = array();

			foreach($contracts as $contract)
			{
				$ocontract = $this->populate_contract($contract);
				$client_contract_result->contracts[] = $ocontract;
			}

			return $client_contract_result;
		}

		return $this->fault($this->invalid_client_message, 'InvalidClientException');
	}  

	public function addIdentifyingDocuments(stdClass $parameters=null)
	{
		if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
		if(!$this->verify_details($parameters)) return $this->fault($this->parameters_failed_message, 'InvalidParameterException');
		
		$hash = $parameters->verify_details->hash;
		if($this->has_expired($hash)) return $this->fault($this->expired_message, 'TokenExpiredException');

		$email = $parameters->verify_details->email;
        $ip_address = $parameters->verify_details->ip_address;

        $user = $this->user($this->read_hash($hash, $ip_address), $email);
		if(!object_exists($user)) return $this->fault($this->invalid_user_message, 'InvalidUserException');
		$client = $this->models->load('Client', $user->client_id);

		if(object_exists($client))
		{
			$driver = $this->upload_driver();

			if(isset($parameters->upload))
			{
			    if(isset($parameters->upload->filename) && $parameters->upload->filename !='' && isset($parameters->upload->temp_filename) && $parameters->upload->temp_filename !='' )
			    {
			        $folder = 'testing';
			        $name = $parameters->upload->filename;
			        $mime = $parameters->upload->mime;
			        $size = $parameters->upload->size;
			        $tmp_name = $parameters->upload->temp_filename;
			        $hash = $this->upload_file($driver, $folder, $name, $mime, $size);
			        $uploads[] = $this->file_details($driver, $folder, $name, $mime, $size, $hash, $tmp_name);

					$client_photo = $this->models->load('ClientPhoto');
					$client_photo->client_id = $client->id;
					$client_photo->identification_type = $parameters->type;
					$client_photo->photo = $hash;
					$client_photo->date_added = myToday();
					$client_photo->save();
			    }
			}

			$result = new stdClass();
	        $result->hash = MyAuth::encrypt_details($user->id, $ip_address);
	        $result->email = $user->email;

			if(!empty($uploads) && count($uploads) > 0)
	        {
	            $result->files = array();

	            foreach($uploads as $upload) 
	            {
	                $file_object = new stdClass();
	                $file_object->file_details = $upload;
	                $result->files[] = $file_object;
	            }
	        }

	        return $result;
        }

		return $this->fault($this->invalid_client_message, 'InvalidClientException');
	}

	public function verifyToken(stdClass $parameters=null) {

		if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
		if(!$this->verify_details($parameters)) return $this->fault($this->parameters_failed_message, 'InvalidParameterException');
		
		$hash = $parameters->verify_details->hash;
		if($this->has_expired($hash)) return $this->fault($this->expired_message, 'TokenExpiredException');

		$email = $parameters->verify_details->email;
        $ip_address = $parameters->verify_details->ip_address;

        $user = $this->user($this->read_hash($hash, $ip_address), $email);
		if(!object_exists($user)) return $this->fault($this->invalid_user_message, 'InvalidUserException');
		$client = $this->models->load('Client', $user->client_id);

		if(object_exists($client))
		{
			if(isset($parameters->token)) {

				$token = $parameters->token;

				$client_verified_detail = $client->clientverifieddetails()->where('token', $token)->first();

				if(object_exists($client_verified_detail)) {

					//if expired 
					if($client_verified_detail->cancelled) {

						return $this->fault('This token has expired', 'TokenCodeExpiredException');
					
					} else if($client_verified_detail->verified) {
						
						return $this->fault('This token has already been verified', 'TokenCodeAlreadyVerifiedException');

					} else {

						$client_verified_detail->verified = 1;
						
						$client_verified_detail->save();
					}

					$updated_client = $this->models->load('Client', $client->id);
            		
            		if(!object_exists( $updated_client )) {
            			return $this->fault($this->invalid_client_message, 'InvalidClientException');
            		}

					$result = new stdClass();
		        	$result->hash = MyAuth::encrypt_details($user->id, $ip_address);
		        	$result->client = $this->populate_client( $updated_client );
		        	return $result;
				}
			}
        }

		return $this->fault($this->invalid_client_message, 'InvalidClientException');	
	}

	public function resendVerifyToken(stdClass $parameters=null) {

		if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
		if(!$this->verify_details($parameters)) return $this->fault($this->parameters_failed_message, 'InvalidParameterException');
		
		$hash = $parameters->verify_details->hash;
		if($this->has_expired($hash)) return $this->fault($this->expired_message, 'TokenExpiredException');

		$email = $parameters->verify_details->email;
        $ip_address = $parameters->verify_details->ip_address;

        $user = $this->user($this->read_hash($hash, $ip_address), $email);
		if(!object_exists($user)) return $this->fault($this->invalid_user_message, 'InvalidUserException');
		$client = $this->models->load('Client', $user->client_id);

		if(object_exists($client)) {

			(new DatabseRequest(config('services.crm.host')))->resendVerifyToken($client); 
			
			$result = new stdClass();
	    	$result->hash = MyAuth::encrypt_details($user->id, $ip_address);
	    	return $result;
        }

		return $this->fault($this->invalid_client_message, 'InvalidClientException');	
	}

}
