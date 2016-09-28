<?php

namespace PWRTelegram\PWRTelegram;

class Proxy extends API
{
    /**
     * AJAX Cross Domain (PHP) Proxy 0.8
     *    by Iacovos Constantinou (http://www.iacons.net).
     *
     * Released under CC-GNU GPL
     */
    public function run_proxy()
    {

        // identify request headers

        $request_headers = [];
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) == 'HTTP_') {
                $headername = str_replace('_', ' ', substr($key, 5));
                $headername = str_replace(' ', '-', ucwords(strtolower($headername)));
                if (!in_array($headername, ['Host', 'X-Proxy-Url', 'Content-Length', 'Content-Type']) && !in_array("$headername: $value", $request_headers)) {
                    $request_headers[] = "$headername: $value";
                }
            }
        }

        // identify request method, url and params

        if (empty($this->REQUEST)) {
            $data = file_get_contents('php://input');
            if (!empty($data)) {
                $this->REQUEST = $data;
            }
        }

        $request_url = $this->url.$this->method;
        $cmd = 'curl -s -D - '.escapeshellarg($request_url).' ';

        // add data for POST, PUT or DELETE requests

        foreach ($this->REQUEST as $key => $val) {
            $cmd .= '--form-string '.escapeshellarg($key.'='.$val).' ';
            $request_headers[] = 'Content-Type: multipart/form-data';
        }

        foreach ($_FILES as $f => $file) {
            if ($file['size']) {
                $cmd .= '--form '.escapeshellarg($f.'=@'.$file['tmp_name'].';filename='.$file['name'].';type='.$file['type']).' ';
            }
        }

        foreach ($request_headers as $header) {
            $cmd .= '-H '.escapeshellarg($header).' ';
        }

        // retrieve response (headers and content)

        $response = preg_replace("/^HTTP\/1.1 100 Continue(\r\n){2}/", '', shell_exec($cmd));

        // error_log($response);
        // split response to header and content

        list($response_headers, $response_content) = preg_split('/(\r\n){2}/', $response, 2);

        // (re-)send the headers

        $response_headers = preg_split('/(\r\n){1}/', $response_headers);
        foreach ($response_headers as $key => $response_header) {
            if ($response_header == 'Location: https://core.telegram.org/bots') {
                $response_header = 'Location: https://pwrtelegram.xyz';
            }

            if (!preg_match('/^(Transfer-Encoding):/', $response_header) && !preg_match('/^Content-Length:/', $response_header)) {
                header($response_header, false);
            }
        }

        // finally, output the content

        echo $response_content;
        exit;
    }
}
