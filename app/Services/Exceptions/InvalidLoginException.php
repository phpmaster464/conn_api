<?php namespace App\Services\Exceptions; 

use Exception;

class InvalidLoginException extends Exception {

    public function __construct ($message, $code = 0, Exception $previous = null) 
    {
       parent::__construct($message, $code, $previous);
    }
}