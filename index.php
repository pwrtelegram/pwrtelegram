<?php
// pwrtelegram script
// by Daniil Gentili
// gplv3 license

// logging
ini_set("log_errors", 1);
ini_set("error_log", "/tmp/php-error-index.log");

// connect to db
include 'db_connect.php';
// import php telegram api
require('vendor/autoload.php');
$telegram = new \Zyberspace\Telegram\Cli\Client('unix:///tmp/tg.sck');

$botusername = "140639228";

$sendMethods = array("photo", "audio", "video", "voice", "document");


function find_txt($msgs) {
	$ok = "";
	foreach ($msgs as $msg) {
		foreach ($msg as $key => $val) { 
			if ($key == "text" && $val == $_REQUEST["file_id"]) $ok = "y";
		}
		if ($ok == "y") return $msg->reply_id;
	}
}
// curl wrapper
function curl($url) {
	// Get cURL resource
	$curl = curl_init();
	curl_setopt_array($curl, array(
	    CURLOPT_RETURNTRANSFER => 1,
	    CURLOPT_URL => $url,
	));
	$res = curl_exec($curl);
	curl_close($curl);
	return json_decode($res, true);
};

function checkurl($url) {
	$ch = curl_init("$url");
	curl_setopt($ch, CURLOPT_NOBODY, true);
	curl_exec($ch);
	$retcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if($retcode == 200) { return true; } else { return false;  };
}

function jsonexit($wut) {
	die(json_encode($wut));
}

// Prepare the url with the token
$token = preg_replace(array("/^\/bot/", "/\/.*/"), '', $_SERVER['SCRIPT_URL']);
$uri = "/" . preg_replace(array("/^\//", "/[^\/]*\//"), '', $_SERVER['SCRIPT_URL']);
$method = "/" . strtolower(preg_replace("/.*\//", "", $uri));
$url = "https://api.telegram.org/bot" . $token;


// gotta intercept sendphoto, sendaudio, sendvoice, sendvideo, senddocument, possibly without storing in ram, upload as secret user, forward to user


// intercept getfile, get file id, forward to secret user, get download link from secret user, return file id, file size, file path

if(preg_match("/^\/file\/bot/", $_SERVER['SCRIPT_URL'])) {
	header("Location: https://storage.pwrtelegram.xyz/" . preg_replace("/^\/file\/bot[^\/]*\//", '', $_SERVER['SCRIPT_URL']));
	die();
};

if($method == "/getfile" && $_REQUEST['file_id'] != "") {
	$file_id = $_REQUEST['file_id'];
	$selectstmt = $pdo->prepare("SELECT * FROM dl WHERE file_id=? LIMIT 1;");
	$selectstmt->execute(array($file_id));
	$select = $selectstmt->fetch(PDO::FETCH_ASSOC);

	if($selectstmt->rowCount() == "1" && checkurl("https://storage.pwrtelegram.xyz/" . $select["file_path"])) {
	
		$newresponse["ok"] = true;
		$newresponse["result"]["file_id"] = $select["file_id"];
		$newresponse["result"]["file_path"] = $select["file_path"];
		$newresponse["result"]["file_size"] = $select["file_size"];
		jsonexit($newresponse);
	}

	$response = curl($url . "/getFile?file_id=" . $_REQUEST['file_id']);

	if($response["ok"] == false && preg_match("/\[Error : 400 : Bad Request: file is too big.*/", $response["description"])) {
		$me = curl($url . "/getMe")["result"]["username"]; // get my username
		$usernameweird  = $telegram->getDialogList(); // get usernames list
		$usernames = array();
		foreach ($usernameweird as $username){ $usernames[] = $username->username; };

		if(!in_array($me, $usernames)) { // If never contacted bot send start command
			if(!$telegram->msg("@".$me, "/start")) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't initiate chat.")); 
		}

		$count = 0;
		while($result["ok"] != true && $count < 5) {
			$result = curl($url . "/send" . $sendMethods["$count"] . "?chat_id=" . $botusername . "&" . $sendMethods["$count"] . "=" . $file_id);
			$count++;
		};
		$count--;

		if($result["ok"] == false) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't forward file to download user."));

		$result = curl($url . "/sendMessage?reply_to_message_id=" . $result["result"]["message_id"] . "&chat_id=" . $botusername . "&text=" . $file_id);


		if($result["ok"] == false) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't send file id."));

		$msg_id = find_txt($telegram->getHistory("@" .$me, 10000000));

		if($msg_id == "") jsonexit(array("ok" => false, "error_code" => 400, "description" => "Message id is empty."));

		$result = $telegram->getFile($sendMethods["$count"], $msg_id);
		$path = $result->{"result"};

		if($path == "") jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't download file."));

		if (!file_exists("/mnt/vdb/api/storage/" . hash('sha256', $token))) {
			if(!mkdir("/mnt/vdb/api/storage/" . hash('sha256', $token), 0777, true)) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't create storage directory."));
		}

		$file_path = hash('sha256', $token) . preg_replace('/\/mnt\/vdb\/api\/.telegram-cli\/downloads/', '', $path);

		if(!rename($path, "/mnt/vdb/api/storage/" . $file_path)) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't move file to storage."));

		if(!chmod("/mnt/vdb/api/storage/" . $file_path, 0755)) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't chmod file."));

		$file_size = filesize("/mnt/vdb/api/storage/" . $file_path);

		$newresponse["ok"] = true;
		$newresponse["result"]["file_id"] = $file_id;
		$newresponse["result"]["file_size"] = $file_size;
		$newresponse["result"]["file_path"] = $file_path;

		$delete_stmt = $pdo->prepare("DELETE FROM dl WHERE file_id=?;");

		$delete = $delete_stmt->execute(array($file_id));

		$insert_stmt = $pdo->prepare("INSERT INTO dl (file_id, file_size, file_path) VALUES (?, ?, ?);");

		$insert = $insert_stmt->execute(array($file_id, $file_size, $file_path));

		jsonexit($newresponse);

	} else jsonexit($response);
}
include "proxy.php";
?>
