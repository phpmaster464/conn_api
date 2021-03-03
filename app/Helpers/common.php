<?php
use App\Models\Option;

function object_exists($object)
{
	if(!empty($object) && $object->exists) return true;
	return false;
}

function my_validate_email($email)
{
	return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function create_hash($length='')
{
    if($length == '') $length = 50;
    $hash = substr(md5(uniqid(mt_rand(0, 120000))), 0, $length);
    return $hash;
}

function clean_value($value ='')
{
    $value = trim($value);
    $replacements = array('‘' => "'", '’' => "'", '“' => '"', '”' => '"', '`' => "'",'`' => "'",'–' => '-','[removed]' => ''); 
    $config = HTMLPurifier_Config::createDefault();
    $config->set('Cache.SerializerPath', storage_path('app/cache/htmlpurifier'));
    $purifier = new HTMLPurifier($config);
    $value = str_replace(array_keys($replacements), array_values($replacements), $value);
    $clean_html = $purifier->purify($value);
    return $clean_html;
}

function sms_message($message, $number) { }

function write_file_update($path, $data, $mode = 'wb')
{
    if ( ! $fp = @fopen($path, $mode)) return FALSE;
    flock($fp, LOCK_EX);
    fwrite($fp, $data);
    flock($fp, LOCK_UN);
    fclose($fp);
    return TRUE;
}

function option($key)
{
	$option = new Option();
	$option = $option->where('key', $key)->first();
	if(object_exists($option)) return $option->value;
	return '';
}

function payment_frequency_days($frequency=12)
{
    if($frequency == 1) return 52;
    else if($frequency == 3) return 12;
    return 26;     
}

function money($money)
{
    if(is_numeric($money)) return '$'.number_format($money, 2);
    else return $money;
}

function payment_frequency_total($payment_frequency, $contract_term, $actual_rental)
{
    if($contract_term <=0) return 0;
    
     switch ($payment_frequency)
     {
            case 1:
              return ((($actual_rental /2) * ((12/$contract_term) + 1)/2));
              break;
            case 3:
              return (((($actual_rental /2) * ((12/$contract_term) + 1)) * 26) /12);
              break;
            case 2:
              return (($actual_rental /2) * ((12/$contract_term) + 1));
              break;
            default:
              return (($actual_rental /2) * ((12/$contract_term) + 1));
     }

     return 0;
}

function client_ip()
{
  return Request::ip();
}

function myUpload($storage='', $disk='')
{
    return new MyUpload($storage, $disk);
}

function findUpload($hash) {

    return ( new \App\Models\Upload )->where('hash', $hash)->first();
}