<?php
/*
 * Place here any hosts for which we are to be a proxy -
 * e.g. the host on which the J2EE APIs we'll be proxying are running
 * */

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
define( 'CSAJAX_FILTERS', true );
/**
 * If set to true, $valid_requests should hold only domains i.e. a.example.com, b.example.com, usethisdomain.com
 * If set to false, $valid_requests should hold the whole URL ( without the parameters ) i.e. http://example.com/this/is/long/url/
 * Recommended value: false (for security reasons - do not forget that anyone can access your proxy)
 */
define( 'CSAJAX_FILTER_DOMAIN', true );

/**
 * Set debugging to true to receive additional messages - really helpful on development
 */
define( 'CSAJAX_DEBUG', true );

/**
 * A set of valid cross domain requests
 */
/*$valid_requests = array(
	'localhost'
);*/

/* * * STOP EDITING HERE UNLESS YOU KNOW WHAT YOU ARE DOING * * */

// identify request headers
$request_headers = array( );
$setContentType = true;
$isMultiPart = false;
foreach ( $_SERVER as $key => $value ) {
	if ( substr( $key, 0, 5 ) == 'HTTP_' ) {
		$headername = str_replace( '_', ' ', substr( $key, 5 ) );
		$headername = str_replace( ' ', '-', ucwords( strtolower( $headername ) ) );
		if ( !in_array( $headername, array( 'Host', 'X-Proxy-Url', 'Content-Length', 'Content-Type') ) && !in_array("$headername: $value", $request_headers)  ) {
			$request_headers[] = "$headername: $value";
		}
	}
}

// identify request method, url and params
$request_params = $REQUEST;
if ( empty( $request_params ) ) {
	$data = file_get_contents( 'php://input' );
	if ( !empty( $data ) ) {
		$request_params = $data;
	}
}
$request_url = "https://api.telegram.org/bot".$token.$method;

$request_headers[] = "Content-Type: multipart/form-data";

$cmd = "curl -s -D - " . escapeshellarg($request_url) . " ";
foreach ($request_headers as $header) {
	$cmd .= "-H " . escapeshellarg($header) . " ";
}
// add data for POST, PUT or DELETE requests
foreach ($request_params as $key => $val) {
	$cmd .= "--form-string " . escapeshellarg($key."=".$val) . " ";
}
foreach ($_FILES as $f => $file) {
	if($file['size']){
		$cmd .= "--form " . escapeshellarg($f . "=@" . $file["tmp_name"] . ";filename=" . $file["name"] . ";type=" . $file["type"]) . " ";
	}
}
// retrieve response (headers and content)
$response = preg_replace("/^HTTP\/1.1 100 Continue(\r\n){2}/" , "", shell_exec( $cmd ));

// split response to header and content
list($response_headers, $response_content) = preg_split( '/(\r\n){2}/', $response, 2 );

// (re-)send the headers
$response_headers = preg_split( '/(\r\n){1}/', $response_headers );


foreach ( $response_headers as $key => $response_header ) {
	if ( !preg_match( '/^(Transfer-Encoding):/', $response_header ) && !preg_match("/^Content-Length:/",$response_header)) {
		header( $response_header, false );
	}
	if($response_header == "Location: https://core.telegram.org/bots") header("Location: https://pwrtelegram.xyz");
}
// finally, output the content
print($response_content);
function csajax_debug_message( $message )
{
	if ( true == CSAJAX_DEBUG ) {
		print $message . PHP_EOL;
	}
}
exit;
