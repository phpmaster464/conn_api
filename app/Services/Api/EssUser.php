<?php namespace App\Services\Api;

use stdClass, MyAuth, Log;

class EssUser extends Api {

	public function __construct () 
    {
       	parent::__construct();
    }

    public function hasChanged(stdClass $parameters=null )
    {
    	if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
    	if(!$this->verify_details($parameters)) return $this->fault($this->parameters_failed_message, 'InvalidParameterException');

        $hash = $parameters->verify_details->hash;
        if($this->has_expired($hash)) return $this->fault($this->expired_message, 'TokenExpiredException');

        $email = $parameters->verify_details->email;
        $ip_address = $parameters->verify_details->ip_address;
        $user = $this->user($this->read_hash($hash, $ip_address), $email);

        if(object_exists($user))
        {	
        	$changed = $user->has_changed;

        	if( $changed ) { 
                $user->has_changed = 0; 
                $user->save(); return true; 
            } else {
                return false;
            }
        }

        return $this->fault($this->invalid_user_message, 'InvalidUserException');
    }

    public function addEnquiry(stdClass $parameters=null )
    {
    	if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
    	if(!$this->verify_details($parameters)) return $this->fault($this->parameters_failed_message, 'InvalidParameterException');

        $hash = $parameters->verify_details->hash;
        if($this->has_expired($hash)) return $this->fault($this->expired_message, 'TokenExpiredException');

        $email = $parameters->verify_details->email;
        $ip_address = $parameters->verify_details->ip_address;
        $user = $this->user($this->read_hash($hash, $ip_address), $email);

        if(object_exists($user))
        {
        	$this->enquiry( $parameters->enquiry, $user->id, 'OnlineUser');
        	return true;
        } 

        return $this->fault($this->invalid_user_message, 'InvalidUserException');
    }

