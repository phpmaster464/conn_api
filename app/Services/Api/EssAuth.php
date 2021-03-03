<?php namespace App\Services\Api;

use stdClass;
use MyAuth;
use App\Services\Api\Api, Log;
use DB;
use Password;
use App\Services\TokenProvider as Token;
use App\Mailers\SecurityMailer as Mailer;
use App\Mailers\ForgotPasswordMailer as ForgotMailer;
use App\Messengers\DirectSmsMessenger as Messenger;
use App\RestWebServices\EzyPayV2 as EzyPay;
use App\Mailers\EnquiriesMailer as EnquiriesMailer;

class EssAuth extends Api {

	public function __construct () 
    {
       	parent::__construct();
    }

    public function canService(stdClass $parameters=null)
    {
        if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
        $clean_postcode = clean_value( trim($parameters->postcode) );
        $clean_suburb = clean_value( trim($parameters->suburb) );
        $first_three_suburb = substr($clean_suburb, 0, 3);
        $first_postcode = substr($clean_postcode, 0, 3);

        $postcode = $this->find_postcode($clean_suburb, $clean_postcode);
        if(!object_exists($postcode)) return $this->fault($this->unserviceable_address_message, 'UnserviceableAddressException');

        $zone = false;
        $result = new stdClass();
        $result->service = 0;
        $result->limited = 1;
        $result->products = array();

        if(object_exists($postcode))
        {
            $result->postcode = $postcode->postcode;
            $result->suburb = $postcode->suburb;
            $zone = $postcode->zone($this->models);
            
            if($postcode->service) $result->service = 1;
            
            $products = array();

            if(object_exists($zone))
            {
                if($zone->we_service) $result->limited =0; 
                $products = $this->available_products($postcode, $zone);
            }

            foreach($products as $product) $result->products[] = $product;
        } 

        return $result;
    }

