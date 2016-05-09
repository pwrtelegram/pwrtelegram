<?php
/**
 * AJAX Cross Domain (PHP) Proxy 0.8
 *    by Iacovos Constantinou (http://www.iacons.net)
 *
 * Released under CC-GNU GPL
 */

/**
 * Enables or disables filtering for cross domain requests.
 * Recommended value: true
 */
define('CSAJAX_FILTERS', true);

/**
 * If set to true, $valid_requests should hold only domains i.e. a.example.com, b.example.com, usethisdomain.com
 * If set to false, $valid_requests should hold the whole URL ( without the parameters ) i.e. http://example.com/this/is/long/url/
 * Recommended value: false (for security reasons - do not forget that anyone can access your proxy)
 */
/**
 * Set debugging to true to receive additional messages - really helpful on development
 */
define('CSAJAX_DEBUG', false);



/* * * STOP EDITING HERE UNLESS YOU KNOW WHAT YOU ARE DOING * * */

/* identify request headers */
$request_headers = array( );
foreach ($_SERVER as $key => $value) {

    if (strpos($key, 'HTTP_') === 0  ||  strpos($key, 'CONTENT_') === 0) {
        $headername = str_replace('_', ' ', str_replace('HTTP_', '', $key));
        $headername = str_replace(' ', '-', ucwords(strtolower($headername)));
	$value = preg_replace('/; boundary=.*/', '', $value);
        if (!in_array($headername, array( 'Host', 'X-Proxy-Url' )) && !in_array("$headername: $value", $request_headers)) {
            $request_headers[] = "$headername: $value";
        }

    }
}


// identify request method, url and params
$request_method = $_SERVER['REQUEST_METHOD'];
if ('GET' == $request_method) {
    $request_params = $_GET;
} elseif ('POST' == $request_method) {
    $request_params = $_POST;
    if (empty($request_params)) {
        $data = file_get_contents('php://input');
        if (!empty($data)) {
            $request_params = $data;
        }
    }
	if(!empty($_FILES)) {
		foreach ($_FILES as $file => $val) {
			$request_params[$file] = new \CurlFile($val["tmp_name"], $val["type"], $val["name"]);
		}
	};
} elseif ('PUT' == $request_method || 'DELETE' == $request_method) {
    $request_params = file_get_contents('php://input');
} else {
    $request_params = null;
}
if($file_id != "") {
	$request_params[$curmethod] = $file_id;
}

$request_url = "https://api.telegram.org".$uri;

$p_request_url = parse_url($request_url);

// append query string for GET requests
if ($request_method == 'GET' && count($request_params) > 0 && (!array_key_exists('query', $p_request_url) || empty($p_request_url['query']))) {
    $request_url .= '?' . http_build_query($request_params);
}


// let the request begin
$ch = curl_init($request_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);   // (re-)send headers
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);     // return response
curl_setopt($ch, CURLOPT_HEADER, true);       // enabled response headers
// add data for POST, PUT or DELETE requests
if ('POST' == $request_method) {
	if(in_array("Content-Type: application/x-www-form-urlencoded", $request_headers)) $request_params = is_array($request_params) ? http_build_query($request_params) : $request_params;
	//die(var_dump($request_params));
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS,  $request_params);
} elseif ('PUT' == $request_method || 'DELETE' == $request_method) {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request_method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request_params);
}
// retrieve response (headers and content)
$response = curl_exec($ch);
curl_close($ch);

// split response to header and content
list($response_headers, $response_content) = preg_split('/(\r\n){2}/', $response, 2);

// (re-)send the headers
$response_headers = preg_split('/(\r\n){1}/', $response_headers);
foreach ($response_headers as $key => $response_header) {
    // Rewrite the `Location` header, so clients will also use the proxy for redirects.
    if (!preg_match('/^(Transfer-Encoding):/', $response_header)) {
        header($response_header, false);
    }
}

// finally, output the content
print($response_content);

function csajax_debug_message($message)
{
    if (true == CSAJAX_DEBUG) {
        print $message . PHP_EOL;
    }
}
exit();

?>
