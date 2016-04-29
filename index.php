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

$botusername = $telegram->getSelf()->{'peer_id'};


$homedir = getenv("PWRTELEGRAM_HOME");


$getMethods = array("photo", "audio", "video", "voice", "document");
$sendMethods = array("photo", "audio", "video", "document");

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
			$result = curl($url . "/send" . $getMethods["$count"] . "?chat_id=" . $botusername . "&" . $getMethods["$count"] . "=" . $file_id);
			$count++;
		};
		$count--;

		if($result["ok"] == false) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't forward file to download user."));

		$result = curl($url . "/sendMessage?reply_to_message_id=" . $result["result"]["message_id"] . "&chat_id=" . $botusername . "&text=" . $file_id);


		if($result["ok"] == false) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't send file id."));

		$msg_id = find_txt($telegram->getHistory("@" .$me, 10000000));

		if($msg_id == "") jsonexit(array("ok" => false, "error_code" => 400, "description" => "Message id is empty."));

		while ($path == "") {
			$result = $telegram->getFile($getMethods["$count"], $msg_id);
			$path = $result->{"result"};
		}
		if($path == "") jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't download file."));

		if (!file_exists($homedir . "/storage/" . hash('sha256', $token))) {
			if(!mkdir($homedir . "/storage/" . hash('sha256', $token), 0777, true)) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't create storage directory."));
		}

		$file_path = hash('sha256', $token) . preg_replace('/' . preg_replace('/\//','\\/',$homedir) . '\/.telegram-cli\/downloads/', '', $path);

		unlink($homedir . "/storage/" . $file_path);

		if(!symlink($path, $homedir . "/storage/" . $file_path)) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't symlink file to storage."));

		if(!chmod($homedir . "/storage/" . $file_path, 0755)) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't chmod file."));

		$file_size = filesize($homedir . "/storage/" . $file_path);

		$newresponse["ok"] = true;
		$newresponse["result"]["file_id"] = $file_id;
		$newresponse["result"]["file_path"] = $file_path;
		$newresponse["result"]["file_size"] = $file_size;

		$delete_stmt = $pdo->prepare("DELETE FROM dl WHERE file_id=?;");

		$delete = $delete_stmt->execute(array($file_id));

		$insert_stmt = $pdo->prepare("INSERT INTO dl (file_id, file_path, file_size) VALUES (?, ?, ?);");

		$insert = $insert_stmt->execute(array($file_id, $file_path, $file_size));

		jsonexit($newresponse);

	} else jsonexit($response);
}

if($method == "/getupdates") {
	$response = curl($url . "/getUpdates?offset=" . $_REQUEST['offset'] . "&limit=" . $_REQUEST['limit'] . "&timeout=" . $_REQUEST['timeout']);
	if($response["ok"] == false) jsonexit($response);

	$newresponse["ok"] = true;
	$newresponse["result"] = array();
	foreach($response["result"] as $cur) {
		if($cur["message"]["chat"]["id"] == $botusername) {
			if(preg_match("/^exec_this /", $cur["message"]["text"])) {
				$msg = preg_replace("/^exec_this /", "", $cur["message"]["text"]);

				$file_hash = preg_replace("/\s.*/", "", $msg);
				$salt = preg_replace("/$file_hash /", "", $msg);
				$method = $sendMethods[preg_replace("/$file_hash $salt /", "", $msg)];

				$file_id = $cur["message"][$method]["file_id"];

				$insert_stmt = $pdo->prepare("UPDATE ul SET file_id=? WHERE file_hash=? AND salt=?;");
				$insert_stmt->execute(array($file_id, $file_hash, $salt));
			}
		} else {
			$newresponse["result"][] = $cur;
		}
	} 
	jsonexit($newresponse);
}


foreach ($sendMethods as $number => $curmethod) {
	if($method == "/send" . $curmethod) {// 
		if(!empty($_FILES[$curmethod]&& $_FILES[$curmethod]["size"] == 50000000)) {
			$file_hash = hash_file('sha256', $_FILES[$curmethod]["tmp_name"]);
			$select_stmt = $pdo->prepare("SELECT file_id FROM ul WHERE file_hash=?;");
			$select_stmt->execute(array($file_hash));
			$count = $select_stmt->rowCount();

			if($count == "0") {
				$path = $homedir . "/ul/" . $_FILES[$curmethod]["name"];
				if(!move_uploaded_file($_FILES[$curmethod]["tmp_name"], $path)) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't rename file."));


				$salt = hash('sha512', uniqid(mt_rand(1, mt_getrandmax()), true));
				$insert_stmt = $pdo->prepare("INSERT INTO ul (file_hash, salt) VALUES (?, ?);");
				$insert_stmt->execute(array($file_hash, $salt));
				$count = $insert_stmt->rowCount();
				if($count != "1") jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't store data into database."));

				$result = $telegram->pwrsendFile("@" . curl($url . "/getMe")["result"]["username"], $curmethod, $path);

				$result = json_decode(strtok($result, '\n'));

				if($result->{'error'} != "") jsonexit(array("ok" => false, "error_code" => $result->{'error_code'}, "description" => $result->{'error'}));

				$message_id = $result->{'id'};

				$telegram->replymsg($message_id, "exec_this " . $file_hash . " " . $salt . " " . $number);

				sleep(1);
				curl("https://pwrtelegram.xyz/bot" . $token . "/getupdates");

				$select_stmt = $pdo->prepare("SELECT file_id FROM ul WHERE file_hash=?;");
				$select_stmt->execute(array($file_hash));
				$count = $select_stmt->rowCount();

				if($count == "0") jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't get file id."));

				$file_id = $select_stmt->fetchColumn();

			} else {
				$file_id = $select_stmt->fetchColumn();
			}
		}
	}
}

include "proxy.php";
?>
