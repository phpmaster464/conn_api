<?php namespace App\Models;

	class ProductType extends MyModel {
		
		protected $connection = 'mysql2';

		public $timestamps = true;

		public function __construct(array $attributes=array())
		{
			parent::__construct($attributes);
		}
	}
