<?php namespace App\Models;

	use Illuminate\Database\Eloquent\Model;
	use App\Services\Models;
	use App\Models\ModelTrait as ModelTrait;

	class MyModel extends Model
	{
		use ModelTrait;
		
		public $timestamps = false;
		
		public function __construct(array $attributes=array())
		{
			parent::__construct($attributes);
		}

		function identify() { return $this->id; }

		function type() { return ucwords(str_replace('_', ' ', class_basename($this))); }
	}