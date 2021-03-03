<?php 

function ws_security_headers($username, $password, $wss_ns)
{
	$auth = new stdClass();
	$auth->Username = new SoapVar($username, XSD_STRING, NULL, $wss_ns, NULL, $wss_ns);
    $auth->Password = new SoapVar($password, XSD_STRING, NULL, $wss_ns, NULL, $wss_ns);
    $username_token = new stdClass();
    $username_token->UsernameToken = new SoapVar($auth, SOAP_ENC_OBJECT, NULL, $wss_ns, 'UsernameToken', $wss_ns);
    $security_sv = new SoapVar( new SoapVar($username_token, SOAP_ENC_OBJECT, NULL, $wss_ns, 'UsernameToken', $wss_ns), SOAP_ENC_OBJECT, NULL, $wss_ns, 'Security', $wss_ns);
    return $security_sv;		
}

function ws_addressing_headers($client)
{
	$auth->ReplyTo = new SoapVar($username, XSD_STRING, NULL, $wss_ns, NULL, $wss_ns);
}