    public function applyNow(stdClass $parameters=null)
    {
        $result = new stdClass();
        $clean_postcode = trim( clean_value( $parameters->address_postcode ) );
        $clean_suburb = trim( clean_value( $parameters->address_city ) );
        $first_three_suburb = substr($clean_suburb, 0, 3);
        $clean_state = trim(clean_value($parameters->address_state));
        $product = false;
        $product_id = $parameters->product_id;
        $postcode = $this->models->load('Postcode')->where('suburb', $clean_suburb)->where('postcode', $clean_postcode)->first();

        if(!object_exists(  $postcode  ) )
        {
            $postcode = $this->models->load('Postcode')->where('suburb', $clean_suburb)->where('state', $clean_state)->first();
        }

        if(!object_exists(  $postcode  ) )
        {
            $postcode = $this->models->load('Postcode')->where('suburb', 'LIKE', $first_three_suburb.'%')->where('postcode', $clean_postcode)->where('state', $clean_state)->where('service', 1)->first();
        }

        if(!object_exists(  $postcode  ) )
        {
            $postcodes = $this->models->load('Postcode')->where('postcode', '=', $clean_postcode)->where('state', $clean_state)->orderBy('suburb')->get();

            $result = new stdClass();
            $result->result = 0;

            if(!empty($postcodes) && count($postcodes) > 0)
            {
                $result->postcodes = new stdClass();
                $result->postcodes->postcode = array();

                foreach($postcodes as $postcode)
                {
                    $pcode = new stdClass();
                    $pcode->suburb = $postcode->suburb;
                    $pcode->postcode = $postcode->postcode;
                    $pcode->state = $postcode->state;
                    $result->postcodes->postcode[] = $pcode;
                }

                return $result;
            } 
        }
        
        if(!object_exists( $postcode ) ) 
        {
            $failed_postcode_search = $this->models->load('FailedPostcodeSearch');
            $failed_postcode_search->date_added = myToday();
            $failed_postcode_search->suburb = $clean_suburb;
            $failed_postcode_search->postcode = $clean_postcode;
            $failed_postcode_search->state = $clean_state;
            $failed_postcode_search->save();
            return $this->fault($this->unserviceable_address_message, 'UnserviceableAddressException');
        }

        $zone = $postcode->zone($this->models);

        if(object_exists($zone))
        {
            if($zone->we_service) 
            { 
                $product = $this->models->load('Product');
                $product = $product->where(function($query) use ($zone)
                                    {
                                        $query->where(DB::raw('(SELECT COUNT(*) FROM product_includes WHERE product_includes.product_id = products.id AND product_includes.available = 1 AND product_includes.zone_id = '.$zone->id.')'), '>', 0);
                                    })
                                   ->where('products.id', $product_id)
                                   ->first();
            }
            else
            {
                $postcode_supplier_locations = $this->models->load('PostcodeSupplierLocation');
                
                $found_suppliers = $postcode_supplier_locations->select('postcode_supplier_locations.id', 'supplier_locations.supplier_id')
                                                               ->join('supplier_locations', 'postcode_supplier_locations.supplier_location_id', '=', 'supplier_locations.id')
                                                               ->where('postcode_id', $postcode->id)
                                                               ->where('distance', '<=', config('general.delivery_distance_limit'))
                                                               ->groupBy('supplier_id')
                                                               ->pluck('supplier_id', 'supplier_id')->all();
                
                $product = $this->models->load('Product');
                $product = $product->where(DB::raw('(SELECT COUNT(*) FROM product_includes WHERE product_includes.product_id = products.id AND product_includes.available = 1)'), '>', 0)
                                   ->where(function($query) use ($found_suppliers)
                                   {
                                        $query->whereIn('supplier_id', $found_suppliers)
                                              ->orWhere('products.can_mail_out', '=', 1);
                                   })
                                   ->where('products.id', $product_id)
                                   ->first();

                $products = $this->available_products($postcode, $zone);
            }
        }

        if(!object_exists($product)) return $this->fault('Product not available', 'ProductNotAvailableException');

        $email = clean_value( $parameters->email ) ;
        $name_first = clean_value( $parameters->name_first );

        $apply_now_application = $this->models->load('ApplyNowApplication');
        $apply_now_application = $apply_now_application->select('apply_now_applications.id', 'enquiries.id as enquiry_id')
                                    ->join('enquiries', function($query)
                                    {
                                        $query->on('apply_now_applications.id', '=', 'messageable_id')
                                              ->where('messageable_type', '=', 'ApplyNowApplication');
                                    })
                                    ->where('email', $email)
                                    ->where('name_first', $name_first)
                                    ->where('resolved', 0)
                                    ->first();

         if(!object_exists($apply_now_application)) 
         {                
            $apply_now_application = $this->models->load('ApplyNowApplication');
            $apply_now_application->name_first = trim( $name_first );
            $apply_now_application->name_last = trim( clean_value( $parameters->name_last ) );
            $apply_now_application->name = $apply_now_application->name_first.' '.$apply_now_application->name_last;
            $apply_now_application->email = trim( $email );
            $apply_now_application->mobile = trim( clean_value( $parameters->mobile ) );
            $apply_now_application->phone = trim( clean_value( $parameters->phone ) );
            $apply_now_application->product_id = clean_value( $parameters->product_id );
            $apply_now_application->address_unit_number = trim(clean_value($parameters->address_unit_number));   
            $apply_now_application->address_number = trim(clean_value($parameters->address_number));
            $apply_now_application->address_name = trim(clean_value($parameters->address_name));
            $apply_now_application->address_city = $postcode->suburb;
            $apply_now_application->address_state = $postcode->state;
            $apply_now_application->address_postcode = $postcode->postcode;
            $apply_now_application->address = (($apply_now_application->address_unit_number != '') ? $apply_now_application->address_unit_number.' / ' : '').$apply_now_application->address_number.' '.$apply_now_application->address_name.' '.$apply_now_application->address_city.' '. $apply_now_application->address_state.' '.$apply_now_application->address_postcode;
            $apply_now_application->application_comment = trim(clean_value($parameters->comment));
            $apply_now_application->save();
            $this->enquiry( 'Online application' , $apply_now_application->id, 'ApplyNowApplication');
        }

        if(object_exists($product) && object_exists($apply_now_application))
        {
            $apply_now_application_product = $this->models->load('ApplyNowApplicationProduct');
            $apply_now_application_product->apply_now_application_id  = $apply_now_application->id;
            $apply_now_application_product->product_id = $product->id;
            $apply_now_application_product->save();
        }
        
        $result->result = 1;
        return $result;
    }

