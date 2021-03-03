<?php namespace App\Http\Controllers;

use MyUpload, File, Input, Log;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\PublicControllers\PublicController;
use App\Services\Models as Models;
use App\RestWebServices\Database as DatabseRequest;

class Statements extends Controller {

	private $models;

	private $myupload;

	public function __construct()
	{
		$this->models = new Models();
		date_default_timezone_set(config('app.timezone'));
		$this->myupload = myUpload(config('general.storage'), config('general.disk'));
	}

	public function getEndpoint() {
	    $referrerCode = Input::get('referrerCode');
	}

	public function postIndex()
	{	
		if(config('app.debug')) {
			\Log::info('bank statement reference '.Input::get('referrerCode'));
		}

	    if(Input::has('referrerCode') || Input::has('name'))
	    {
		    $referrerCode = Input::get('referrerCode');
		    $name = Input::get('name');
		    $fileCount = Input::get('fileCount');

		    $files = array();
		    
		    for($x=1; $x<=$fileCount; $x++) {
		    	$files[] = Input::file('file'.$x);
		    }

		    if(starts_with($referrerCode, 'ESSN-')) { 

		    	$this->handle_client_documents($referrerCode, $name, $files, 'ESSN-'); 

		    } else if(starts_with($referrerCode, 'ESSE-')) { 

		    	$this->handle_contract_documents($referrerCode, $name, $files, 'ESSE-'); 

		    } else if(starts_with($referrerCode, 'ESRV-')) { 

		    	$this->handle_test_client_documents($referrerCode, $name, $files, 'ESRV-');

		    } else if(starts_with($referrerCode, 'ESRW-')) {

		    	$this->handle_test_contract_documents($referrerCode, $name, $files, 'ESRW-');

		    } else if(starts_with($referrerCode, 'ESSS-')) { 

		    	if(config('app.debug')) {
		    		
		    		$this->handle_test_client_documents($referrerCode, $name, $files, 'ESSS-');
		    	
		    	} else {

		    		$this->handle_client_documents($referrerCode, $name, $files, 'ESSS-'); 
		    	}

		    } else if(starts_with($referrerCode, 'ESSR-')) {

		    	if(config('app.debug')) {

		    		$this->handle_test_contract_documents($referrerCode, $name, $files, 'ESSR-');

		    	} else {

		    		$this->handle_contract_documents($referrerCode, $name, $files, 'ESSR-'); 
		    	}
		    }
		    else if(starts_with($referrerCode, 'RRSA-')) {

		    	if(config('app.debug')) {
		    		
		    		$this->handle_test_client_documents($referrerCode, $name, $files, 'RRSA-');
		    	
		    	} else {

		    		$this->handle_client_documents($referrerCode, $name, $files, 'RRSA-'); 
		    	}
		    }
		    else if(starts_with($referrerCode, 'RRSS-')) {

		    	if(config('app.debug')) {

		    		$this->handle_test_contract_documents($referrerCode, $name, $files, 'RRSS-');

		    	} else {

		    		$this->handle_contract_documents($referrerCode, $name, $files, 'RRSS-'); 
		    	}
		    }
    	}
	}

	public function handle_test_client_documents($referrerCode, $name, $files, $prefix)
	{
		$client_id = str_replace($prefix, '', $referrerCode); 
		
		$client = $this->models->load('Client', $client_id);

		if(object_exists($client)) {

			foreach($files as $file) {

	    		$object = $this->models->load('ClientDocument');
	    		$object->date_added = myToday();
	    		$object->client_id = $client->id;
	    		$this->upload_files($file, $object);
    		}
		}
	}

	public function handle_client_documents($referrerCode, $name, $files, $prefix)
	{
		$client_id = str_replace($prefix, '', $referrerCode); 
		
		$client = $this->models->load('Client', $client_id);

		if(object_exists($client)) {

			foreach($files as $file) {

	    		$object = $this->models->load('ClientDocument');
	    		$object->date_added = myToday();
	    		$object->client_id = $client->id;
	    		$this->upload_files($file, $object);
    		}
    		
		} else { 
			$this->handle_failed_documents($referrerCode, $name, $files); 
		}
	}

