<?php namespace App\Models;

	class ClientEzidebitAccount extends MyModel {

		protected $connection = 'mysql2';

		public $timestamps = true;

		function identify() { return $this->id; }

		function type() { return ucwords(str_replace('_', ' ', get_class($this))); }

	}