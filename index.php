<?php

// pwrtelegram script
// by Daniil Gentili
/*
Copyright 2016 Daniil Gentili
(https://daniil.it)

This file is part of the PWRTelegram API.
the PWRTelegram API is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version. 
The PWRTelegram API is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the GNU Affero General Public License for more details. 
You should have received a copy of the GNU General Public License along with the PWRTelegram API. 
If not, see <http://www.gnu.org/licenses/>.
*/
// logging
ini_set("log_errors", 1);
ini_set("error_log", "/tmp/php-error-index.log");

// Home dir
$homedir = realpath(__DIR__ . "/../") . "/";
// Available methods and their equivalent in tg-cli
$methods = array(
	"photo" => "photo",
	"video" => "video",
	"voice" => "document",
	"document" => "document",
	"sticker" => "document",
	"audio" => "audio",
	"file" => ""
);
// The uri without the query string
$uri = "/" . preg_replace(array("/\?.*$/", "/^\//", "/[^\/]*\//"), '', $_SERVER['REQUEST_URI']);
// The method
$method = "/" . strtolower(preg_replace("/.*\//", "", $uri));
// The user id of @pwrtelegramapi
$botusername = "140639228";
// The bot's token
$token = preg_replace(array("/^\/bot/", "/^\/file\/bot/", "/\/.*/"), '', $_SERVER['REQUEST_URI']);
// The api url with the token
$url = "https://api.telegram.org/bot" . $token;
// The url of this api
$pwrtelegram_api = "https://".$_SERVER["HTTP_HOST"]."/";
// The url of the storage
$pwrtelegram_storage = "https://storage.pwrtelegram.xyz/";


/**
 * Returns 
 *
 * @param $url - The location of the remote file to download. Cannot
 * be null or empty.
 *
 * @param $json - Default is true, if set to true will json_decode the content of the url.
 *
 * @return true if remote file exists, false if it doesn't exist.
 */
function curl($url, $json = true) {
	// Get cURL resource
	$curl = curl_init();
	curl_setopt_array($curl, array(
	    CURLOPT_RETURNTRANSFER => 1,
	    CURLOPT_URL => str_replace (' ', '%20', $url),
	));
	$res = curl_exec($curl);
	curl_close($curl);
	if($json == true) return json_decode($res, true); else return $res;
};

/**
 * Returns true if remote file exists, false if it doesn't exist.
 *
 * @param $url - The location of the remote file to download. Cannot
 * be null or empty.
 *
 * @return true if remote file exists, false if it doesn't exist.
 */

function checkurl($url) {
	$ch = curl_init(str_replace(' ', '%20', $url));
//	curl_setopt( $ch, CURLOPT_HEADER, true );
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
	curl_setopt($ch, CURLOPT_NOBODY, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 50);
	curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
	curl_exec($ch);
	$retcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//	error_log($url . $retcode. curl_error($ch));
	curl_close($ch);
	if($retcode == 200) { return true; } else { return false;  };
}

// Die while outputting a json error
function jsonexit($wut) {
	die(json_encode($wut));
}


// If request comes from telegram webhook
if(preg_match("|^/hook|", $method)) {
	$hook = preg_replace("|^/hook/|", '', $method);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url . "/send" . $type . "?" . http_build_query($newparams));
	curl_setopt($ch, CURLOPT_POSTFIELDS, array('chat_id' => $botusername, $type => new \CURLFile($path))); 
	$result = json_decode(curl_exec($ch), true);
	curl_close($ch);
}

function escapeJsonString($value) {
	$escapers = array("\\", "/", "\n", "\r", "\t", "\x08", "\x0c");
	$replacements = array("\\\\", "\\/", "\\n", "\\r", "\\t", "\\f", "\\b");
	$result = str_replace($escapers, $replacements, $value);
	return $result;
}

