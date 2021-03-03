<?php namespace App\Models;

	class DirectSms extends MyModel
	{
		protected $connection = 'mysql2';

		protected $table = 'directsms';

		public $timestamps = true;

		public function __construct(array $attributes=array())
		{
			parent::__construct($attributes);
		}
	}