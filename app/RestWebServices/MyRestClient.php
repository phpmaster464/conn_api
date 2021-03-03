<?php namespace  App\RestWebServices; 
	
	use Log, stdClass;
	use GuzzleHttp\Client as GuzzleHttp; 
	use GuzzleHttp\Exception\ClientException;
	use GuzzleHttp\Exception\ServerException;
	use GuzzleHttp\Exception\RequestException;

	class MyRestClient
	{
		private $type ='';
		private $url = '';
		private $parameters = array();
		private $error_message = '';
		private $errors = array();
		private $guzzle = '';
		private $result = '';

		public function __construct()
		{
			$this->guzzle = new GuzzleHttp();
		}

		public function add_parameter($key, $parameter)
		{
			$this->parameters[$key] = $parameter;
		}

		public function request($type, $url)
		{	
			try
			{
				$response = $this->guzzle->request($type, $url, $this->parameters);
				$this->result = $response->getBody()->getContents();
			}
		    catch(ClientException $ex)
		    {
		    	$response = json_decode($ex->getResponse()->getBody(true));

		    	if(is_object($response) && isset($response->message))
		    	{
		    		$this->error_message = $response->message;
		    		
		    		if(isset($response->details))
		    		{
		    			if(isset($response->details->errors) && is_array($response->details->errors))
		    			{
		    				$this->errors = $response->details->errors;
		    			}
		    		}
		    	}
		    }
		    catch(RequestException $ex)
		    {
		    	$error = new stdClass();
			    $error->field = 'Server down';
			    $error->reason = 'Server down';
			    $error->message = $ex->getMessage();
		    	$this->errors[] = $error;
		    }
		    catch(ServerException $ex)
		    {
		    	$error = new stdClass();
			    $error->field = 'Server down';
			    $error->reason = 'Server down';
			    $error->message = $ex->getMessage();
		    	$this->errors[] = $error;
		    }
		    catch(Exception $ex)
		    {
		    	$error = new stdClass();
			    $error->field = 'Server down';
			    $error->reason = 'Server down';
			    $error->message = $ex->getMessage();
		    	$this->errors[] = $error;
		    }
		}

		public function has_errors()
		{
			return (count($this->errors) > 0 ) ? 1 : 0;
		}

		public function result()
		{
			return $this->result;
		}

		public function errors()
		{
			return $this->errors;
		}
	}