// If requesting a file
if(preg_match("/^\/file\/bot/", $_SERVER['REQUEST_URI'])) {
	if(checkurl($pwrtelegram_storage . preg_replace("/^\/file\/bot[^\/]*\//", '', $_SERVER['REQUEST_URI']))) {
		$file_url = $pwrtelegram_storage . preg_replace("/^\/file\/bot[^\/]*\//", '', $_SERVER['REQUEST_URI']);
	} else {
		if(checkurl("https://api.telegram.org/". $_SERVER['REQUEST_URI'])) {
			include 'functions.php';
			include '../db_connect.php';
			$me = curl($url . "/getMe")["result"]["username"]; // get my username
			$path = str_replace('//', '/', $homedir . "/storage/" . $me . "/" . preg_replace("/^\/file\/bot[^\/]*\//", '', $_SERVER['REQUEST_URI']));
			$dl_url = "https://api.telegram.org/" . $_SERVER['REQUEST_URI'];

			if(!(file_exists($path) && filesize($path) == curl_get_file_size($dl_url))){
				if(!checkdir(dirname($path))) return array("ok" => false, "error_code" => 400, "description" => "Couldn't create storage directory.");
				set_time_limit(0);
				$fp = fopen ($path, 'w+');
				$ch = curl_init(str_replace(" ","%20", $dl_url));
				curl_setopt($ch, CURLOPT_TIMEOUT, 50);
				// write curl response to file
				curl_setopt($ch, CURLOPT_FILE, $fp);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				// get curl response
				curl_exec($ch);
				curl_close($ch);
				fclose($fp);
			}
			$file_path = $me . "/" . preg_replace("/^\/file\/bot[^\/]*\//", '', $_SERVER['REQUEST_URI']);

			if(!file_exists($path)) return array("ok" => false, "error_code" => 400, "description" => "Couldn't download file (file does not exist).");
			$file_size = filesize($path);

			$delete_stmt = $pdo->prepare("DELETE FROM dl WHERE file_path=? AND bot=?;");
			$delete = $delete_stmt->execute(array($file_path, $me));
			$insert_stmt = $pdo->prepare("INSERT INTO dl (file_path, file_size, bot, real_file_path) VALUES (?, ?, ?, ?);");
			$insert = $insert_stmt->execute(array($file_path, $file_size, $me, $path));
		}
		if(checkurl($pwrtelegram_storage . "/" . $file_path)) {
			$file_url = $pwrtelegram_storage . $file_path;
		} else $file_url = "https://api.telegram.org/" . $_SERVER['REQUEST_URI'];
	}
	header("Location: " . $file_url);
	die();
};


// Else use a nice case switch
switch($method) {
	case "/getfile":
		if($token == "") jsonexit(array("ok" => false, "error_code" => 400, "description" => "No token was provided."));
		if($_REQUEST["file_id"] == "") jsonexit(array("ok" => false, "error_code" => 400, "description" => "No file id was provided."));
		include 'functions.php';
/*		if($_REQUEST["store_on_pwrtelegram"] == true) jsonexit(download($_REQUEST['file_id']));
		$response = curl($url . "/getFile?file_id=" . $_REQUEST['file_id']);
		if($response["ok"] == false && preg_match("/\[Error : 400 : Bad Request: file is too big/", $response["description"])) {
		} else jsonexit($response);*/
		jsonexit(download($_REQUEST["file_id"]));
		break;
	case "/getupdates":
		if($token == "") jsonexit(array("ok" => false, "error_code" => 400, "description" => "No token was provided."));
		$limit = "";
		$timeout = "";
		$offset = "";
		if(isset($_REQUEST["limit"])) $limit = $_REQUEST["limit"];
		if(isset($_REQUEST["offset"])) $offset = $_REQUEST["offset"];
		if(isset($_REQUEST["timeout"])) $timeout = $_REQUEST["timeout"];
		if($limit == "") $limit = 100;
		$response = curl($url . "/getUpdates?offset=" . $offset . "&timeout=" . $timeout);
		if($response["ok"] == false) jsonexit($response);
		$onlyme = true;
		$notmecount = 0;
		$todo = "";
		$newresponse["ok"] = true;
		$newresponse["result"] = array();
		foreach($response["result"] as $cur) {
			if(isset($cur["message"]["chat"]["id"]) && $cur["message"]["chat"]["id"] == $botusername) {
				if(isset($cur["message"]["text"]) && preg_match("/^exec_this /", $cur["message"]["text"])){
					include_once '../db_connect.php';
					$data = json_decode(preg_replace("/^exec_this /", "", $cur["message"]["text"]));
					foreach (array_keys($methods) as $curmethod) {
						if(isset($cur["message"]["reply_to_message"][$curmethod]) && is_array($cur["message"]["reply_to_message"][$curmethod])) $type = $curmethod;
					}

					if($type == "photo") {
						$file_id = $cur["message"]["reply_to_message"][$type][0]["file_id"];
					} else $file_id = $cur["message"]["reply_to_message"][$type]["file_id"];
					$update_stmt = $pdo->prepare("UPDATE ul SET file_id=?, file_type=? WHERE file_hash=? AND bot=? AND file_name=?;");
					$update_stmt->execute(array($file_id, $type, $data->{'file_hash'}, $data->{'bot'}, $data->{'filename'}));
				}
				if($onlyme) $todo = $cur["update_id"] + 1;
			} else {
				$notmecount++;
				if($notmecount <= $limit) $newresponse["result"][] = $cur;
				$onlyme = false;
			}
		}
		if($todo != "") curl($url . "/getUpdates?offset=" . $todo);
		jsonexit($newresponse);
		break;
	case "/deletemessage":
		if($token == "") jsonexit(array("ok" => false, "error_code" => 400, "description" => "No token was provided."));
		if(!($_REQUEST["inline_message_id"] != '' || ($_REQUEST["message_id"] != '' && $_REQUEST["chat_id"] != ''))) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Missing required parameters."));
		if($_REQUEST["inline_message_id"] != "") {
			$res = curl($url . "/editMessageText?parse_mode=Markdown&text=_This message was deleted_&inline_message_id=" . $_REQUEST["inline_message_id"]); 
		} else {
			$res = curl($url . "/editMessageText?parse_mode=Markdown&text=_This message was deleted_&message_id=" . $_REQUEST["message_id"] . "&chat_id=" . $_REQUEST["chat_id"]);
		};
		if($res["ok"] == true) $res["result"] = "The message was deleted successfully.";
		jsonexit($res);
		break;
	case "/answerinlinequery":
		if($token == "") jsonexit(array("ok" => false, "error_code" => 400, "description" => "No token was provided."));
		if(!(isset($_REQUEST["inline_query_id"]) && $_REQUEST["inline_query_id"] != "")) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Missing query id."));
		if(!(isset($_REQUEST["results"]) && $_REQUEST["results"] != "")) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Missing results json array."));
		$results = json_decode(escapeJsonString($_REQUEST["results"]), true);
		$newresults = array();
		if(isset($_REQUEST["detect"])) $detect = $_REQUEST["detect"]; else $detect = '';
		foreach ($results as $number => $result) {
			$type = $result["type"];
			if (!(isset($result[$type . "_file_id"]) && $result[$type . "_file_id"] != "")) {
				include_once 'functions.php';
				if((!isset($result[$type . "_url"]) || $result[$type . "_url"] == "") && isset($_FILES["inline_file" . $number]["error"]) && $_FILES["inline_file" . $number]["error"] == UPLOAD_ERR_OK) {
					// $detect enables or disables metadata detection
					// Let's do this!
					$upload = upload($_FILES["inline_file" . $number]["tmp_name"], $_FILES["inline_file" . $number]["name"]);

					if(isset($upload["result"]["file_id"]) && $upload["result"]["file_id"] != "") {
						unset($result[$type . "_url"]);
						$result[$type . "_file_id"] = $upload["result"]["file_id"];
					}
				}
				if(isset($result[$type . "_url"]) && $result[$type . "_url"] != "") {
					$upload = upload($result[$type . "_url"]);
					if(isset($upload["result"]["file_id"]) && $upload["result"]["file_id"] != "") {
						$result[$type . "_file_id"] = $upload["result"]["file_id"];
						unset($result[$type . "_url"]);
					}
				}
			}
			$newresults[] = $result;
		}
		$newparams = $_REQUEST;
		$newparams["results"] = json_encode($newresults);
//error_log(var_export($newparams, true));
		$json = curl($url . "/answerinlinequery?" . http_build_query($newparams));
//error_log(var_export($json, true));
		jsonexit($json);
		break;
	case "/donutsetwebhook":
		include_once '../db_connect.php';
		if($token == "") jsonexit(array("ok" => false, "error_code" => 400, "description" => "No token was provided."));
		if(isset($_REQUEST["url"]) && $_REQUEST["url"] != "") {
			$insert_stmt = $pdo->prepare("DELETE FROM hooks WHERE bot=? AND file_name=? AND file_size=?;");
			$insert_stmt->execute(array($me));
			$insert_stmt = $pdo->prepare("INSERT INTO ul (file_hash, bot, file_name, file_size) VALUES (?, ?, ?, ?);");
			$insert_stmt->execute(array($me, hash("sha256", $_REQUEST["url"])));
			$count = $insert_stmt->rowCount();
			if($count != 1) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't insert hook hash into database."));
			$newparams = $_REQUEST;
			$newparams["url"] = $pwrtelegram_api . "bot" . $token . "/hook/" . $newparams["url"];
			jsonexit(curl($url . "/answerinlinequery?" . http_build_query($newparams)));

		}
		break;
}

