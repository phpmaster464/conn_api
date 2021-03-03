<?php namespace App\Models;

	class ClientVedaResult extends MyModel
	{
		protected $connection = 'mysql2';

		public $timestamps = true;

		public function __construct(array $attributes=array())
		{
			parent::__construct($attributes);
		}

		public function url()
		{
			return 'income';
		}

		public function client() {
			return $this->belongsTo('App\Models\Client', 'client_id');
		}
	}