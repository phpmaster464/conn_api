<?php namespace App\Models;

	use App\Models\VerifyingField;

	class Client extends MyModel
	{
		protected $connection = 'mysql2';

		public $timestamps = true;

		public function __construct(array $attributes=array())
		{
			parent::__construct($attributes);
		}

		public function contracts()
		{
			return $this->hasMany('App\Models\Contract');
		}
		
		public function clientincomes()
		{
			return $this->hasMany('App\Models\ClientIncome');
		}

		public function clientdeductions()
		{
			return $this->hasMany('App\Models\ClientDeduction');
		}

		public function clientcreditcards()
		{
			return $this->hasMany('App\Models\ClientCreditCard');
		}

		public function clientdirectdebits()
		{
			return $this->hasMany('App\Models\ClientDirectDebit');
		}

		public function clientvedaresults()
		{
			return $this->hasMany('App\Models\ClientDirectDebit');
		}

		public function clientverifieddetails()
		{
			return $this->hasMany('App\Models\ClientVerifiedDetail');
		}

		public function get_address_line1()
		{
			return (($this->address_unit_number != '') ? $this->address_unit_number.' / ' : '').$this->address_number.' '.$this->address_name;
		}

		public function clientphotos()
		{
			return $this->hasMany('App\Models\ClientPhoto');
		}

		public function hasReachedMaxContractsInPeriod($limit, $period = ' +24 hours') {

			$today = myToday();

			$today_plus_24_hours = date("Y-m-d H:i:s", strtotime( $today.$period ) );

			return ( ( $this->contracts()->whereBetween('created_at', [$today, $today_plus_24_hours])->count() >= $limit ) ? true : false );
		}

		public function hasMobileBeenVerified() {

			$mobile = trim( $this->mobile );

			if($mobile == '') {
				return true;
			}

			$mobile_verifying_field = ( new VerifyingField )->where('field', 'mobile')->first();

			if(object_exists($mobile_verifying_field)) { 

				if( $this->clientverifieddetails()->where('verifying_field_id', $mobile_verifying_field->id)->where('verified', 0)->where('cancelled', 0)->count() > 0 ) {

					return false;
				}	
			}

			return true;
		}

		public function convert_income($field='actual_amount')
		{
			$total =0;
			$income_values = $this->clientincomes()->get();

			foreach($income_values as $income_value)
			{
				if($income_value->frequency == 1)
				{
					$total = $total + ($income_value->$field * 2);
				}	
				else if($income_value->frequency == 3)
				{
					$total = $total + ($income_value->$field * 0.461538562);
				}
				else
				{
					$total = $total + $income_value->$field;
				}
			}

			return $total;
		}
	}