// The sending method without the send keyword
$smethod = preg_replace(array("|.*/send|", "|.*/upload|"), "", $method);
if (array_key_exists($smethod, $methods)) { // If using one of the send methods
	if($token == "") jsonexit(array("ok" => false, "error_code" => 400, "description" => "No token was provided."));
	include 'functions.php';
	$name = '';
	$forcename = false;
	if(isset($_FILES[$smethod]["tmp_name"]) && $_FILES[$smethod]["tmp_name"] != "") {
		$name = $_FILES[$smethod]["name"];
		$file = $_FILES[$smethod]["tmp_name"];
		$forcename = true;
	} else $file = $_REQUEST[$smethod];
	// $file is the file's path/url/id
	if(isset($_REQUEST["name"]) && $_REQUEST["name"] != "") {
		// $name is the file's name that must be overwritten if it was set with $_FILES[$smethod]["name"]
		$name = $_REQUEST["name"];
		$forcename = true;
		// $forcename is the boolean that enables or disables renaming of files
	};

	// Let's do this!
	$upload = upload($file, $name, $smethod, $forcename);
	if(isset($upload["ok"]) && $upload["ok"] == true && preg_match("|^/send|", $method)) {
	 	$params = $_REQUEST;
		$params[$upload["result"]["file_type"]] = $upload["result"]["file_id"];
	 	jsonexit(curl($url . "/send" . $upload["result"]["file_type"] . "?" . http_build_query($params)));
	} else jsonexit($upload);
}

include "proxy.php";
?>