    public function addEnquiry(stdClass $parameters=null )
    {
        if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
        $public_enquiry = $this->models->load('PublicEnquiry');
        $public_enquiry->name = clean_value( $parameters->name );
        $public_enquiry->email = clean_value( $parameters->email );
        $public_enquiry->contact = clean_value( $parameters->contact );
        $public_enquiry->save();

        $mailer = new EnquiriesMailer();
        $mailer->set_name('Terry Miller');
        $mailer->set_email('enquiries@essential.net.au');
        $mailer->set_from_name(  $public_enquiry->name );
        $mailer->set_from_email( $public_enquiry->email );
        $mailer->send_email( clean_value( $parameters->enquiry ) );
        return true;
    }

    public function forgotPassword(stdClass $parameters=null)
    {
    	$email = trim( $parameters->email );
    	$user = $this->models->load('User')->where('email', $email)->first();

    	if(object_exists( $user )) 
    	{  
            $repo = Password::getRepository();
            $token = $repo->create( $user );
            $password_reset = $this->models->load('PasswordReset');
            $password_reset->email = $user->email;
            $password_reset->token = $token;
            $password_reset->save();

            $mailer = new ForgotMailer();
            $mailer->set_name($user->name);
            $mailer->set_email($user->email);
            $mailer->send_email($parameters->url, $password_reset->token);
    	}
    }

    public function resetPassword(stdClass $parameters=null)
    {
        if(!isset($parameters->email ) || !isset($parameters->hash) || !isset($parameters->password)) return $this->fault($this->parameters_failed_message, 'InvalidParameterException');
    	$email = trim( $parameters->email );
    	$token = trim( $parameters->hash );
    	$password = trim( $parameters->password );

        if(!$this->valid_password($parameters->password )) return $this->fault($this->valid_password_message, 'InvalidPasswordException', 'Client');

        $user = $this->models->load('User');
        $user = $user->where('email', $email)->first();
        if(!object_exists( $user )) return $this->fault('Account matching provided email not found ', 'InvalidUserException');

        if(Password::tokenExists($user, $token))
        {
            $user->password = MyAuth::hash_password($password);
            $user->confirmed = 1;
            $user->save();
            return true;
        }
        return $this->fault('Invalid security token', 'InvalidSecurityTokenException');
    }

	public function login(stdClass $parameters=null)
	{        
        if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
		if(!isset($parameters->email) || trim($parameters->email) == '') return $this->fault('Please enter your email', 'InvalidParameterException');
		if(!isset($parameters->password) || trim($parameters->password) == '') return $this->fault('Please enter your password', 'InvalidParameterException');

		$email = $parameters->email;
		$password = $parameters->password;
		$ip_address = $parameters->ip_address;

		$user = $this->models->load('User')->where('email', $email)->first();

        if(!object_exists($user)) {
            return $this->fault('Incorrect email address or password', 'InvalidLoginException');
        }

		$valid_password = MyAuth::check_password($user, $password, $user->password);
		
        if(!$valid_password) {
            return $this->fault('Incorrect email address or password', 'InvalidLoginException');
        }

		$loginResult = new stdClass();
		$loginResult->return = new stdClass();
		$loginResult->return->hash = MyAuth::encrypt_details($user->id, $ip_address);
		return $loginResult;
	}

