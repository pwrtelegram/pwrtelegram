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
    if(preg_match('/Content.Type/i', $key)){
        $setContentType = false;
        $content_type = explode(";", $value)[0];
        $isMultiPart = preg_match('/multipart/i', $content_type);
        $request_headers[] = "Content-Type: ".$content_type;
        continue;
    }
	if ( substr( $key, 0, 5 ) == 'HTTP_' ) {
		$headername = str_replace( '_', ' ', substr( $key, 5 ) );
		$headername = str_replace( ' ', '-', ucwords( strtolower( $headername ) ) );
		if ( !in_array( $headername, array( 'Host', 'X-Proxy-Url', 'Content-Length') ) && !in_array("$headername: $value", $request_headers)  ) {
			$request_headers[] = "$headername: $value";
		}
	}
}

if($setContentType)
    $request_headers[] = "Content-Type: application/json";

// identify request method, url and params
$request_method = $_SERVER['REQUEST_METHOD'];
if ( 'GET' == $request_method ) {
	$request_params = $_GET;
} elseif ( 'POST' == $request_method ) {
	$request_params = $_POST;
	if ( empty( $request_params ) ) {
		$data = file_get_contents( 'php://input' );
		if ( !empty( $data ) ) {
			$request_params = $data;
		}
	}
} elseif ( 'PUT' == $request_method || 'DELETE' == $request_method ) {
	$request_params = file_get_contents( 'php://input' );
} else {
	$request_params = null;
}

// Get URL from `csurl` in GET or POST data, before falling back to X-Proxy-URL header.
$request_url = "https://api.telegram.org/bot".$token.$method;
$p_request_url = parse_url( $request_url );

// csurl may exist in GET request methods

// append query string for GET requests
if ( $request_method == 'GET' && count( $request_params ) > 0 && (!array_key_exists( 'query', $p_request_url ) || empty( $p_request_url['query'] ) ) ) {
	$request_url .= '?' . http_build_query( $request_params );
}

// let the request begin
$ch = curl_init( $request_url );
curl_setopt( $ch, CURLOPT_HTTPHEADER, $request_headers );   // (re-)send headers
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );	 // return response
curl_setopt( $ch, CURLOPT_HEADER, true );	   // enabled response headers
// add data for POST, PUT or DELETE requests
if ( 'POST' == $request_method ) {
	$post_data = is_array( $request_params ) ? http_build_query( $request_params ) : $request_params;

    $has_files = false;
    $file_params = array();

    foreach ($_FILES as $f => $file) {
        if($file['size']){
            $file_params[$f] = new \CurlFile($file["tmp_name"], $file["type"], $file["name"]); 
            $has_files = true;
        }
    }

    if($isMultiPart || $has_files){
        foreach(explode("&",$post_data) as $i => $param) {
            $params = explode("=", $param);
            $xvarname = $params[0];
            if (!empty($xvarname))
                $file_params[$xvarname] = urldecode($params[1]);
        }
    }

	curl_setopt( $ch, CURLOPT_POST, true );
	curl_setopt( $ch, CURLOPT_POSTFIELDS,  $isMultiPart || $has_files ? $file_params : $post_data );
} elseif ( 'PUT' == $request_method || 'DELETE' == $request_method ) {
	curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $request_method );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, $request_params );
}
// retrieve response (headers and content)
$response = preg_replace("/^HTTP\/1.1 100 Continue(\r\n){2}/" , "", curl_exec( $ch ));
curl_close( $ch );
// split response to header and content
list($response_headers, $response_content) = preg_split( '/(\r\n){2}/', $response, 2 );

// (re-)send the headers
$response_headers = preg_split( '/(\r\n){1}/', $response_headers );


foreach ( $response_headers as $key => $response_header ) {
	// Rewrite the `Location` header, so clients will also use the proxy for redirects.
	if ( preg_match( '/^Location:/', $response_header ) ) {
		list($header, $value) = preg_split( '/: /', $response_header, 2 );
		$response_header = 'Location: ' . $_SERVER['REQUEST_URI'] . '?csurl=' . $value;
	}
	if ( !preg_match( '/^(Transfer-Encoding):/', $response_header ) && !preg_match("/^Content-Length:/",$response_header)) {
		header( $response_header, false );
	}
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
