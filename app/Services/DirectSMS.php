<?php namespace App\Services;
require_once(app_path().'/Services/Directsms/sms_connection.php');

use sms_connection;

class DirectSms
{
    private $id ='';
	private $username='';
	private $password ='';
	private $error ='';
	private $conn = false;
	private $message = '';
	private $mobile_numbers = array();
	private $character_limit = 150;
	private $mobile_limit =100;

	function __construct($username='', $password='')
    {
    	$this->username = $username;
    	$this->password = $password;
    }

    public function join()
    {
    	$this->conn = new sms_connection(true);

		if($this->conn->is_error())
		{	
			$this->error = $this->conn->get_error();
			return false;
		}
		else
		{
		    return true; 
		}
    }

    public function connect()
    {
    	if(trim($this->username) =='' || trim($this->password) =='') return false;
 
    	if($this->conn->connect($this->username, $this->password))
    	{
    		return true;
    	}
    	else
    	{
    		$this->error = $this->conn->get_error();
    		return false;
    	}
    }

    public function getMaxSms()
    {
        return $this->mobile_limit;
    }

    public function getConnect()
    {
    	return $this->conn;
    }

    public function getError()
    {
    	return $this->error;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function setMessage($message)
    {
        $this->message = $message;
    }

    public function setMobiles($mobiles)
    {
    	$this->mobiles = $mobiles;
    }

    public function getMobiles()
    {
    	return $this->mobiles;
    }

    public function oneWayMessage()
    {
    	if(count($this->mobiles) > 0 && trim($this->message) !='')
    	{
    		return $this->conn->send_one_way_sms($this->message, $this->mobiles, $this->id);
    		if($this->conn->is_error()) $this->error = $this->conn->get_error();	
            return false;
    	}
    	else
    	{
    		if(count($this->mobiles) > 0) $this->error .= 'Please enter mobile(s)';
    		if(trim($this->message) !='') $this->error .= 'Please enter message';
    		return false;
    	}
    }


    public function twoWayMessage()
    {
        if(count($this->mobiles) > 0 && trim($this->message) !='')
        {
            return $this->conn->send_two_way_sms($this->message,$this->mobiles, $this->id);
            if($this->conn->is_error()) $this->error = $this->conn->get_error();
            return false;
            
        }
        else
        {
            if(count($this->mobiles) > 0) $this->error .= 'Please enter mobile(s)';
            if(trim($this->message) !='') $this->error .= 'Please enter message';
            return false;
        }
    }

    public function setUsername($username)
    {
    	$this->username = $username;
    }

    public function setPassword($password)
    {
    	$this->password = $password;
    }

    public function disconnect()
    {
    	$this->conn->disconnect();
    }

	}
?>