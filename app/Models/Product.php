<?php namespace App\Models;

	class Product extends MyModel {
		
		protected $connection = 'mysql2';

		public $timestamps = true;

		public function __construct(array $attributes=array())
		{
			parent::__construct($attributes);
		}

		public function describe()
		{
			return $this->make.' '.$this->model_number.' '.$this->description;
		}

		public function productimages()
		{
		      return $this->hasMany('App\Models\ProductImage');
		}

		public function productfeatures() {
    		return $this->hasMany('App\Models\ProductFeature', 'product_id');
  		}

		public function productspecifications() {
    		return $this->hasMany('App\Models\ProductSpecification', 'product_id');
  		}
	}