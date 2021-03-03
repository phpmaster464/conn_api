<?php namespace App\Models;
	
	use App\Models\Product as Product;

	class Contract extends MyModel
	{
		protected $connection = 'mysql2';

		public $timestamps = true;

		public function __construct(array $attributes=array())
		{
			parent::__construct($attributes);
		}

		public function contractassessment()
		{
			return $this->hasOne('App\Models\ContractAssessment');
		}

		public function contractpayments()
		{
			return $this->hasMany('App\Models\ContractPayment');
		}

		public function contractproducts()
		{
			return $this->hasMany('App\Models\ContractProduct');
		}

		public function contractrefunds()
		{
			return $this->hasMany('App\Models\ContractRefund');
		}

		public function contractadditionalcosts()
		{
			return $this->hasMany('App\Models\ContractAdditionalCost');
		}

		public function terminations()
		{
			return $this->hasMany('App\Models\Termination');
		}

		public function contractdocuments()
		{
			return $this->hasMany('App\Models\ContractDocument');
		}

		public function contractpaymentmethods()
		{
			return $this->hasMany('App\Models\ContractPaymentMethod');
		}

		public function contracttype()
		{
			return $this->belongsTo('App\Models\ContractType', 'contract_type_id');
		}

		public function client() {
			return $this->belongsTo('App\Models\Client', 'client_id');
		}

		public function balance($received, $receivables)
		{	
	        return ($receivables - $received);
		}

		public function can_rollover($balance_owing)
		{
			if($this->contract_status == 'Active' && $balance_owing < 1000 && check_date($this->date_signed) && date_difference_update($this->date_signed, myToday()) / 30 >= 3 )
			{
				return true;
			}

			return false;
		}

		public function rollover_description()
		{
			$product_details = 'ROLLOVER OF Contract : '.$this->id.' - ';

			foreach($this->contractproducts()->get() as $contract_product)
			{
				$product = Product::find($contract_product->product_id);

				if(trim($product->code) !='ROLLOVER')
				{
			    	if($contract_product->description !='') $product_details .= trim($contract_product->description).' ';
			    	else if(!empty($product) && $product->exists) $product_details .= trim($product->describe()).' '; 
				}
			}

			return $product_details;
		}

		public function balanceproduct($balance_owing)
		{
			return round($balance_owing / ((12/12)*26),2);
		}

		public function calculate_rollover_cost($old_contract_term, $old_frequency, $new_contract_term, $new_frequency, $actual_rental)
		{
			return (($old_contract_term / 12) * $actual_rental * $old_frequency) / (($new_contract_term / 12) * $new_frequency);
		}

		public function need_documents()
		{
			$contract_documents_count = $this->contractdocuments()->count();
			return ( starts_with($this->contract_status, 'A') && ( $this->more_info || $contract_documents_count <= 0 )) ? 1 : 0;
		}
	}