<?php
include 'vendor/autoload.php';
$path = "download_435400621709852762";


use Mhor\MediaInfo\MediaInfo;
$mediaInfo = new MediaInfo();
$mediaInfoContainer = $mediaInfo->getInfo($path);
$general = $mediaInfoContainer->getGeneral();
if($general->get("file_extension") == null) {
	$path = $path . "." . preg_replace("/.*\s/", "", $general->get("format_extensions_usually_used"));
}

var_dump($path);