    public function confirmation(stdClass $parameters=null)
    {   
        if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
        if(!$this->verify_details($parameters)) return $this->fault($this->parameters_failed_message, 'InvalidParameterException');

        $hash = $parameters->verify_details->hash;
        if($this->has_expired($hash)) return $this->fault($this->expired_message, 'TokenExpiredException');

        $email = $parameters->verify_details->email;
        $ip_address = $parameters->verify_details->ip_address;
        $user = $this->user($this->read_hash($hash, $ip_address), $email);
        $type = $parameters->type;

        if(object_exists($user))
        {
            $client = $this->models->load('Client', $user->matched_client_id, false);

            if(object_exists($client))
            {
                if($type == 'email')
                {                        
                    if( $client->email !='' )
                    {
                        $token = $this->hash($user, $ip_address);
                        $mailer = new Mailer();
                        $mailer->set_name($client->name);
                        $mailer->set_email($client->email);
                        $result = $mailer->send_email('Your security code is '.$token);
                    }
                }
                else if($type == 'sms') 
                {
                    if( $client->mobile !='' )
                    {
                        $token = $this->hash($user, $ip_address);
                        $messager = new Messenger();
                        $messager->add_number($client->mobile);
                        $messager->send(' Your security code is '.$token);
                    }
                }
            }
        }
        else return $this->fault($this->invalid_user_message, 'InvalidUserException');
    }

    public function processToken(stdClass $parameters=null)
    {        
        if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
        if(!$this->verify_details($parameters)) return $this->fault($this->parameters_failed_message, 'InvalidParameterException');

        $hash = $parameters->verify_details->hash;
        if($this->has_expired($hash)) return $this->fault($this->expired_message, 'TokenExpiredException');

        $email = $parameters->verify_details->email;
        $ip_address = $parameters->verify_details->ip_address;
        $user = $this->user($this->read_hash($hash, $ip_address), $email);

        $token = trim( $parameters->token );

        if(object_exists($user))
        {
            $security_code = $this->models->load('SecurityCode');
            $security_code =  $security_code->where('user_id', $user->id)
                                            ->where('token', $token)
                                            ->where('ip_address', $ip_address)
                                            ->first();

            if(object_exists( $security_code ))
            {
                $client = $this->models->load('Client', $user->matched_client_id, false);

                if(object_exists($client))
                {
                    $user->client_id = $client->id;
                    $user->save();
                    $security_code->delete();
                    return true;
                }
            }
            else return $this->fault('Invalid security token', 'InvalidSecurityTokenException');
        }
        else return $this->fault($this->invalid_user_message, 'InvalidUserException');
    }

