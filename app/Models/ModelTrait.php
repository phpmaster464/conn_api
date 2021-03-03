<?php namespace App\Models;

	trait ModelTrait
	{
		function identify() { return $this->id; }

		function type() { return ucwords(str_replace('_', ' ', class_basename($this))); }
	}