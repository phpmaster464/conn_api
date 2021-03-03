<?php namespace App\Messengers;

use App\Services\Models;

abstract class Messenger
{
	protected $message ='';
	protected $id ='Essential';
	protected $numbers = array();
	protected $models;
	protected $remove_items = array(' ');

	public function __construct()
	{
		$this->models = new Models();
	}

	public function set_numbers($numbers)
	{
		$this->numbers = $numbers;
	}

	public function add_number($number)
	{
		$this->numbers[] = str_replace($this->remove_items, '', $number);
	}

	public function set_id($id)
	{
		$this->id = $id;
	}

	public function set_message( $message )
	{
		$this->message = $message;
	}
}

?>