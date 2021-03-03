<?php namespace App\Models;
	
	class CompanyDetail extends MyModel {

		protected $connection = 'mysql2';

		public $timestamps = true;

		public function identify()
		{
			return $this->company_name;
		}

		public function type()
		{
			return 'Company details';
		}

		public function letter_details()
		{
			$details = '';
			$details .= '<p style=\'font-size:10px;padding:2px;margin:0\'><strong>'.$this->company_name.'</strong> '.$this->address.' '.$this->city.' '.$this->state.' '.$this->postcode.'</p>';
			$details .= '<p style=\'font-size:10px;padding:2px;margin:0\'>enquiries@essential.net.au '.$this->freeCall_number.' '. $this->website.'</p>';
			//$details .=	'<p style=\'font-size:10px;padding:2px;margin:0\'>'.strtoupper($this->email).'</p>';
			return $details;
		} 
	}