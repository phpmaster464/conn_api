<?php namespace App\Services;
use Illuminate\Validation\Validator; 
use Input, App\Services\Models;

class CustomValidator extends Validator {

    public function validateRequiredFile($attribute, $value, $parameters)
    {
      return true;
    }

    public function validateCleanInputs($attribute, $value, $parameters)
    {
        if(Input::has($attribute)) Input::merge(array( $attribute => clean_value(Input::get($attribute))));
        return true; 
    }

    public function validateProds($attribute, $value, $parameters)
    {
    	if(!Input::has('product_category_id')) return false;
    	if(!Input::has('range_id')) return false;
        $id = head($parameters, 0);
    	$model = new Models();
    	$product = $model->load('Product');
    	$product = $product->where('name', $value)->where('id', '!=', $id)->where('range_id', Input::get('range_id'))->where('product_category_id', Input::get('product_category_id'))->first();
    	if(object_exists($product)) return false;
     	return true;
    }
}
?>