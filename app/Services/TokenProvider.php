<?php namespace App\Services;

use DB;
use Carbon\Carbon;
use Illuminate\Auth\Passwords\DatabaseTokenRepository as Token;

class TokenProvider extends Token {

	public function __construct ( )
	{
		parent::__construct( DB::connection(), config('auth.password.table'), config('app.key'), config('auth.password.expire', 60));
	}

	public function table()
	{
		return $this->connection->table($this->table);
	}

	public function date()
	{
		return new Carbon();
	}
}