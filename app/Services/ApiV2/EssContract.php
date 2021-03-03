<?php namespace App\Services\ApiV2;

use stdClass;
use App\Services\Api\Api;

class EssContract extends Api {

	public function __construct () 
    {
       	parent::__construct();
    }

	public function getContractPayments(stdClass $parameters=null)
	{
		if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
		if(!$this->verify_details($parameters)) return $this->fault($this->parameters_failed_message, 'InvalidParameterException');

        $hash = $parameters->verify_details->hash;
        if($this->has_expired($hash)) return $this->fault($this->expired_message, 'TokenExpiredException');

        $email = $parameters->verify_details->email;
        $ip_address = $parameters->verify_details->ip_address;
		$contract_id = $parameters->contract_id;
		$client = $this->read($hash, $email, $ip_address);

		if(object_exists($client))
		{
			$contract = $client->contracts()->where('id', $contract_id)->first();

			if(object_exists( $contract ))
			{
				$payments = $contract->contractpayments()->where('amount', '>', 0)->orderBy('received')->get();
				$contract_payments_result = new stdClass();
				$contract_payments_result->contract_payments = new stdClass();
				$contract_payments_result->contract_payments->contract_payment = array();

				foreach($payments as $payment)
				{
					$opayment = new stdClass();
					$opayment->amount = $payment->amount;
					$opayment->date = $payment->received;
					$opayment->type = $payment->payment_type;
					$contract_payments_result->contract_payments->contract_payment[] = $opayment;
				}

				return $contract_payments_result;
			}
			
			return $this->fault($this->invalid_contract_message, 'InvalidContractException');
		}

		return $this->fault($this->invalid_client_message, 'InvalidClientException');
	}

	public function getContractProducts(stdClass $parameters=null)
	{
		if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
		if(!$this->verify_details($parameters)) return $this->fault($this->parameters_failed_message, 'InvalidParameterException');

        $hash = $parameters->verify_details->hash;
        if($this->has_expired($hash)) return $this->fault($this->expired_message, 'TokenExpiredException');

        $email = $parameters->verify_details->email;
        $ip_address = $parameters->verify_details->ip_address;
		$contract_id = $parameters->contract_id;
		$client = $this->read($hash, $email, $ip_address);

		if(object_exists($client))
		{
			$contract = $client->contracts()->where('id', $contract_id)->first();

			if(object_exists( $contract ))
			{
				$contract_products = $contract->contractproducts()->orderBy('id', 'desc')->get();
				$contract_products_result = new stdClass();
				$contract_products_result->contract_products = new stdClass();
				$contract_products_result->contract_products->contract_product = array();

				foreach($contract_products as $contract_product)
				{
					$product = $this->models->load('Product', $contract_product->product_id);

					if(object_exists( $product ))
					{
						$ocontract_product = new stdClass();
						$ocontract_product->product_id = $contract_product->product_id;
						$ocontract_product->description = ( trim( $contract_product->description ) == '') ? $product->description : $contract_product->description;
						$ocontract_product->code = $product->code;
						$ocontract_product->price = $contract_product->actual_rental;
						$ocontract_product->retail_value = $contract_product->base_price;
						$ocontract_product->rrp = $contract_product->base_price;
						$ocontract_product->delivered = $contract_product->delivered;
						$contract_product_upgrades = $contract_product->contractproductupgrades()->join('product_type_upgrades', 'contract_product_upgrades.product_type_upgrade_id', '=', 'product_type_upgrades.id')->where('active', 1)->get();
						$ocontract_product->image_url = (($product->wpimage_url !== '') ? $product->wpimage_url : '');

						$ocontract_product->contract_product_upgrades = new stdClass();
						$ocontract_product->contract_product_upgrades->contract_product_upgrade = array();

						foreach($contract_product_upgrades as $contract_product_upgrade)
						{
							$ocontract_product_upgrade = new stdClass();
							$ocontract_product_upgrade->rate = $contract_product_upgrade->rate;
							$ocontract_product_upgrade->description = $contract_product_upgrade->description;
							$ocontract_product->contract_product_upgrades->contract_product_upgrade[] = $ocontract_product_upgrade;
						}

						$contract_products_result->contract_products->contract_product[] = $ocontract_product;
					}
				}

				return $contract_products_result;
			}
			
			return $this->fault($this->invalid_contract_message, 'InvalidContractException');
		}

		return $this->fault($this->invalid_client_message, 'InvalidClientException');
	}

	public function emailDocument(stdClass $parameters=null)
	{
		if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
		if(!$this->verify_details($parameters)) return $this->fault($this->parameters_failed_message, 'InvalidParameterException');

		$hash = $parameters->verify_details->hash;
        if($this->has_expired($hash)) return $this->fault($this->expired_message, 'TokenExpiredException');

        $email = $parameters->verify_details->email;
        $ip_address = $parameters->verify_details->ip_address;
		$contract_id = $parameters->contract_id;
		$client = $this->read($hash, $email, $ip_address);
		$letter_slug = $parameters->letter_slug;

		if(object_exists($client))
		{
			$contract = $client->contracts()->where('id', $contract_id)->first();

			if(object_exists( $contract ))
			{
				$contract_letter = $this->models->load('ContractLetter');
				$contract_letter = $contract_letter->where('name_url', $letter_slug)->first();

				$guzzle = $this->guzzle();
				if(object_exists($contract_letter)) $guzzle->request('GET', 'clients/'.$client->id.'/contracts/'.$contract->id.'/letters/email/'.$contract_letter->name_url);
				else $guzzle->request('GET', 'clients/'.$client->id.'/contracts/'.$contract->id.'/letters/email-documents');
				$guzzle->logout();
			}
		}
	}

