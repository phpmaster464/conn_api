<?php namespace App\Services\Api;

use stdClass, SoapFault, MyAuth, Input, Log, DB;

use MyUpload;
use App\Services\Models;
use App\Events\NewEnquiry;
use Illuminate\Support\Facades\Auth;
use App\Responders\DatabaseGuzzleResponder as GuzzleResponder;

class Api  {

	public $models;
    private $contract_limit = 0;
    protected $excluded_contract_types = ['Rental'];
	protected $expired_message = 'Token has expired';
	protected $authentication_failed_message = 'Incorrect username and password combination';
	protected $parameters_failed_message = 'Required verification details not found';
	protected $invalid_user_message = 'User matching provided details not found';
	protected $invalid_client_message = 'Client matching provided details not found';
    protected $client_already_exists_message = 'An account matching provided details has been found on our system';
	protected $invalid_contract_message = 'Contract matching provided details not found';
	protected $user_already_exists_message = 'User matching provided details already exists';
	protected $invalid_country_message = 'Provided country not found on system';
	protected $unserviceable_address_message = 'We do not have this suburb and postcode combination in our serviceable areas. Please check the spelling and postcode and try again if they were entered incorrectly. If the spelling and numbers are correct then unfortunately we do not currently service your area.';
    protected $max_contracts_message = 'Please note you have reached the maximum number of contracts you can add over a 24 hours period';
    protected $valid_password_message = 'Please enter a valid password. Must be a least 6 characters long. Must contain at least 1 number and 1 letter. May contain any of these characters: !@#$%';
    protected $product_not_available_message = "Unfortunately, your cart contains products not available at your address";

	public function __construct () 
    {
       	$this->models = new Models();  	
        $this->contract_limit = config('services.crm.limit');
    }

    public function guzzle()
    {
        return new GuzzleResponder();
    }

    public function get_contract_limit() {
        return $this->contract_limit;
    }

    public function model()
    {
        return $this->models;
    }

    public function get_exclude_contract_type_list()
    {
        return $this->models->load('ContractType')->whereIn('name', $this->excluded_contract_types)->pluck('id', 'id')->all();
    }

    public function find_postcode($suburb, $vpostcode, $state='', $save_failed_postcode=true)
    {
        $first_three_suburb = substr($suburb, 0, 3);
        
        $first_postcode = substr($vpostcode, 0, 3);

        $postcode = $this->models->load('Postcode')->where('suburb', $suburb)->where('postcode', $vpostcode)->first();

        if(!object_exists( $postcode ) )
        {
            $postcode = $this->models->load('Postcode')->where('suburb', $suburb)->where('postcode', 'LIKE', $vpostcode.'%')->first();
        }

        if(!object_exists( $postcode ) )
        {
            $postcode = $this->models->load('Postcode')->where('suburb', 'LIKE', $first_three_suburb.'%')->where('postcode', $vpostcode)->first();
        }

        if(!object_exists( $postcode ) && $save_failed_postcode)
        {
            $failed_postcode_search = $this->models->load('FailedPostcodeSearch');
            $failed_postcode_search->date_added = myToday();
            $failed_postcode_search->suburb = $suburb;
            $failed_postcode_search->postcode = $vpostcode;
            $failed_postcode_search->state = $state;
            $failed_postcode_search->save();
            return false;
        }

        return $postcode;
    }

    public function enquiry($message, $id, $type)
    {
    	$enquiry = $this->models->load('Enquiry');
    	$enquiry->enquiry = $message;
    	$enquiry->date_added = myToday();
    	$enquiry->messageable_id = $id;
    	$enquiry->messageable_type = $type;
    	$enquiry->save();
    	return 0;
    }

