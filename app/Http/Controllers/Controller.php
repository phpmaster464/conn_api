<?php

namespace App\Http\Controllers {

	use Illuminate\Foundation\Bus\DispatchesJobs;
	use Illuminate\Routing\Controller as BaseController;
	use Illuminate\Foundation\Validation\ValidatesRequests;
	use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

	class Controller extends BaseController
	{
	    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
	}
}

namespace App\Http\PublicControllers {

	use App\Services\Models as Models;
	use App\Http\Controllers\Controller;

	abstract class PublicController extends Controller {

		protected $models;

		public function __construct ( )
		{
			$this->models = new Models();
		}
	}
}

namespace App\Http\PrivateControllers {

	use App\Http\Controllers\Controller;
	
	abstract class PrivateController extends Controller {

		public function __construct ( )
		{
			parent::__construct();
		}
	}
}