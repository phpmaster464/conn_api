<?php namespace App\Models;

	class ClientIncome extends MyModel
	{
		protected $connection = 'mysql2';

		protected $table = 'client_income';

		public $timestamps = true;

		public function __construct(array $attributes=array())
		{
			parent::__construct($attributes);
		}

		public function url()
		{
			return 'income';
		}
	}