    public function available_products($postcode, $zone)
    {
        $products = array();

        if($zone->we_service) {
         
            $products = $this->models->load('Product')->where(DB::raw('(SELECT COUNT(*) FROM product_includes WHERE product_includes.product_id = products.id AND product_includes.available = 1 AND product_includes.zone_id = ' . $zone->id . ')'), '>', 0)->pluck('products.id', 'products.id')->all();
        
        } else {
            
            $zone_id = $zone->id;

            $postcode_supplier_locations = $this->models->load('PostcodeSupplierLocation');

            $found_suppliers = $postcode_supplier_locations->select('postcode_supplier_locations.id', 'supplier_locations.supplier_id')
                                                           ->join('supplier_locations', 'postcode_supplier_locations.supplier_location_id', '=', 'supplier_locations.id')
                                                           ->where('postcode_id', $postcode->id)
                                                           ->where('distance', '<=', config('general.delivery_distance_limit'))
                                                           ->groupBy('supplier_id')
                                                           ->pluck('supplier_id', 'supplier_id')->all();
            
            $products = $this->models->load('Product')
                                     ->select('products.id')
                                     ->join('product_includes', function($query) use ($zone_id) { $query->on('product_includes.product_id', '=', 'products.id') ->where('product_includes.zone_id', '=', $zone_id);})
                                     ->where(DB::raw('(SELECT COUNT(*) FROM product_includes WHERE product_includes.product_id = products.id AND product_includes.available = 1 AND product_includes.zone_id = '.$zone->id.')'), '>', 0)
                                     ->where(function($query) use ( $found_suppliers ) {  $query->whereIn('product_includes.supplier_id', $found_suppliers)->orWhere('can_mail_out', 1); })
                                     ->pluck('products.id', 'products.id')->all();
        }

        $can_mail_out = $this->models->load('Product')->where('can_mail_out', 1)->where(DB::raw('(SELECT COUNT(*) FROM product_includes WHERE product_includes.product_id = products.id AND product_includes.available = 1)'), '>', 0)->pluck('products.id', 'products.id')->all();

        $products = array_merge($products, $can_mail_out);

        return $products;
    }

    /* NO LONGER REQUIRED USING PASSPORT */

