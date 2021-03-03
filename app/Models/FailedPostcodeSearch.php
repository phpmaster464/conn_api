<?php namespace App\Models;

	class FailedPostcodeSearch extends MyModel {
		
		protected $table = 'failed_postcode_searches';
		
		public function __construct(array $attributes=array())
		{
			parent::__construct($attributes);
		}
	}