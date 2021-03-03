<?php namespace  App\SoapWebServices;
	
	use SoapClient;
	
	abstract class MySoapClient extends SoapClient
	{
		private $wsdl = null;
		private $options = array();
		private $version = SOAP_1_1;
		private $trace = true;
		private $namespaces = array();
		private $headers = array();

		public function __construct($wsdl=null, $options=array())
		{	
			parent::__construct($wsdl, $options);
		}

		public function get_version()
		{
			return $this->version;
		}

		public function set_version($version)
		{
			$this->version = $version;
		}

		public function set_options($options)
		{
			$this->options = $options;
		}

		public function options_setup()
		{
			$options = array();
			$options['trace'] = $this->trace;
			$options['soap_version'] = $this->version;
			return $options;
		}

		public function add_namespace($location, $namespace)
		{
			$this->namespaces[$location] = $namespace;
		}

		public function set_namespaces($namespaces)
		{
			$this->namespaces = $namespaces;
		}

		public function get_namespaces()
		{
			return $this->namespaces;
		}

		public function add_headers($header)
		{
			$this->headers[] = $header;
		}

		public function get_headers()
		{
			return $this->headers;
		}

	}