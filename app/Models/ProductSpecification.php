<?php namespace App\Models;

	class ProductSpecification extends MyModel {

		protected $connection = 'mysql2';

		public $timestamps = true;

		public function __construct(array $attributes=array())
		{
			parent::__construct($attributes);
		}

		public function identify()
		{
			return $this->type;
		}

		public function type()
		{
			return 'Product specification';
		}

		public function product()
		{
			return $this->belongsTo('App\Models\Product', 'product_id');
		}
	}