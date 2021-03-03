<?php namespace App\Services;

use Session, Redirect, Str, Route, Log, Config;

class Feedback {

    private static $messages = array('message'=>array());

	public static function message($type='info', $text, $items='', $temporary=false)
    {
        $html = '<div class="message';
        if($temporary) $html .= ' message_temporary';
        $html .= '"><p class="message_text message_text_'.$type.'"';
        if(!empty($items)) $html .= ' style="margin-bottom:0"';
        $html .= '>'.ucfirst($text).'</p>';
        if(!empty($items))  $html .= '<ol class="message_items message_items_'.$type.'">'.$items.'</ol>';
        $html .='</div>';
        return $html;
    }

    public static function add_message($message, $type='error', $key='')
    {
        $key = empty($key) ? 'message' : 'message_'.$key; 
        self::$messages[$key][] = self::message($type, $message, null, true);
    }

    public static function set_info($text, $redirect=false, $key='')
    {
        $key = empty($key) ? 'feedback' : 'feedback_'.$key; 
        $result = Session::flash($key, $text);
        if($redirect!==false) return redirect($redirect);
        else return $result;
    }

    public static function set_error($text, $redirect=false, $key='')
    {
        $key = empty($key) ? 'error' : 'error_'.$key; 
        $result = Session::flash($key, $text);
        if($redirect!==false) return redirect($redirect)->send();
        else return $result;
    }

    public static function render($key='')
    {
        $feedback_key = empty($key) ? 'feedback' : 'feedback_'.$key; 
        $text =  ((Session::has($feedback_key)) ? Session::get($feedback_key) : '');
        $feedback = empty($text) ? '' : self::message('feedback', $text, null, true);
        $error_key = empty($key) ? 'error' : 'error_'.$key; 
        $text =  ((Session::has($error_key)) ? Session::get($error_key) : '');
        $feedback .= empty($text) ? '' : self::message('error', $text, null, true);
        $message_key = empty($key) ? 'message' : 'message_'.$key; 
        $messages = (array_key_exists($message_key, self::$messages) ? self::$messages[$message_key] : array());
        if(!empty($messages) && count($messages) > 0) foreach($messages as $message) $feedback .= $message;
        return $feedback;
    }
}