    public function addClient(stdClass $parameters=null)
    {    	    
        if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
        if(!$this->verify_details($parameters)) return $this->fault($this->parameters_failed_message, 'InvalidParameterException');
       
        $hash = $parameters->verify_details->hash;
        if($this->has_expired($hash)) return $this->fault($this->expired_message, 'TokenExpiredException');

        $email = $parameters->verify_details->email;
        $ip_address = $parameters->verify_details->ip_address;
        $user = $this->user($this->read_hash($hash, $ip_address), $email);

        $selected_postcode = (isset($parameters->client_details->delivery_address_postcode) ? $parameters->client_details->delivery_address_postcode : $parameters->client_details->address_postcode );
        $selected_city = (isset($parameters->client_details->delivery_address_city) ? $parameters->client_details->delivery_address_city : $parameters->client_details->address_city );
        $selected_state = (isset($parameters->client_details->delivery_address_state) ? $parameters->client_details->delivery_address_state : $parameters->client_details->address_state );

        if(object_exists($user))
        {
            $postcode = $this->models->load('Postcode')->where('postcode', trim( $selected_postcode ))->where('suburb', trim( $selected_city ))->where('state', trim( $selected_state ))->first();
            
            if(!object_exists( $postcode ) ) {
                return $this->fault('Currently we are unable to service your provided address', 'UnserviceableAddressException', 'Client');
            }

            $guzzle = $this->guzzle();
            $client = $this->models->load('Client', ( ( trim($user->client_id) == '' ) ? 0 : $user->client_id)); 

            if(isset($parameters->client_details))
            {
                $other_user = $this->models->load('User')->where('id', '!=', $user->id)->where('email', $parameters->client_details->email)->first();

        		if(object_exists( $other_user )) {
                    return $this->fault('Account matching provided email already exists', 'InvalidEmailException', 'Client');
                }
                
        		$user->email = $parameters->client_details->email;
                $parameters->client_details->address_period = 2;
            	$parameters->client_details->dob = $user->dob;
            	$parameters->client_details->crn = $user->crn;
            	$parameters->client_details->email = $user->email;
            	$parameters->client_details->medicare_card = $user->medicare;
            	$parameters->client_details->medicare_card_reference = $user->medicare_card_reference;
            	$parameters->client_details->medicare_card_expiry = $user->medicare_card_expiry;
            	$parameters->client_details->medicare_card_colour = $user->medicare_card_colour;
            	$parameters->client_details->medicare_card_middle_name = $user->medicare_card_middle_name;
            	$parameters->client_details->driver_license = $user->license;
            	$parameters->client_details->driver_license_state_code = $user->license_state_code;
            	$parameters->client_details->passport = $user->passport;
            	$parameters->client_details->passport_country_code = $user->passport_country_code;
                $parameters->client_details->statement_value = 'Email';
                $parameters->client_details->online_user_id = $user->id;
                $parameters->client_details->is_online = 1;
                $parameters->client_details->verify_identity = 1;
                $parameters->client_details->ezidebit_customer_id = ((object_exists($client) ? $client->ezidebit_customer_id : '' ));
                //$parameters->client_details->address_postcode = $postcode->postcode;
                //$parameters->client_details->address_city = $postcode->suburb;
                //$parameters->client_details->address_state = $postcode->state;
                
                $result = $guzzle->request('POST', 'clients/'. ( object_exists($client) ? 'edit/'.$client->id : 'add' ), ['form_params'=>(array)$parameters->client_details] );

                $matched_client = $this->models->load('Client', head( $result->getHeader('exists'), false ) , false);
                
                if(object_exists($matched_client)) 
                { 
                    $user->matched_client_id = $matched_client->id; 
                    $user->save(); 
                    return $this->fault($this->client_already_exists_message, 'ClientExistsException');
                } 
                	
                if(!object_exists($client))
                {
                	$client_online_user = $this->models->load('ClientOnlineUser')->where('online_user_id', $user->id)->first();

                	if(object_exists($client_online_user))
                	{
                		$client = $this->models->load('Client')->where('id', $client_online_user->client_id)->first();
                		if(object_exists($client)) $user->client_id = $client->id;
                		else return $this->fault($this->invalid_client_message, 'InvalidClientException');
                	}
                	else return $this->fault($this->invalid_client_message, 'InvalidClientException');
                }
            }

            $client_income = $parameters->client_income;
            $client_deductions = $parameters->client_deductions;

            if(is_object($client_income)) $guzzle->request('POST', 'clients/'.$client->id.'/income/add', ['form_params'=>$client_income ] );
            if(is_object($client_deductions)) $guzzle->request('POST', 'clients/'.$client->id.'/deductions/add', ['form_params'=>$client_deductions ] );
            if(isset($parameters->client_details->different_delivery_address) && $parameters->client_details->different_delivery_address) $guzzle->request('POST', 'clients/'.$client->id.'/delivery-address/edit/'.$client->id, ['form_params'=>$parameters->client_details ] );
            else $guzzle->request('GET', 'clients/'.$client->id.'/delivery-address/process-remove' );
            $guzzle->logout();

            $updated_client = $this->models->load('Client', $client->id);
            if(!object_exists( $updated_client )) return $this->fault($this->invalid_client_message, 'InvalidClientException');

            $user_changed = false;
        	$user->save();

            $result = new stdClass();
            $result->hash = MyAuth::encrypt_details($user->id, $ip_address);
            $result->email = $user->email;
            $result->client = $this->populate_client( $updated_client );

            $result->service = 0;
            $result->limited = 1;
            $result->products = array();

            if(object_exists($postcode))
            {
                $result->postcode = $postcode->postcode;
                $result->suburb = $postcode->suburb;

                if($postcode->service) 
                {
                    $result->service = 1;
                    $products = array();
                    $zone = false;
                    $zone = $postcode->zone($this->models);

                    if(object_exists($zone))
                    {
                        if($zone->we_service) $result->limited = 0; 
                        $products = $this->available_products($postcode, $zone);
                    }

                    foreach($products as $product) $result->products[] = $product;
                }   
            }

            return $result;
        }

        return $this->fault($this->invalid_user_message, 'InvalidUserException');
    }

    public function add_budget_type($object, $client, $type, $guzzle )
    {
        if(is_array($object)) foreach($object as $income) { $this->add_budget_type($income, $client, $type, $guzzle ); }
        
        else if(is_object($object))
        {
            if(isset($object->id) && $object->id != '' && $object->id != 0){ $c_budget = $this->models->load($type, $object->id); if(!object_exists($c_budget)) $c_budget = $this->models->load($type); }
            else $c_budget = $this->models->load($type);
            $result = $guzzle->request('POST', 'clients/'.$client->id.'/'.$c_budget->url().'/'.( object_exists($c_budget) ? 'edit/'.$c_budget->id : 'add' ), ['form_params'=>(array)$object] );
        }
    }
}