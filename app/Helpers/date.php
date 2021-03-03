<?php

function now_string()
{
	return time();
}

function today_string()
{
	return date('Y-m-d');
}

function myToday()
{
    return date('Y-m-d');
}

function today_datetime()
{
    return date('Y-m-d H:i:s');
}

function date_difference_year_update($date1, $date2)
{
    $d1 = new DateTime($date1);
    $d2 = new DateTime($date2);
    $diff12 = date_diff($d2, $d1);
    return $diff12->y;
}

function date_difference_update($date1, $date2)
{
    $d1 = new DateTime($date1);
    $d2 = new DateTime($date2);
    $diff12 = date_diff($d2, $d1);
    return $diff12->days;
}

 function check_date($date, $format='0000-00-00')
{  
    if($format == '0000-00-00')
    {
        if (!preg_match ("/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/", $date, $parts)) return false;
        $date = array_get($parts, 0); $year = array_get($parts, 1); $month = array_get($parts, 2); $day = array_get($parts, 3);
    }
    else if($format == '00-00-0000')
    {
        if (!preg_match ("/^([0-9]{2})-([0-9]{2})-([0-9]{4})$/", $date, $parts)) return false;
        $date = array_get($parts, 0); $year = array_get($parts, 3); $month = array_get($parts, 2); $day = array_get($parts, 1);
    }
    
    if($date == '0000-00-00' || trim($date) == '')
    {
        return false;
    }
    else if(strtotime($month.'/'.$day.'/'.$year) =='')
    {
        return false;
    }
    else if (!checkdate($month, $day, $year))
    {
        return false;
    }
    else
    {
        return true;
    }
}
