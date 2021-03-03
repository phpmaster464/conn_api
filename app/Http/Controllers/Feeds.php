<?php namespace App\Http\Controllers;

use DB;
use App\Models\ContractTerm;
use App\Models\Product;
use App\Http\PublicControllers\PublicController;

class Feeds extends PublicController {

	public function __construct()
	{
		parent::__construct();
        $this->middleware('ipcheck');
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */

	public function getProducts()
	{		
        $contract_terms = ( new ContractTerm )->where('active', 1)->get();

        $products = ( new Product() )->select('products.id',  'products.model_number', DB::raw('(CONCAT("per week over a 24 month contract.")) as price_value'), 'product_type_id', 'products.make', 'products.barcode', 'product_types.slug', 'products.wpname', 'products.wpname_url', DB::raw('products.wpimage_url as wpimage_url'), 'products.suggested_rental', DB::raw('description_html as wpcontent'), 'payment')
                               ->join('product_types', 'products.product_type_id', '=', 'product_types.id')
                               ->where('wpname', '!=', '')
                               ->where(DB::raw('(SELECT COUNT(*) FROM product_includes WHERE product_includes.product_id = products.id)'), '>', 0)
                               ->orderBy('wpname')
                               ->get();

        foreach($products as $product) {

            $prices = array();

            foreach($contract_terms as $contract_term) {
                $prices[] = array('term'=>$contract_term->term, 'amount'=>round(payment_frequency_total(2, $contract_term->term, $product->suggested_rental), 2, PHP_ROUND_HALF_UP), 'field'=>$contract_term->wp_field);
            }

            $product->wpcontent = htmlentities( $product->wpcontent  );
            $product->images = $product->productimages()->select('title', 'image_url')->orderBy('id', 'asc')->get();
            $product->features = $product->productfeatures()->select('feature', 'order')->orderBy('order')->get();
            $product->specifications = $product->productspecifications()->select('attribute', 'value', 'order')->orderBy('order')->get();
            $product->prices = $prices;
        }

        return $products->toJson();
	}

    public function getProductTypes()
    {       
        return $this->models->load('ProductType')->select('id', 'type', 'slug', 'type_image_url')->where('show_on_website', 1)->get()->toJson();
    }

    public function getFeedback()
    {
        $company_detail = $this->models->load('CompanyDetail');
        $company_detail = $company_detail->select('feedback_product_mutiple_payments', 'feedback_product_one_payment')->first();
        return '{"feedback" : '.$company_detail->toJson().'}';
    }
}
