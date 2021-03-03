<?php namespace App\Http\Controllers;

use DB;
use stdClass;
use Carbon\Carbon;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\PublicControllers\PublicController;
use App\Services\HandleHealthCheck;
use App\Models\User;
use App\Models\Client;
use App\Models\UserContactAttempt;
use App\Services\Api\Api;
use App\Mailers\FollowUpMailer;
use App\Models\ContractApplicationDetail;
use App\Responders\DatabaseGuzzleResponder as Guzzle;

class Home extends PublicController {

	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */

	public function getIndex() {

        return view('welcome');
    }

    public function getUnsubscribe($email, $ip = '') {
    	$email = urldecode($email);

	    $client = ( new Client() )->where('email', $email)->first();

	    if(object_exists( $client )) {

	        \Log::info('unsubscribe '.$client->email);

	        $client->subscribed = 0;

	        $client->save();
	    }

	    return redirect('/');
    }

    public function getPopular() {

      set_time_limit(config('general.max_time_limit'));

      \Log::info('started emails');

	  $health_check = new HandleHealthCheck('c27e7fa9-eb64-4792-b928-5df4854cb51b');

	  $health_check->start();

	  $users = ( new User() )->select('id', 'client_id')->where(DB::raw('date_user_added'), '=', DB::raw('DATE_SUB(CURDATE(),INTERVAL 2 DAY)'))->get();

	  $found = array();

	  $mailer = new FollowUpMailer();

	  foreach($users as $user) {

	      $client = ( new Client() )->where('id', $user->client_id)->first();

	      if(object_exists($client)) {

	          if($client->contracts()->count() <= 0) {

	              $mailer->send_email($client->name_first, $client->email);
	              $user_contact_attempt = new UserContactAttempt();
	              $user_contact_attempt->user_id = $user->id;
	              $user_contact_attempt->type = 'No Contract';
	              $user_contact_attempt->date_added = myToday();
	              $user_contact_attempt->save();
	              \Log::info($client->name.' has been emailed');
	          }
	      }
	  }

	  \Log::info('ended emails');

	  $health_check->finished();
    }

    public function getUpdated() {

    	$auth = new EssAuth();
    	
	    $parameters = ( object ) Input::all();
	    
	    if($auth->simple_auth()) {

	      if( $auth->verify( $parameters ) ) {
	          $client = $auth->read($parameters->hash, $parameters->email, $parameters->ip_address);
	          if(object_exists($client)) return response()->json(['date' => $client->last_updated]);
	      }
	    }

	    return "NOTHINH";
    }

    public function getConfirm($email, $id, $ip) {

	    $email = urldecode($email);

	    $client = ( new Client() )->where('email', $email)->first();

	    if(object_exists( $client )) {

	      	$contract = $client->contracts()->where('id', $id)->first();

	      	if(object_exists($contract)) {

	        	\Log::info('contract id '.$contract->id.' has been signed');

		        if(!$contract->is_confirmed) {

		            $contract_application_detail = ( new ContractApplicationDetail() );
		            $contract_application_detail->contract_id = $contract->id;
		            $contract_application_detail->ip_address = $ip;
		            $contract_application_detail->timestamp = today_datetime();
		            $contract_application_detail->save();

		            if(!check_date($contract->date_signed)) {
		            	$contract->date_signed = myToday();
		            }

		            $contract->is_confirmed = 1;
		            $contract->save();

		            $parameters = new stdClass();
		            $parameters->contract_status = $contract->contract_status;

		            $guzzle = new Guzzle(); 
		            $guzzle->request('POST', 'contracts/edit/'.$contract->id, [ 'form_params'=>(array)$parameters ] );
		        }

		        return response(null, 200);
	      	}
	    }

	    return response(null, 404);
    }

    public function getReSign($email, $id, $ip) {

	    $email = urldecode($email);

	    $client = ( new Client() )->where('email', $email)->first();

	    if(object_exists( $client )) {

	      	$contract = $client->contracts()->where('id', $id)->first();

	      	if(object_exists($contract)) {

	        	\Log::info('contract id '.$contract->id.' has been re-signed');

		        if(!$contract->is_confirmed) {

		            $contract_application_detail = ( new ContractApplicationDetail() );
		            $contract_application_detail->contract_id = $contract->id;
		            $contract_application_detail->ip_address = $ip;
		            $contract_application_detail->timestamp = today_datetime();
		            $contract_application_detail->save();

		            if(!check_date($contract->date_signed)) {
		            	$contract->date_signed = myToday();
		            }

		            $contract->is_confirmed = 1;
		            $contract->re_sign = 0;
		            $contract->save();

		            $parameters = new stdClass();
		            $parameters->contract_status = $contract->contract_status;

		            $guzzle = new Guzzle(); 
		            $guzzle->request('POST', 'contracts/edit/'.$contract->id, [ 'form_params'=>(array)$parameters ] );
		        }

		        return response(null, 200);
	      	}
	    }

	    return response(null, 404);
    }
}