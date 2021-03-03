<?php namespace App\Models;

	class ContractProduct extends MyModel {
		
		protected $connection = 'mysql2';

		protected $fillable = ['contract_id', 'product_id', 'delivered', 'actual_rental', 'base_price', 'description'];

		public $timestamps = true;

		public function __construct(array $attributes=array())
		{
			parent::__construct($attributes);
		}

		public function contractproductupgrades()
		{
			return $this->hasMany('App\Models\ContractProductUpgrade');
		}
	}