    public function addUser(stdClass $parameters=null)
    {                  
        if(!$this->simple_auth()) return $this->fault($this->authentication_failed_message, 'InvalidAuthenticationException');
        $user = $this->models->load('User');
        $user = $user->where('email', trim($parameters->email))->first();
        if(object_exists( $user )) return $this->fault('User account matching provided email already exists', 'InvalidEmailException', 'Client');

        $existing_client = $this->models->load('Client')->where('email', $parameters->email)->first();

        $postcode = $this->models->load('Postcode')->where('postcode', trim( $parameters->address_postcode ))->where('state', trim( $parameters->address_state ))->first();
        
        if(!object_exists( $postcode ) ) {
            return $this->fault($this->unserviceable_address_message, 'UnserviceableAddressException', 'Client');
        }

        if(!$this->valid_password($parameters->password )) {
            return $this->fault($this->valid_password_message, 'InvalidPasswordException', 'Client');
        }

        $crn = isset($parameters->crn) ? clean_value( $parameters->crn ) : '';
        $license = isset($parameters->license->number) ? clean_value( $parameters->license->number ) : '';
        
        $medicare = '';

        if(isset($parameters->medicare)) {
            $medicare = isset($parameters->medicare->number) ? str_replace(' ', '', clean_value( $parameters->medicare->number )) :'';
            $medicare_card_reference = clean_value( $parameters->medicare->reference_number );
        }

        $name_first = clean_value( $parameters->name_first );
        $passport = isset($parameters->passport->number) ? clean_value( $parameters->passport->number ) : '';

        $client = false;
        if(isset($parameters->client_id)) { $client = $this->models->load('Client', $parameters->client_id, false); }
        else if( $this->client_details_exists('crn', $crn ) !== false ) { $client = $this->client_details_exists('crn', $crn ); } 
        else if( !object_exists($client) && $this->client_details_exists('driver_license', $license  ) !== false ) { $client = $this->client_details_exists('driver_license', $license  ); }
        else if( !object_exists($client) && $this->client_details_exists('passport', $passport ) !== false ) { $client = $this->client_details_exists('passport', $passport ); }

        if(!object_exists($client) && $medicare != '') { 
            $client = $this->models->load('Client')->where('medicare_card', $medicare)->where(function($query) use ($name_first, $medicare_card_reference) { $query->where('medicare_card_reference', $medicare_card_reference)->orWhere(function($query) use ($name_first) { $query->where(DB::raw('COALESCE(medicare_card_reference, "")'), '=', '')->where('name_first', $name_first); } ); } )->first(); 
        }

        if(object_exists($existing_client)) 
        {
            if(!object_exists($client)) return $this->fault('Account matching provided email already exists', 'InvalidEmailException', 'Client');
            else if($client->id != $existing_client->id) return $this->fault('Account matching provided email already exists', 'InvalidEmailException', 'Client');
        }

        $parameters->dob = date('Y-m-d', strtotime($parameters->dob_year.'/'.$parameters->dob_month.'/'.$parameters->dob_day));
        
        $user = $this->models->load('User');
        $user->reference = create_hash();
        $user->client_id = (isset($parameters->client_id) ? $parameters->client_id : 0 );
        $user->email = clean_value( $parameters->email );
        $user->dob = clean_value( $parameters->dob );
        $user->mobile = (isset( $parameters->mobile ) ? clean_value( $parameters->mobile ) : '');
        $user->phone = (isset( $parameters->phone ) ? clean_value( $parameters->phone ) : '');
        $user->name_first = clean_value( $parameters->name_first );
        $user->name_last = clean_value( $parameters->name_last );
        $user->name = $user->name_first.' '.$user->name_last;
        $user->address_state = clean_value( $parameters->address_state );
        $user->address_postcode = clean_value( $parameters->address_postcode );

        if(!check_date($user->dob)) return $this->fault('Provided date of birth is not valid', 'InvalidDobException');

        $uploads = array();

        if($crn !='') 
        {
            //VALIDATE CRN
            $user->crn = strtoupper( $crn );
            if(!preg_match('/^[0-9]{9}[A-Z]{1}$/', $user->crn)) { return $this->fault('Provided Centrelink reference number is not valid', 'InvalidCrnException'); } 
            if($this->user_details_exists('crn', $user->crn )) { 
                return $this->fault($this->user_already_exists_message.' ( crn )', 'UserAlreadyExistsException'); 
            } 
        }

        if($license !='')
        {
            $user->license = $license;
            if(isset($parameters->license->state_code)) $user->license_state_code = clean_value( $parameters->license->state_code );
            if( $this->user_details_exists('license', $user->license  ) ) { 
                return $this->fault($this->user_already_exists_message.' ( license )', 'UserAlreadyExistsException'); 
            }
        } 
        
        if($medicare !='')
        {
            //VALIDATE MEDICARE CARD
            if(!isset($parameters->medicare->expiry_date_month) || !isset($parameters->medicare->expiry_date_year)) return $this->fault('Incorrect expiry date for medicare card mm/yyyy', 'InvalidMedicareExpiryException'); 
            $user->medicare = $medicare;
            $user->medicare_card_reference = $medicare_card_reference;
            $user->medicare_card_middle_name = clean_value( $parameters->medicare->middle_name );
            $user->medicare_card_expiry = clean_value( $parameters->medicare->expiry_date_month ).'/'.clean_value( $parameters->medicare->expiry_date_year );
            $user->medicare_card_colour = clean_value( $parameters->medicare->card_colour ); 
            if(!preg_match('/^[0-9]{10}$/', $user->medicare)) return $this->fault('Provided medicare card number is not valid', 'InvalidMedicareNumberException'); 
            if(!preg_match('/^[1-9]{1}$/', $user->medicare_card_reference)) return $this->fault('Provided medicare card reference number is not valid', 'InvalidMedicareReferenceNumberException');  
                
            $find_medicare_user = $this->models->load('User');
            $find_medicare_user = $find_medicare_user->where('medicare', $medicare)->where(function($query) use ($name_first, $medicare_card_reference) { $query->where(function($query) use ($medicare_card_reference) { $query->where('medicare_card_reference', $medicare_card_reference)->where(DB::raw('COALESCE(medicare_card_reference, "")'), '!=', ''); })->orWhere(function($query) use ($name_first) { $query->where(DB::raw('COALESCE(medicare_card_reference, "")'), '=', '')->where('name_first', $name_first); } ); } )->first(); 
            if( object_exists( $find_medicare_user )) { 
                return $this->fault($this->user_already_exists_message.' ( medicare )', 'UserAlreadyExistsException'); 
            }         
        }
        
        if($passport != '')
        {
            $user->passport = $passport;
            $country = false;

            if(isset($parameters->passport->country))
            {
                $country_name = trim( clean_value( $parameters->passport->country ) );   
                if(trim($country_name) == '') $country_name = 'Australia';
                $country = $this->models->load('Country')->where('name', $country_name )->first();  
            }
            else if(isset($parameters->passport->country_code))
            {
                $country_code = trim( clean_value( $parameters->passport->country_code ) ); 
                if(trim($country_code) == '') $country_code = 'AUS';
                $country = $this->models->load('Country')->where('prefix', $country_code)->first();
            }

            if(!object_exists($country)) return $this->fault($this->invalid_country_message, 'InvalidCountryException');  
            
            $user->passport_country_code = $country->prefix;
            if( $this->user_details_exists('passport', $user->passport ) ) { 
                return $this->fault($this->user_already_exists_message.' ( passport )', 'UserAlreadyExistsException'); 
            }         
        } 

        $user->password = MyAuth::hash_password( $parameters->password );
        $user->confirmed = 1;  
        if(isset($parameters->confirmation)) $user->confirmation = clean_value( $parameters->confirmation );
        if(isset($parameters->consent)) $user->consent = clean_value( $parameters->consent );
        if(object_exists( $client )) $user->matched_client_id = $client->id;  
        if(!check_date($user->date_user_added)) $user->date_user_added = myToday();
        $user->save();

        $result = new stdClass();
        $result->hash = MyAuth::encrypt_details($user->id, $parameters->ip_address);
        $result->reference = $user->reference;
        $result->email = $user->email;
        $result->name_first = $user->name_first;
        $result->name_last = $user->name_last;
        $result->mobile = $user->mobile;
        $result->phone = $user->phone;
        $result->client_match = 0;

        if(object_exists( $client ) ) {
            $result->client_match = 1;
            $this->confirmation_methods($client, $result);
        }

        return $result;
    }

