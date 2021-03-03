<?php namespace App\Models;

class ProductFeature extends MyModel {

	protected $connection = 'mysql2';

	public $timestamps = true;

	public function __construct(array $attributes=array())
	{
		parent::__construct($attributes);
	}

	public function identify()
	{
		return $this->feature;
	}

	public function type()
	{
		return 'Product feature';
	}

	public function product()
	{
		return $this->belongsTo('App\Models\Product', 'product_id');
	}
}