    public function simple_auth()
    {
		$username = null;
		$password = null;	

		if (isset($_SERVER['PHP_AUTH_USER'])) 
		{
		    $username = trim($_SERVER['PHP_AUTH_USER']);
		    $password = trim($_SERVER['PHP_AUTH_PW']);
		} 
		else if (isset($_SERVER['HTTP_AUTHORIZATION'])) 
		{
        	if (strpos(strtolower($_SERVER['HTTP_AUTHORIZATION']),'basic')===0) list($username,$password) = explode(':',base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
		}

		if(Auth::guard('admin')->attempt(['username' => $username, 'password' => $password ])) {
            return true;
        }
                
    	return false;
    }

    public function token($email)
    {
    	$password_reset = $this->models->load('PasswordReset');
    	$password_reset->email = $email;
    	$password_reset->token = $token;
    	$password_reset->save();
    	return $password_reset->token;
    }

    public function hash( $user , $ip_address)
    {
    	$hash = create_hash(5);
    	$security_code = $this->models->load('SecurityCode');
    	$security_code->user_id = $user->id;
    	$security_code->token = 'EAR'.strtoupper( $hash );
    	$security_code->ip_address = $ip_address;
    	$security_code->save();
		return $security_code->token;
    }

    public function verify($parameters)
    {
    	if(!isset($parameters->hash) || !isset($parameters->email) || !isset($parameters->ip_address)) $this->fault('Required field not found', "InvalidParameterException");
  		return true;
    }

    public function verify_details($parameters)
    {
    	if(!isset($parameters->verify_details->hash) || !isset($parameters->verify_details->email) || !isset($parameters->verify_details->ip_address)) return false;
    	return true;
    }

    public function has_expired($hash)
    {
    	return MyAuth::has_expired($hash);
    }

    public function read_hash($hash, $ip_address)
    {
    	return MyAuth::decrypt_details($hash, $ip_address);
    }

    public function read($hash, $email, $ip_address)
	{
		$id = MyAuth::decrypt_details($hash, $ip_address);
		
		$user = $this->user($id, $email);
		
		if(object_exists($user)) 
		{
			$client = $this->client($user->client_id);
			if(object_exists($client)) return $client; 
		}

		return 0;
	}

	public function user($id, $email)
	{
		$user = $this->models->load('User');
		$user = $user->where('id', trim($id))->where('email', trim($email))->first();
		return $user;
	}

	public function client($id)
	{
		$client = $this->models->load('Client');
		$client = $client->where('id', $id)->first();
		return $client;
	}

	public function valid_password($password)
	{
		if (!preg_match('/^(?=.*\d)(?=.*[A-Za-z])[0-9A-Za-z!@#$%]{6,}$/', $password)) return false;
		return true;
	}

	public function fault($message, $exception, $fault='Server')
    {
        return new SoapFault($fault, $message, null, array('message'=>$message), $exception);
    } 

    public function populate_client( $client )
    {
    	$oclient = new stdClass();
        $oclient->client_id = $client->id;
        $oclient->default_payment_type = $client->default_type;
        $oclient->title = $client->title;
        $oclient->name_first = $client->name_first;
        $oclient->name_last = $client->name_last;
        $oclient->name_middle = $client->name_middle;
        $oclient->dob = $client->dob;
        $oclient->email = $client->email;
        $oclient->crn = $client->crn;
        $oclient->phone = $client->phone;
        $oclient->mobile = $client->mobile;
        $oclient->mobile_verified = $client->hasMobileBeenVerified();
        $oclient->license = $client->driver_license;
        $oclient->medicare = $client->medicare_card;
        $oclient->living_situation = $client->living_situation;
        $oclient->dependents = $client->dependents;
        $oclient->relationship = $client->relationship;
        $oclient->shared = $client->shared;
        $oclient->address_unit_number = $client->address_unit_number;
        $oclient->address_number = $client->address_number;
        $oclient->address_name = $client->address_name;
        $oclient->address_city = $client->address_city;
        $oclient->address_postcode = $client->address_postcode;
        $oclient->address_state = $client->address_state;
        $oclient->delivery_address_unit_number = $client->delivery_address_unit_number;
        $oclient->delivery_address_number = $client->delivery_address_number;
        $oclient->delivery_address_name = $client->delivery_address_name;
        $oclient->delivery_address_city = $client->delivery_address_city;
        $oclient->delivery_address_postcode = $client->delivery_address_postcode;
        $oclient->delivery_address_state = $client->delivery_address_state;
        $oclient->nlr_name_first = $client->nlr_name_first;
        $oclient->nlr_name_last = $client->nlr_name_last;
        $oclient->nlr_phone = $client->nlr_phone;
        $oclient->nlr_address_unit_number = $client->nlr_address_unit_number;
        $oclient->nlr_address_number = $client->nlr_address_number;
        $oclient->nlr_address_name = $client->nlr_address_name;
        $oclient->nlr_address_city = $client->nlr_address_city;
        $oclient->nlr_address_postcode = $client->nlr_address_postcode;
        $oclient->nlr_address_state = $client->nlr_address_state;
        $oclient->nlr_relationship = $client->nlr_relationship;
        $oclient->last_updated = $client->last_updated;
        $oclient->spending_value = $client->spending_value;
        $oclient->ezidebit_customer_id = $client->ezidebit_customer_id;

        $contracts = $client->contracts();

        //if(is_array($this->get_exclude_contract_type_list()) && count( $this->get_exclude_contract_type_list() ) > 0) {
            //$contracts = $contracts->whereNotIn('contract_type_id', $this->get_exclude_contract_type_list());
        //}  

        $contracts = $contracts->orderBy('id', 'desc')->get();

        $oclient->contracts = array();

        $active_contracts = 0;

        foreach($contracts as $contract)
        {
            if(starts_with($contract->contract_status, 'A')) $active_contracts++;
            $ocontract = $this->populate_contract($contract);
            $oclient->contracts[] = $ocontract;
        }

        $oclient->active_contracts = $active_contracts;

        $oclient->reached_max_contracts_in_period = $client->hasReachedMaxContractsInPeriod($this->get_contract_limit());

        /* LOAD THE CLIENT INCOME */

        $client_income = $client->clientincomes()->get();

        $oclient->client_income = array();

        foreach($client_income as $income)
        {
            $oclientincome = new stdClass();
            $oclientincome->id = $income->id;
            $oclientincome->type = $income->type;
            $oclientincome->frequency = $income->frequency;
            $oclientincome->amount = $income->amount;
            $oclient->client_income[] = $oclientincome;
        }

        /* LOAD THE CLIENT DEDUCTIONS */

        $client_deductions = $client->clientdeductions()->get();

        $oclient->client_deductions = array();

        foreach($client_deductions as $deduction)
        {
            $oclientdeduction = new stdClass();
            $oclientdeduction->id = $deduction->id;
            $oclientdeduction->type = $deduction->type;
            $oclientdeduction->frequency = $deduction->frequency;
            $oclientdeduction->amount = $deduction->amount;
            $oclient->client_deductions[] = $oclientdeduction;
        }

        $oclient->identifying_documents = array();

        $client_photos = $client->clientphotos()
        						->select('client_photos.id', 'client_photos.identification_type','uploads.file_name')
        						->join('uploads', 'client_photos.photo', '=', 'uploads.hash')
        						->orderBy('identification_type')
        						->get();

        foreach($client_photos as $client_photo)
        {
        	$oclientphoto = new stdClass();
        	$oclientphoto->name = $client_photo->file_name;
        	$oclientphoto->type = $client_photo->identification_type;
        	$oclient->identifying_documents[] = $oclientphoto;
        }

        return $oclient;
    }

    public function populate_contract( $contract )
    {
    	$refunds = $contract->contractrefunds()->sum('amount');
        $inital_received = $contract->contractpayments()->sum('amount');
        $received = $inital_received - $refunds;
        $additional = $contract->contractadditionalcosts()->sum('amount');
        $receivables = $contract->total_receivable + $additional;
        $balance = $contract->balance($received, $receivables);
        $product_balance = $contract->balanceproduct( $balance );

       	$default_payment_type_text = '';
        $contract_payment_type = $this->models->load('ContractPaymentType');
        $contract_payment_type = $contract_payment_type->where('payment_type', $contract->payment_type)->first();

        if(object_exists($contract_payment_type))
        {
        	$default_payment_type = $this->models->load('DefaultPaymentType', $contract_payment_type->default_payment_type_id);
        	if(object_exists($default_payment_type)) $default_payment_type_text = $default_payment_type->payment_type;
        }

        $ocontract = new stdClass();
        $ocontract->id = $contract->id;
        $ocontract->date = $contract->contract_date;
        $ocontract->timestamp = $contract->timestamp;
        $ocontract->status = $contract->contract_status;
        $ocontract->payment_type = $contract->payment_type;
        $ocontract->default_payment_type = $default_payment_type_text;
        $ocontract->contract_type = $contract->contracttype()->value('name');
        $ocontract->marketing_source = $contract->marketing_source;
        $ocontract->rental_rate = $contract->rental_rate;
        $ocontract->contract_term = $contract->contract_term;
        $ocontract->payment_frequency = $contract->payment_frequency;
        $ocontract->refunds = $refunds;
        $ocontract->additional_costs = $additional;
        $ocontract->total_received = $received;
        $ocontract->total_receivables = $receivables;
        $ocontract->payments_remaining = 0;
        $ocontract->balance_remaining = $balance;
        $ocontract->initial_receivables = $contract->total_receivable;
        $ocontract->authorised = $contract->authorised;
        $ocontract->need_documents = $contract->need_documents();

        $can_rollover = $contract->can_rollover($balance);
        $ocontract->rollover_details = new stdClass();
        $ocontract->rollover_details->can_rollover = $can_rollover;

       	$assessment = $contract->contractassessment()->first();

       	if(object_exists($assessment))
       	{
       		$ocontract->assessment = new stdClass();
       		$ocontract->assessment->result = (($assessment->failed ) ? 0 : 1 );
       	}

        $termination = $contract->terminations()->where('resolved', 0)->first();

        if(object_exists($termination))
        {
        	$ocontract->termination = new stdClass();
        	$ocontract->termination->term_type = $termination->term_type;
        	$ocontract->termination->user = ''; //$termination->user;
        }

        if( $can_rollover )
        {
            $ocontract->rollover_details->description = $contract->rollover_description();
            $ocontract->rollover_details->twelve_month_price = $contract->calculate_rollover_cost(12, payment_frequency_days(2), 12, payment_frequency_days(2), $product_balance );
            $ocontract->rollover_details->eighteen_month_price = $contract->calculate_rollover_cost(12, payment_frequency_days(2), 18, payment_frequency_days(2), $product_balance );
            $ocontract->rollover_details->twently_four_month_price = $contract->calculate_rollover_cost(12, payment_frequency_days(2), 24, payment_frequency_days(2), $product_balance );
        }

        $contract_products = $contract->contractproducts()->orderBy('id', 'desc')->get();
		$ocontract->contract_products = new stdClass();
		$ocontract->contract_products->contract_product = array();

		foreach($contract_products as $contract_product)
		{
			$product = $this->models->load('Product', $contract_product->product_id);

			if(object_exists( $product ))
			{
				$ocontract_product = new stdClass();
				$ocontract_product->product_id = $product->id;
				$ocontract_product->description = ( trim( $contract_product->description ) == '') ? $product->description : $contract_product->description;
				$ocontract_product->code = $product->code;
				$ocontract_product->delivered = $contract_product->delivered;
				$ocontract_product->price = $contract_product->actual_rental;
                $ocontract_product->retail_value = $contract_product->base_price;
                $ocontract_product->rrp = $contract_product->rrp;
				$contract_product_upgrades = $contract_product->contractproductupgrades()->join('product_type_upgrades', 'contract_product_upgrades.product_type_upgrade_id', '=', 'product_type_upgrades.id')->where('active', 1)->get();
				$ocontract_product->contract_product_upgrades = new stdClass();
				$ocontract_product->contract_product_upgrades->contract_product_upgrade = array();
                $ocontract_product->image_url = (($product->wpimage_url !== '') ? $product->wpimage_url : '');

				foreach($contract_product_upgrades as $contract_product_upgrade)
				{
					$ocontract_product_upgrade = new stdClass();
					$ocontract_product_upgrade->rate = $contract_product_upgrade->rate;
					$ocontract_product_upgrade->description = $contract_product_upgrade->description;
					$ocontract_product->contract_product_upgrades->contract_product_upgrade[] = $ocontract_product_upgrade;
				}

				$ocontract->contract_products->contract_product[] = $ocontract_product;
			}
		}

		$contract_payment_methods = $contract->contractpaymentmethods()->get();

		$ocontract->contract_payment_methods = new stdClass();
		$ocontract->contract_payment_methods->contract_payment_method = array();

		foreach($contract_payment_methods as $contract_payment_method)
		{
			$ocontract_payment_method = new stdClass();
			$ocontract_payment_method->id = $contract_payment_method->id;
			$ocontract_payment_method->relationship_id = $contract_payment_method->payable_id;
			$ocontract_payment_method->primary = $contract_payment_method->primary;
			$ocontract_payment_method->updated = $contract_payment_method->updated;
			$ocontract_payment_method->type = $contract_payment_method->payable_type;
			$ocontract->contract_payment_methods->contract_payment_method[] = $ocontract_payment_method;
		}

        return $ocontract;
    }

    public function confirmation_methods($client, &$user_result)
    {
    	$user_result->confirmation_methods = new stdClass();
        $user_result->confirmation_methods->methods = array();

        //SEND MORE VALUES
        if(my_validate_email($client->email))
        {
            $email_length = strlen( $client->email );
            $symbol_position = strpos($client->email, '@');
            $c_method = new stdClass();
            $c_method->method = 'Email';
            $c_method->value = substr_replace( str_pad( substr($client->email, 0, 4), $email_length, '*'), '@', $symbol_position, 1);
            $user_result->confirmation_methods->methods[] = $c_method;
        }

        if($client->mobile != '')
        {
            $mobile_length = strlen( $client->mobile );
            $c_method = new stdClass();
            $c_method->method = 'SMS';
            $c_method->value = str_pad( substr( str_replace(' ', '', $client->mobile), -3), $mobile_length, '*', STR_PAD_LEFT);
            $user_result->confirmation_methods->methods[] = $c_method;
        }

    }

    public function upload_driver()
    {
        $myupload = new MyUpload(config('general.storage'), config('general.disk'));
        return $myupload->createDriver();
    }

    public function upload_file($driver, $folder, $name, $mime, $size)
    {
        $myupload = new MyUpload(config('general.storage'), config('general.disk'));
        $disk = $myupload->getDisk();
        $hash = $myupload->createHash();
        $driver->directory($disk, $folder, $hash, $name);
        //$references = $driver->references($folder, $hash, $name);
        //$myupload->reference($hash, $name, $mime, $folder, $size, $references);
        $upload = $myupload->create($hash, $name, $disk, $folder, $mime, $size);
        return $hash;
    }

    public function file_details($driver, $folder, $name, $mime, $size, $hash, $tmp_name='')
    {
		$file_details = new stdClass();
		$file_details->filename = $name;
		$file_details->mime = $mime;
		$file_details->size = $size;
		//$file_details->path = $driver::path().'/'.$folder.'/'.$hash.'/'.$name;
		$file_details->temp_filename = $tmp_name;
		return $file_details;
    }
}