    public function getUser(stdClass $parameters=null)
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
            $user->has_changed = 0;
            $user->save();

            $user_result = new stdClass();
            $user_result->user = new stdClass();
            $user_result->user->reference = $user->reference;
            $user_result->user->email = $user->email;
            $user_result->user->name_first = $user->name_first;
            $user_result->user->name_last = $user->name_last;
            $user_result->user->dob = $user->dob;

            $client = $this->client($user->client_id);

            $user_result->client_exists = 0;
            $user_result->client_match = 0;

            if(object_exists($client))
            {
                $oclient = $this->populate_client( $client );
                $user_result->client = $oclient;
                $user_result->client_exists = 1;

                $client_credit_cards = $client->clientcreditcards()->select('client_credit_cards.id', 'last_digits')->join('client_ezidebit_accounts', function($join){ $join->on('payable_id', '=', 'client_credit_cards.id') ->where('payable_type', '=', 'ClientCreditCard'); })->get();

                if(!empty($client_credit_cards) && count($client_credit_cards) > 0)
                {
                    $user_result->credit_card_details = new stdClass();
                    $user_result->credit_card_details->credit_cards = array();

                    foreach($client_credit_cards as $client_credit_card)
                    {
                        $ocreditcard = new stdClass();
                        $ocreditcard->id = $client_credit_card->id;
                        $ocreditcard->last_digits = $client_credit_card->last_digits;
                        $user_result->credit_card_details->credit_cards[] = $ocreditcard;
                    }
                }

                $client_direct_debits = $client->clientdirectdebits()->select('client_direct_debits.id', 'last_digits')->join('client_ezidebit_accounts', function($join) { $join->on('payable_id', '=', 'client_direct_debits.id') ->where('payable_type', '=', 'ClientDirectDebit');})->get();

                if(!empty($client_direct_debits) && count($client_direct_debits) > 0)
                {
                    $user_result->direct_debit_details = new stdClass();
                    $user_result->direct_debit_details->direct_debits = array();

                    foreach($client_direct_debits as $client_direct_debit)
                    {
                        $odirectdebit = new stdClass();
                        $odirectdebit->id = $client_direct_debit->id;
                        $odirectdebit->last_digits = $client_direct_debit->last_digits;
                        $user_result->direct_debit_details->direct_debits[] = $odirectdebit;
                    }
                }

                $user_result->service = 0;
                $user_result->limited = 1;
                $user_result->products = array();

                $postcode = $this->models->load('Postcode')->where('postcode', trim( $client->address_postcode ))->where('suburb', trim($client->address_city))->where('state', trim( $client->address_state ))->first();

                if(object_exists($postcode))
                {                
                    if($postcode->service) $user_result->service = 1;
                    $products = array();
                    $zone = $postcode->zone($this->models);

                    if(object_exists($zone))
                    {
                        if($zone->we_service) $user_result->limited =0; 
                        $products = $this->available_products($postcode, $zone);
                    }

                    foreach($products as $product) $user_result->products[] = $product;
                }
            }
            else
            {
                $client = false;

                if($user->matched_client_id > 0) {
                    $client = $this->models->load('Client', $user->matched_client_id, false);

                    if(object_exists($client)) {
                         $user_result->client_match = 1;
                    }
                }

                if( !object_exists($client) && $this->client_details_exists('crn', $user->crn ) !== false ) { $client = $this->client_details_exists('crn', $user->crn ); $user_result->client_match = 1; } 
                               
                if( !object_exists($client) && $this->client_details_exists('driver_license', $user->license ) !== false ) { $client = $this->client_details_exists('driver_license', $user->license ); $user_result->client_match = 1; }

                if( !object_exists($client))
                {
                    if(trim($user->medicare) !='')
                    {
                        $name_first = $user->name_last;
                        $medicare_card_reference = $user->medicare_card_reference;
                        $client = $this->models->load('Client');
                        $client = $client->where('medicare_card', $user->medicare)->where(function($query) use ($name_first, $medicare_card_reference) { $query->where(function($query) use ($medicare_card_reference) { $query->where('medicare_card_reference', $medicare_card_reference)->where(DB::raw('COALESCE(medicare_card_reference, "")'), '!=', ''); })->orWhere(function($query) use ($name_first) {  $query->where(DB::raw('COALESCE(medicare_card_reference, "")'), '=', '')->where('name_first', $name_first); }); } )->first(); 
                        if(object_exists($client)) $user_result->client_match = 1;
                    }
                }

                if(object_exists($client))
                {
                    $this->confirmation_methods($client, $user_result);

                    if($user->matched_client_id != $client->id) {
                        $user->matched_client_id = $client->id;
                        $user->save();
                    }
                }
            }

            return $user_result;
        }

        return $this->fault($this->invalid_user_message, 'InvalidUserException');
    }

    public function client_details_exists($field, $value)
    {
        if($value != '')
        {
            $client = $this->models->load('Client');
            $client = $client->where($field, $value)->first();
            if(object_exists( $client )) {
                return $client;
            }
        }

        return false;
    }

    public function user_details_exists($field, $value)
    {
        if($value != '')
        {
            $user = $this->models->load('User');
            $user = $user->where($field, $value)->first();
            if(object_exists( $user )) return true;
        }

        return false;
    }
}