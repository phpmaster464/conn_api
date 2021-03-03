<?php namespace App\Models;

class ClientVerifiedDetail extends MyModel {

	protected $connection = 'mysql2';

	protected $fillable = ['client_id', 'verifying_field_id', 'value', 'token', 'verified', 'cancelled'];

	public $timestamps = true;

	public function __construct($attributes = array()) {
		parent::__construct($attributes);
	}

	public function getType() {
		return 'Client verified detail';
	}
}