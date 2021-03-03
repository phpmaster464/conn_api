<?php namespace App\Models;

	class Postcode extends MyModel {
		
		protected $connection = 'mysql2';

		public $timestamps = true;

		public function __construct(array $attributes=array())
		{
			parent::__construct($attributes);
		}

		public function zone($models)
		{
			$zone = false;
			$region = false;
			$municipality = $models->load('Municipality')->where('id', $this->municipality_id)->first();
	        if(object_exists( $municipality )) $region = $models->load('Region')->where('id', $municipality->region_id)->first();
	        if(object_exists( $region )) $zone = $models->load('Zone')->where('id', $region->zone_id)->first();
	        if(object_exists( $zone )) return $zone;
	        return false;
		}
	}