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

$sendMethods = array("document", "photo", "audio", "voice", "video");


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
	if($retcode = 200) { return 0; } else { return 1;  };
}

// Prepare the url with the token
$token = preg_replace(array("/^\/bot/", "/\/.*/"), '', $_SERVER['SCRIPT_URL']);
$uri = "/" . preg_replace(array("/^\//", "/[^\/]*\//"), '', $_SERVER['SCRIPT_URL']);
$method = "/" . strtolower(preg_replace("/.*\//", "", $uri));
$url = "https://api.telegram.org/bot" . $token;


// gotta intercept sendphoto, sendaudio, sendvoice, sendvideo, senddocument, possibly without storing in ram, upload as secret user, forward to user


// intercept getfile, get file id, forward to secret user, get download link from secret user, return file id, file size, file path

if($method == "/getfile" && $_REQUEST['file_id'] != "") {
	$response = curl($url . "/getFile?file_id=" . $_REQUEST['file_id']);

	if($response["ok"] == false && preg_match("/\[Error\]: Bad Request: file is too big\[size:/", $response["description"])) {
		$file_id = $_REQUEST['file_id'];
		$selectstmt = $pdo->prepare("SELECT * FROM dl WHERE file_id=? DESC LIMIT 1;");
		$select = $selectstmt->execute(array($file_id));
		if(checkurl("https://storage.pwrtelegram.xyz/" . $select["file_path"])) {
			$newresponse["file_id"] = $select["file_id"];
			$newresponse["file_path"] = $select["file_path"];
			$newresponse["file_size"] = $select["file_size"];
			$newresponse["ok"] = true;
		} else {
			$file_size = preg_replace(array("/.*big\[size:/", "/\].*/"), '', $response["description"]); // get size
			$me = curl($url . "/getMe")["result"]["username"]; // get my username

			$usernameweird  = $telegram->getDialogList(); // get usernames list

			$usernames = array();
			foreach ($usernameweird as $username){ $usernames[] = $username->username; };

			if(!in_array($me, $usernames)) { // If never contacted bot send start command
				$telegram->msg("@".$me, '/start');
			}

			$count = 0;
			while($result["ok"] != true && $count < 5) {
				$result = curl($url . "/send" . $sendMethods["$count"] . "?chat_id=" . $botusername . "&" . $sendMethods["$count"] . "=" . $file_id);
				$count++;
			};

			if($result["ok"] == false) {
				$newresponse["ok"] = false;
				$newresponse["error_code"] = 400;
				$newresponse["description"] = "Couldn't forward file to download user.";
			} else {
				$result = curl($url . "/sendMessage?reply_to_message_id=" . $result["result"]["message_id"] . "&chat_id=" . $botusername . "&text=" . $file_id);


				if($result["ok"] == false) {
					$newresponse["ok"] = false;
					$newresponse["error_code"] = 400;
					$newresponse["description"] = "Couldn't reply to forwarded file to download user.";
				} else {
					$msg_id = find_txt($telegram->getHistory("@" .$me, 10000000));
					$result = $telegram->getFile($sendMethods["$count"], "010000008fda0a060d030000000000007a314db62e13b68e");
					$path = $result["result"];
die(var_export($result));
					if($path == "") {
						$newresponse["ok"] = false;
						$newresponse["error_code"] = 400;
						$newresponse["description"] = "Couldn't download file.";
					} else {
						clearstatcache();
						if(filesize($path) != $file_size) {
							$newresponse["ok"] = false;
							$newresponse["error_code"] = 400;
							$newresponse["description"] = "Downloaded file size does not match.";
							unlink($path);
						} else {
							if (!file_exists("/mnt/vdb/api/storage/" . hash('sha256', $token))) {
								mkdir("/mnt/vdb/api/storage/" . hash('sha256', $token), 0777, true);
							}
							$file_path = hash('sha256', $token) . preg_replace('/\/mnt\/vdb\/api\/files/', '', $path);
							if(rename($path, "/mnt/vdb/api/storage/" . $file_path)) {
								$newresponse["ok"] = true;
								$newresponse["file_id"] = $file_id;
								$newresponse["file_size"] = $file_size;
								$newresponse["file_path"] = $file_path;
								$delete_stmt = $pdo->prepare("DELETE FROM dl WHERE file_id=?;");
								$delete = $delete_stmt->execute(array($file_id));
								$insert_stmt = $pdo->prepare("INSERT INTO dl VALUES (file_id, file_size, file_path), (?, ?, ?);");
								$insert = $insert_stmt->execute(array($file_id, $file_size, $file_path));
							}
						}
					}
				}
			}
		}

		$response = json_encode($newresponse);
	}
	exit($response);
}
include "proxy.php";
?>
