<?php namespace App\Services;

	class Models {
		
		public function load($model, $id=0, $expection=true)
		{		
			$model = 'App\Models\\'.$model;
			if($id == 0) return new $model;

			$object = $model::find($id);

			if(!object_exists($object)) return new $model;

			return $object;
		}
	}