	public function getDocuments(stdClass $parameters=null)
	{
		if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
		if(!$this->verify_details($parameters)) return $this->fault($this->parameters_failed_message, 'InvalidParameterException');

        $hash = $parameters->verify_details->hash;
        if($this->has_expired($hash)) return $this->fault($this->expired_message, 'TokenExpiredException');

        $email = $parameters->verify_details->email;
        $ip_address = $parameters->verify_details->ip_address;
		$contract_id = $parameters->contract_id;
		$client = $this->read($hash, $email, $ip_address);

		if(object_exists($client))
		{
			$contract = $client->contracts()->where('id', $contract_id)->first();

			if(object_exists( $contract ))
			{
				$guzzle = $this->guzzle();
				$result = $guzzle->request('GET', 'clients/'.$client->id.'/contracts/'.$contract->id.'/letters/email-documents');
				return true;
			}

			return $this->fault($this->invalid_contract_message, 'InvalidContractException');
		}

		return $this->fault($this->invalid_client_message, 'InvalidClientException');
	}

	public function uploadDocument(stdClass $parameters=null)
	{
		if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
		if(!$this->verify_details($parameters)) return $this->fault($this->parameters_failed_message, 'InvalidParameterException');

        $hash = $parameters->verify_details->hash;
        if($this->has_expired($hash)) return $this->fault($this->expired_message, 'TokenExpiredException');

        $email = $parameters->verify_details->email;
        $ip_address = $parameters->verify_details->ip_address;
		$contract_id = $parameters->contract_id;
		$client = $this->read($hash, $email, $ip_address);

		if(object_exists($client))
		{
			$contract = $client->contracts()->where('id', $contract_id)->first();

			if(object_exists( $contract ))
			{
				$driver = $this->upload_driver();
				$folder = 'testing';
				$name = $parameters->file_details->filename;
				$mime = $parameters->file_details->mime;
				$size = $parameters->file_details->size;
				$hash = $this->upload_file($driver, $folder, $name, $mime, $size);

			    $contract_document = $this->models->load('ContractDocument');
			    $contract_document->contract_id = $contract->id;
              	
              	if(!check_date($contract_document->date_added)) {
              		$contract_document->date_added = myToday();
              	}

              	$contract_document->file = $hash;
              	$contract_document->save();

              	$contract->more_info = 0;
                $contract->save();

			    $result = new stdClass();
			    $driver = $this->upload_driver();
			    $result->path = $driver::path().'/'.$folder.'/'.$hash.'/'.$name;
			    return $result;
			}

			return $this->fault($this->invalid_contract_message, 'InvalidContractException');
		}

		return $this->fault($this->invalid_client_message, 'InvalidClientException');
	}

	public function uploadDocuments(stdClass $parameters=null)
	{
		if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
		if(!$this->verify_details($parameters)) return $this->fault($this->parameters_failed_message, 'InvalidParameterException');

        $hash = $parameters->verify_details->hash;
        if($this->has_expired($hash)) return $this->fault($this->expired_message, 'TokenExpiredException');

        $email = $parameters->verify_details->email;
        $ip_address = $parameters->verify_details->ip_address;
		$client = $this->read($hash, $email, $ip_address);

		if(object_exists($client) ) 
		{
			$files = array();
			$contracts = $client->contracts()->where('contract_status', 'LIKE', 'A%')->whereRaw('(SELECT COUNT(*) FROM contract_assessments WHERE contract_id = contracts.id) <=0')->orderBy('more_info', 'desc')->orderBy('id', 'desc')->get();
			if(empty($contracts) || count($contracts) <= 0 ) $contracts = array($client->contracts()->orderBy('id', 'desc')->first());
			if(isset($parameters->files) && count($contracts) > 0 ) $this->add_contract_file($parameters->files, $files, $contracts);
			$result = new stdClass();
			$result->files = $files;
			return $result;
		}

		return $this->fault($this->invalid_client_message, 'InvalidClientException');
	}

	public function add_contract_document($contract, $hash, &$count)
	{
		if(object_exists($contract) && $contract->need_documents() || $count == 1) {
		 
			$contract_document = $this->models->load('ContractDocument');
			
			if(object_exists($contract)) {
				$contract_document->contract_id = $contract->id;
			}

			if(!check_date($contract_document->date_added)) {
				$contract_document->date_added = myToday();
			}

			$contract_document->file = $hash;
			$contract_document->save();
			
			if(object_exists($contract)) {
				$contract->more_info = 0;
				$contract->save();
			}
		}

		$count++;
	}

	public function add_contract_file($objects, &$files, $contracts)
    {
        if(is_array($objects)) { 
            
            foreach($objects as $object) { 
            	$this->add_contract_file($object, $files, $contracts); 
            }
        
        } else if(is_object($objects)) {
        	
        	$count = 1;
        	$driver = $this->upload_driver();
			$folder = 'testing';
			$name = $objects->filename;
			$mime = $objects->mime;
			$size = $objects->size;
			$hash = $this->upload_file($driver, $folder, $name, $mime, $size);
			
			foreach($contracts as $contract) {
				$this->add_contract_document($contract, $hash, $count);
			}

			$file_object = new stdClass();
			$file_object->file_details = $this->file_details($driver, $folder, $name, $mime, $size, $hash, $objects->temp_filename);
			$files[] = $file_object;

			unset($driver);
        }
    }
}