	public function handle_test_contract_documents($referrerCode, $name, $files, $prefix)
	{
		$client = false;

	    $contract_id = str_replace($prefix, '', $referrerCode); 
	    
	    $ocontract = $this->models->load('Contract', $contract_id);

	    if(object_exists($ocontract)) {
	    	$client = $this->models->load('Client', $ocontract->client_id);
	    }

	    if(object_exists( $client )) {

	    	$contracts = $client->contracts()->orderBy('more_info', 'desc')->orderBy('id', 'desc')->get();

	    	$count = 1;

	    	foreach($contracts as $contract) {

	    		if($contract->need_documents() || $count == 1) {

				    if(count($files) > 0) {

					    if(object_exists($contract)) {

				    		foreach($files as $file) {

					    		$object = $this->models->load('ContractDocument');
					    		$object->date_added = myToday();
					    		$object->contract_id = $contract->id;
					    		$this->upload_files($file, $object);
				    		}

				    		$contract->more_info = 0;
				            $contract->save();
				            
				    	} else {
				    		
				    		$this->handle_failed_documents($referrerCode, $name, $files); 
				    	}
			    	}
		    	}

		    	$count++;
	    	}
    	}
    	else { $this->handle_failed_documents($referrerCode, $name, $files); }

    	if(object_exists($client) && object_exists($ocontract)) {
	    	(new DatabseRequest(config('services.crm.host')))->predict($ocontract); 
    	}
	}

	public function handle_contract_documents($referrerCode, $name, $files, $prefix)
	{
		$client = false;
	    
	    $contract_id = str_replace($prefix, '', $referrerCode);

	    $ocontract = $this->models->load('Contract', $contract_id);

	    if(object_exists($ocontract)) {
	    	$client = $this->models->load('Client', $ocontract->client_id);
	    }

	    if(object_exists( $client )) {

	    	$contracts = $client->contracts()->orderBy('more_info', 'desc')->orderBy('id', 'desc')->get();

	    	$count = 1;

	    	foreach($contracts as $contract) {

	    		if($contract->need_documents() || $count == 1) {

				    if(count($files) > 0) {

					    if(object_exists($contract)) {

				    		foreach($files as $file) {

					    		$object = $this->models->load('ContractDocument');
					    		$object->date_added = myToday();
					    		$object->contract_id = $contract->id;
					    		$this->upload_files($file, $object);
				    		}

				    		$contract->more_info = 0;
				            $contract->save();
				            
				    	} else {
				    		
				    		$this->handle_failed_documents($referrerCode, $name, $files); 
				    	}
			    	}
		    	}

		    	$count++;
	    	}
    	}
    	else { $this->handle_failed_documents($referrerCode, $name, $files); }

    	if(object_exists($client) && object_exists($ocontract)) {
	    	(new DatabseRequest(config('services.crm.host')))->predict($ocontract); 
    	}
	}

	public function handle_failed_documents($referrerCode, $name, $files)
	{
		$failed_contract_document = $this->models->load('FailedContractDocument');
		$failed_contract_document->date_added = myToday();
		$failed_contract_document->name = $name;
		$failed_contract_document->referrer_code = $referrerCode;
		$failed_contract_document->save();

		foreach($files as $file) 
		{
			$object = $this->models->load('FailedContractDocumentFile');
			$object->failed_contract_document_id = $failed_contract_document->id;
			$this->upload_files($file, $object);
		}
	}

	public function upload_files($file, $object)
	{
      if(File::exists($file))
      {
        $object->file = $this->myupload->upload($file, 'contract-documents', $file->getClientOriginalName());
        $object->save(); 

        $upload = findUpload($object->file);

        if(object_exists($upload)) {

            $object->upload_id = $upload->id;
            $object->save();
        }
      }
	}
}