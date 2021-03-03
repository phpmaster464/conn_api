<?php

    /****************************************************************************/
    /*                                                                          */
    /* Desc:        Class to interact with the directSMS Gateway over HTTP/S.   */
    /*              This requires PHP4.3.x or higher compiled with mod_ssl      */
    /*              in if you intend to use HTTPS to communicate with the       */
    /*              gateway                                                     */
    /*                                                                          */
    /* Version:     1.3.4                                                       */
    /* Copyright:   Copyright (c) 2003 directSMS Pty Ltd. All rights reserved   */
    /* Rel. Date:   17/07/2012                                                  */
    /*                                                                          */
    /****************************************************************************/

    // Server URLs
    define("S3_SERVER_URL",                 "http://api.directsms.com.au");
    define("S3_SERVER_URL_SECURE",          "https://api.directsms.com.au");

    // URIs for the operations available
    define("URI_CONNECT",                   "/s3/http/connect");
    define("URI_DISCONNECT",                "/s3/http/disconnect");
    define("URI_GET_BALANCE",               "/s3/http/get_balance");
    define("URI_GET_REPLY_MESSAGES",        "/s3/http/get_reply_messages");
    define("URI_GET_INBOUND_MESSAGES",      "/s3/http/get_inbound_messages");
    define("URI_SEND_MESSAGE",              "/s3/http/send_message");
    define("URI_SCHDEDULE_MESSAGE",         "/s3/http/schedule_message");
    define("URI_CANCEL_SCHDEDULED_MESSAGE", "/s3/http/cancel_scheduled_message");

    // Message types
    define("MESSAGE_TYPE_1WAY",             "1-way");
    define("MESSAGE_TYPE_2WAY",             "2-way");

    // The various operation results
    define("OP_CODE_ID",                    "id");
    define("OP_CODE_ERR",                   "err");
    define("OP_CODE_CREDITS",               "credits");
    define("OP_CODE_REPLIES",               "replies");

    // Error messages
    define("ERROR_SMS_GATEWAY_UNREACHABLE", "SMS gateway unreachable");
    define("ERROR_INVALID_RESPONSE",        "Invalid gateway response");
    define("ERROR_MAX_MOBILES_EXCEEDED",    "Maximum of 100 destination mobile numbers per request exceeded");

    // The various field prefixes in a 2-way sms reply or an inbound sms
    define("PREFIX_MESSAGEID",              "messageid: ");
    define("PREFIX_INBOUND_NUMBER",         "inbound: ");
    define("PREFIX_MOBILE",                 "mobile: ");
    define("PREFIX_MESSAGE",                "message: ");
    define("PREFIX_WHEN",                   "when: ");

    // Fixed lengths for 2-way replies or inbound messages
    define("LENGTH_MESSAGEID",              12);
    define("LENGTH_MESSAGE",                160);
    define("LENGTH_MOBILE",                 20);

    // Maximum number of mobile phones in any one request
    define("MAX_MOBILES",                   100);

    class sms_connection
    {
        var $connectionid = null;       // Connection ID to use with all operations

        var $result       = null;       // Positive result retrieved after last operation

        var $error        = null;       // Error message received adter last operation

        var $server_url   = null;       // The base URL to use when comminucating with the server

        var $response     = null;       // The raw HTML/XML response received from the server

        /************************************************************************/
        /* Public methods                                                       */
        /************************************************************************/

        /**
         *  Create a new connection
         *
         *  @param  $secure     Communicate with the gateway over HTTPS, which is more secure
         *                      however, you will need mod_ssl built into your PHP instalation
         */
        function sms_connection($secure = false)
        {
            // Set the server_url
            if($secure)
            {
                $this->server_url = S3_SERVER_URL_SECURE;
            }
            else
            {
                $this->server_url = S3_SERVER_URL;
            }

            // Check if the server is contactable
            if(!@file($this->server_url . URI_CONNECT))
            {
                $this->error = ERROR_SMS_GATEWAY_UNREACHABLE;
            }
        }

        /**
         *  Connect to the SMS gateway with the given
         *  credentials. the $lic_key is only used when
         *	distributing the software to other customers
         *
         *  @param  $username   	Your directSMS username
         *  @param  $password   	The corresponding password
         *  @param  $lic_key	    A valid license key for an
         *							enterprise license (optional)
         *
         *  @return boolean
         */
        function connect($username, $password, $lic_key = null)
        {
	        $url = $this->server_url . URI_CONNECT . "?username=" . $username . "&password=" . $password;

	        // Add the licence key
            if(!is_null($lic_key))
            {
	            $url .= "&lic_key=" . $lic_key;
            }

            // Send the HTTP request, this will fail and
            // return false if the server is not responding
            // or an error is found, i.e the server responded
            // with 'err: Invalid login credentials'
            if($this->send_request($url))
            {
                // Connection established, save the connection id
                // we will use it in subsequent operations
                $this->connectionid = $this->result;
                return true;
            }
            else
            {
                // Error message was already set by send_request()
                return false;
            }
        }

        /**
         *  Send a 1-way SMS message
         *
         *  @param  $message        The message to send
         *  @param  $mobiles        An array of valid mobile phone numbers
         *  @param  $senderid       Sender ID to use
         *  @param  $max_segments   The max. no. of SMS segments to use when sending this message
         *
         *  @return mixed           If you specify $max_segments > 1, the method will return an array
         *                          of 1 or more IDs identifying the SMS segments your message was
         *                          converted into.
         *
         *                          Otherwise, the ID of the SMS sent is returned as a string
         */
        function send_one_way_sms($message, $mobiles, $senderid, $max_segments = 1)
        {
            if(!$this->is_connected())
            {
                return null;
            }

            if(count($mobiles) > MAX_MOBILES)
            {
                $this->error = ERROR_MAX_MOBILES_EXCEEDED;
                return null;
            }

            // Generate the request string
            $url =  $this->server_url . URI_SEND_MESSAGE . "?connectionid=" . $this->connectionid . "&type=" . MESSAGE_TYPE_1WAY;
            $url .= "&message=" . urlencode($message) . "&senderid=" . urlencode($senderid) . "&to=" . $this->array_to_string($mobiles);
            $url .= "&max_segments=$max_segments";

            // Send the request off to the server
            if($this->send_request($url))
            {
                // Multiple segment IDs (potentially)
                if($max_segments > 1)
                {
                    return $this->get_ids();
                }

                return $this->result;
            }
            else
            {
                return null;
            }
        }

        /**
         *  Schedule a 1-way sms message for future delivery
         *
         *  @param  $message    The message to send
         *  @param  $mobiles    An array of valid mobile phone numbers
         *  @param  $senderid   Sender ID to use
         *	@param	$time		The date/time to send the message represented as the number
         *						of seconds since the UNIX epoch (January 1 1970 00:00:00 GMT)
         *
         *  @return string      The ID returned for this sms the gateway
         */
        function schedule_one_way_sms($message, $mobiles, $senderid, $time)
        {
            if(!$this->is_connected())
            {
                return null;
            }

            if(count($mobiles) > MAX_MOBILES)
            {
                $this->error = ERROR_MAX_MOBILES_EXCEEDED;
                return null;
            }

            // Generate the request string
            $url =  $this->server_url . URI_SCHDEDULE_MESSAGE . "?connectionid=" . $this->connectionid . "&type=" . MESSAGE_TYPE_1WAY;
            $url .= "&message=" . urlencode($message) . "&senderid=" . urlencode($senderid) . "&to=" . $this->array_to_string($mobiles) . "&timestamp=" . urlencode($time);

            // Send the request off to the server
            if($this->send_request($url))
            {
                return $this->result;
            }
            else
            {
                return null;
            }
        }

        /**
         *  Send a 2-way sms message
         *
         *  @param  $message        The message to send
         *  @param  $mobiles        An array of valid mobile phone numbers
         *  @param  $messageid      An ID that uniquely identifies this SMS in *your* system
         *  @param  $max_segments   The max. no. of SMS segments to use when sending this message
         *
         *  @return mixed           If you specify $max_segments > 1, the method will return an array
         *                          of 1 or more IDs identifying the SMS segments your message was
         *                          converted into.
         *
         *                          Otherwise, the ID of the SMS sent is returned as a string
         */
        function send_two_way_sms($message, $mobiles, $messageid = null, $max_segments = 1)
        {
            if(!$this->is_connected())
            {
                return null;
            }

            if(count($mobiles) > MAX_MOBILES)
            {
                $this->error = ERROR_MAX_MOBILES_EXCEEDED;
                return null;
            }

            // Generate the request string
            $url =  $this->server_url . URI_SEND_MESSAGE . "?connectionid=" . $this->connectionid . "&type=" . MESSAGE_TYPE_2WAY;
            $url .= "&message=" . urlencode($message) . "&to=" . $this->array_to_string($mobiles);
            $url .= "&max_segments=$max_segments";

            // Add the messageid if it is present
            if(!is_null($messageid))
            {
                $url .= "&messageid=" . urlencode($messageid);
            }

            // Send the request off to the server
            if($this->send_request($url))
            {
                // Multiple segment IDs (potentially)
                if($max_segments > 1)
                {
                    return $this->get_ids();
                }

                return $this->result;
            }
            else
            {
                return null;
            }
        }

        /**
         *  Schedule a 2-way sms message for future delivery
         *
         *  @param  $message    The message to send
         *  @param  $mobiles    An array of valid mobile phone numbers
         *  @param  $messageid  An ID that uniquely identifies this SMS in *your* system
         *	@param	$time		The date/time to send the message represented as the number
         *						of seconds since the UNIX epoch (January 1 1970 00:00:00 GMT)
         *
         *  @return string      The ID returned for this SMS by the gateway
         */
        function schedule_two_way_sms($message, $mobiles, $messageid = null, $time)
        {
            if(!$this->is_connected())
            {
                return null;
            }

            if(count($mobiles) > MAX_MOBILES)
            {
                $this->error = ERROR_MAX_MOBILES_EXCEEDED;
                return null;
            }

            // Generate the request string
            $url =  $this->server_url . URI_SCHDEDULE_MESSAGE . "?connectionid=" . $this->connectionid . "&type=" . MESSAGE_TYPE_2WAY;
            $url .= "&message=" . urlencode($message) . "&to=" . $this->array_to_string($mobiles) . "&timestamp=" . urlencode($time);

            // Add the messageid if it is present
            if(!is_null($messageid))
            {
                $url .= "&messageid=" . urlencode($messageid);
            }

            // Send the request off to the server
            if($this->send_request($url))
            {
                return $this->result;
            }
            else
            {
                return null;
            }
        }

        /**
         *  Cancel a scheduled sms message that was created earlier
         *
         *  @param  $id         The ID of the message that was returned when
         *                      the message was created. This should be 32 bytes
         *                      long
         *
         *  @return string      The ID returned for this scheduled SMS
         */
        function cancel_scheduled_sms($id)
        {
            if(!$this->is_connected())
            {
                return null;
            }

            // Generate the request string
            $url =  $this->server_url . URI_CANCEL_SCHDEDULED_MESSAGE  . "?connectionid=" . $this->connectionid . "&id=" . urlencode($id);

            // Send the request off to the server
            if($this->send_request($url))
            {
                return $this->result;
            }
            else
            {
                return null;
            }
        }

        /**
         *  Retrieve 2-way reply messages from the gateway
         *
         *  @param  $mark_as_read   Mark the messages retrieved as "read"
         *  @param  $messageid      The messageid of the 2-way messages to search
         *                          for unread replies for. This must match the messageid
         *                          you set on the original 2-way message
         *
         *  @return array           An array of reply_sms objects
         */
        function get_reply_sms($mark_as_read = null, $messageid = null)
        {
            if(!$this->is_connected())
            {
                return null;
            }

            // Generate the request string
            $url =  $this->server_url . URI_GET_REPLY_MESSAGES . "?connectionid=" . $this->connectionid;

            // Add the mark_as_read param if anything is passed in
            if(!is_null($mark_as_read))
            {
                $url .= "&mark_as_read=true";
            }

            // Add the messageid if it is present
            if(!is_null($messageid))
            {
                $url .= "&messageid=" . urlencode($messageid);
            }

            // Send the request off to the server
            if($this->send_request($url))
            {
                // Return an array of reply_sms objects
                $reply_count = $this->result;

                if($reply_count > 0)
                {
                    $result = array();

                    // We are expecting the response from the gateway to
                    // look like the following:
                    // messageid: msg_id  mobile: mob  message: msg   when: t
                    //
                    // This will be displayed in a fixed length manner
                    for($i = 1; $i <= $reply_count; $i++)
                    {
                        $line = $this->response[$i];

                        // Get the message id
                        $start     = strlen(PREFIX_MESSAGEID);
                        $length    = LENGTH_MESSAGEID;
                        $messageid = trim(substr($line, $start, $length));

                        // Get the mobile
                        $start    += $length + 1 + strlen(PREFIX_MOBILE);
                        $length    = LENGTH_MOBILE;
                        $mobile    = trim(substr($line, $start, $length));

                        // Get the message text
                        $start    += $length + 1 + strlen(PREFIX_MESSAGE);
                        $length    = LENGTH_MESSAGE;
                        $message   = trim(substr($line, $start, $length));

                        // Get the time
                        $start    += $length + 1 + strlen(PREFIX_WHEN);
                        $when      = trim(substr($line, $start));

                        // Create a new reply and place it in the array
                        $result[] = new reply_sms($messageid,
                                                  $message,
                                                  $mobile,
                                                  $when);
                    }

                    // Change the result to the array
                    $this->result = $result;
                    return $result;
                }
                else
                {
                    // No replies found, return an empty array
                    return array();
                }
            }
            else
            {
                // Error
                return null;
            }
        }

        /**
         *  Retrieve unread inbound SMS messages from the gateway
         *
         *  @param  $mark_as_read   Mark the messages retrieved as "read"
         *  @param  $inbound_number The number to fetch the inbound messages for
         *
         *  @return array           An array of inbound_sms objects
         */
        function get_inbound_sms($mark_as_read = null, $inbound_number = null)
        {
            if(!$this->is_connected())
            {
                return null;
            }

            // Generate the request string
            $url =  $this->server_url . URI_GET_INBOUND_MESSAGES . "?connectionid=" . $this->connectionid;

            // Add the mark_as_read param if anything
            // is passed in
            if(!is_null($mark_as_read))
            {
                $url .= "&mark_as_read=true";
            }

            // Add the inbound number if it is present
            if(!is_null($inbound_number))
            {
                $url .= "&inbound_number=" . urlencode($inbound_number);
            }

            // Send the request off to the server
            if($this->send_request($url))
            {
                $message_count = $this->result;

                if($message_count > 0)
                {
                    $result = array();

                    // We are expecting the response from the gateway to
                    // look like the following:
                    // inbound: inbound_number  mobile: mob  message: msg   when: t
                    //
                    // This will be displayed in a fixed length manner

                    for($i = 1; $i <= $message_count; $i++)
                    {
                        $line = $this->response[$i];

                        // Get the inbound number
                        $start          = strlen(PREFIX_INBOUND_NUMBER);
                        $length         = LENGTH_MOBILE;
                        $inbound_number = trim(substr($line, $start, $length));

                        // Get the mobile
                        $start         += $length + 1 + strlen(PREFIX_MOBILE);
                        $length         = LENGTH_MOBILE;
                        $mobile         = trim(substr($line, $start, $length));

                        // Get the message
                        $start         += $length + 1 + strlen(PREFIX_MESSAGE);
                        $length         = LENGTH_MESSAGE;
                        $message        = trim(substr($line, $start, $length));

                        // Get the time
                        $start         += $length + 1 + strlen(PREFIX_WHEN);
                        $when           = trim(substr($line, $start));

                        // Create a new reply and place it in the array
                        $result[] = new inbound_sms($inbound_number,
                                                    $message,
                                                    $mobile,
                                                    $when);
                    }

                    // Change the result to the array
                    $this->result = $result;
                    return $result;
                }
                else
                {
                    // No replies found, return an empty array
                    return array();
                }
            }
            else
            {
                // Error
                return null;
            }
        }

        /**
         *  Get your current credit balance
         *
         *  @return double  This will be -1 if an error is encountered or if your customer
         *                  account is not a pre-paid account
         */
        function get_balance()
        {
            if(!$this->is_connected())
            {
                return -1;
            }

            if($this->send_request($this->server_url . URI_GET_BALANCE . "?connectionid=" . $this->connectionid))
            {
                return $this->result;
            }
            else
            {
                return -1;
            }
        }

        /**
         *  Disconnect from the gateway
         *
         *  @return boolean
         */
        function disconnect()
        {
            if(!$this->is_connected())
            {
                return false;
            }

            if($this->send_request($this->server_url . URI_DISCONNECT . "?connectionid=" . $this->connectionid))
            {
                return true;
            }
            else
            {
                return false;
            }
        }

        /**
         *  Check if this sms_connection is connected to the gateway
         *
         *  @return boolean Return true if the connection has been established
         *                  and false otherwise
         */
        function is_connected()
        {
            return !is_null($this->connectionid);
        }

        /**
         *  Return the result of the last operation
         *
         *  @return object  The result of the last operation
         *                  maybe a string, an int or an array
         */
        function get_result()
        {
            return $this->result;
        }

        /**
         *  Check if there were any errors encountered whilst performing
         *  the last operation
         *
         *  @return boolean
         */
        function is_error()
        {
            return !is_null($this->error);
        }

        /**
         *  A helper function to return the error message associated with
         *  the last operation. if one is present
         *
         *  @return string
         */
        function get_error()
        {
            return $this->error;
        }

        /************************************************************************/
        /*                          Private Methods                             */
        /************************************************************************/

        /**
         *  A debug method used to show the response from the server
         *
         *  @return void
         */
        function print_response()
        {
            if(is_null($this->response))
            {
                return;
            }

            print("<pre>\n");

            for($i = 0; $i < count($this->response); $i++)
            {
                print($this->response[$i] . "\n");
            }

            print("</pre>\n");
        }

        /**
         *  Send a HTTP request to the URL passed in
         *  and process the response.
         *
         *  The function will return true if the server
         *  is contactable and no errors are returned
         *
         *  @param  $url    The url to send the request to
         *
         *  @return boolean Signals whether or not any errors
         *                  were encountered
         */
        function send_request($url)
        {
            // Clear the result and error from the previous operation
            $this->result = null;
            $this->error  = null;

            // Send the request and store the results in the response
            // attribute for debugging purposes
            if($this->response = @file($url))
            {
                // Check if nothing was returned in the response
                if(count($this->response) == 0)
                {
                    $this->error = ERROR_INVALID_RESPONSE;
                    return false;
                }
                else
                {
                    // Check the first line in the response.
                    // we are expecting a response of the form:
                    //
                    // id: a4c5ad77ad6faf5aa55f66a
                    //
                    // or
                    //
                    // credits: 2334
                    //
                    // or
                    //
                    // err: invalid login credentials
                    //
                    // etc...
                    $op_code   = $this->get_op_code($this->response[0]);
                    $op_result = $this->get_op_result($this->response[0]);

                    // Look for an error, i.e. err:
                    if($op_code == OP_CODE_ERR)
                    {
                        $this->error = $op_result;
                        return false;
                    }
                    else
                    {
                        $this->result = $op_result;
                        return true;
                    }
                }
            }
            else
            {
                // Gateway is not responding
                $this->error = ERROR_SMS_GATEWAY_UNREACHABLE;
                return false;
            }
        }

        /**
         *  Look at the response returned by gateway and
         *  rerturn the portion of the line before the ":"
         *
         *  @param  $line       Response from gateway
         *
         *  @return string
         */
        function get_op_code($line)
        {
            return strtolower(trim(substr($line, 0, strpos($line, ":"))));
        }

        /**
         *  Look at the response returned by gateway and
         *  rerturn the portion of the line after the ":"
         *
         *  @param  $line       Response from gateway
         *
         *  @return string
         */
        function get_op_result($line)
        {
            return trim(substr($line, strpos($line, ":") + 1));
        }

        /**
         *  Helper function to turn the array of strings
         *  into one long string, with array elements separated
         *  by ","
         *
         *  @param  $elements   An array of strings
         *
         *  @return string
         */
        function array_to_string($elements)
        {
            $result = "";

            for($i = 0, $count = count($elements); $i < $count; $i++)
            {
                $result .= $elements[$i];

                if($i + 1 < $count)
                {
                    $result .= ",";
                }
            }

            return $result;
        }

        /**
         *  Helper function to get the IDs of sumitted SMS message segments
         *  for long SMS messages
         *
         *  @return array   An array containing one or more message IDs
         */
        function get_ids()
        {
            if(count($this->response) == 0)
            {
                return null;
            }

            // Expecting a response form the server that lists
            // the ID of each SMS segment on a new line:
            // id: 7392a60b5ad26b138f4b939310b330f0
            // id: 754206ff53e47e05b32d017d5c6b97f4
            // id: a4c5ad77ad6faf5aa55f66a7261abc31
        	$result = array();
            foreach($this->response as $line)
            {
                $result[] = $this->get_op_result($line);
            }

            return $result;
        }
    }

    /****************************************************************************/
    /*                                                                          */
    /* desc:        Class to encapsulate 2-way reply messages                   */
    /*                                                                          */
    /* version:     1.3.3                                                       */
    /* copyright:   copyright (c) 2001-2010. all rights reserved                */
    /* rel date:    29/08/2011                                                  */
    /*                                                                          */
    /****************************************************************************/
    class reply_sms
    {
        var $messageid; // The client specific ID of the original 2-way message as
                        // submitted by the client in a call to send 2-way SMS
        var $message;   // The SMS message text
        var $mobile;    // Mobile number responding
        var $when;      // The number of seconds since this reply was received

        /**
         *  Create a new reply message
         */
        function reply_sms($messageid, $message, $mobile, $when)
        {
            $this->messageid = $messageid;
            $this->message   = $message;
            $this->mobile    = $mobile;
            $this->when      = $when;
        }

        /**
         *  Show the contents of this reply
         *
         *  @return string
         */
        function to_string()
        {
            return "messageid = '" . $this->messageid . "' " .
                   "mobile = '" . $this->mobile . "' " .
                   "when = '" . $this->when . "' " .
                   "message = '" . $this->message . "'";
        }
    }

    /****************************************************************************/
    /*                                                                          */
    /* desc:        Class to encapsulate inbound messages                       */
    /*                                                                          */
    /* version:     1.3.3                                                       */
    /* copyright:   copyright (c) 2001-2010. all rights reserved                */
    /* rel date:    29/08/2011                                                  */
    /*                                                                          */
    /****************************************************************************/
    class inbound_sms
    {
        var $inbound_number; // The number the message was received on
        var $message;        // The SMS message text
        var $mobile;         // Mobile number sending the message
        var $when;           // The number of seconds since this message was received

        /**
         *  Create a new inbound message
         */
        function inbound_sms($inbound_number, $message, $mobile, $when)
        {
            $this->inbound_number = $inbound_number;
            $this->message        = $message;
            $this->mobile         = $mobile;
            $this->when           = $when;
        }

        /**
         *  Show the contents of this inbound message
         *
         *  @return string
         */
        function to_string()
        {
            return "inbound = '" . $this->inbound_number . "' " .
                   "mobile = '" . $this->mobile . "' " .
                   "when = '" . $this->when . "' " .
                   "message = '" . $this->message . "'";
        }
    }
?>