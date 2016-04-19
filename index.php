<?php
// n2ogram script
// by Daniil Gentili
// gplv3 license

// connect to db
include 'db_connect.php';
// import php telegram api
require('vendor/autoload.php');
$telegram = new \Zyberspace\Telegram\Cli\Client('unix:///tmp/tg.sck');

$botusername = "@daniilgentili";
$sendMethods = array("document", "photo", "audio", "voice", "video");


function find_txt($msgs) {
   foreach ($msgs as $msg) {
     foreach ($msg as $key => $val) { 
      if ($key = "text" && $val = $_REQUEST['file_id']) {
        $ok = y;
      }
     }
     if ($ok = "y") return $msg["reply_id"];
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

// Prepare the url with the token
$url = "https://telegram.org/" . preg_replace(array("/^\//", "/\/.*/"), '', $_SERVER['REQUEST_URI']);


// gotta intercept sendphoto, sendaudio, sendvoice, sendvideo, senddocument, possibly without storing in ram, upload as secret user, forward to user


// intercept getfile, get file id, forward to secret user, get download link from secret user, return file id, file size, file path
if($_SERVER['REQUEST_URI'] == "/getFile" && $_REQUEST['file_id'] != "") {
 $response = curl($url . "/getFile?file_id=" . $_REQUEST['file_id']);
 if($response["ok"] == false && preg_match("/\[Error\]: Bad Request: file is too big\[size:/", $response["description"])) {
 
  $size = preg_replace(array("/.*big\[size:/", "/\].*/"), '', $response["description"]); // get size

  
  $me = curl($url . "/getMe")["username"]; // get my username
  
  $usernames = array_column($telegram->getDialogList(), "username"); // get usernames list
  
  if(!in_array($me, $usernames)) { // If never contacted bot send start command
   $telegram->msg($me, '/start');
  }

  $count = 0;
  while($result["ok"] != true) {
   $result = curl($url . "/send" . $sendMethods["$count"] . "?chat_id=" . $botusername . "&" . $sendMethods["$count"] . "=" . $_REQUEST['file_id']);
   $count++;
  };

  if($result["ok"] == false) {
   $newresponse["ok"] = false;
   $newresponse["error_code"] = 400;
   $newresponse["description"] = "Couldn't forward file to download user.";
  } else {
   $result = curl($url . "/sendMessage?reply_to_message_id=" . $result["message_id"] . "&chat_id=" . $botusername . "&message=" . $_REQUEST['file_id']);
   if($result["ok"] == false) {
    $newresponse["ok"] = false;
    $newresponse["error_code"] = 400;
    $newresponse["description"] = "Couldn't reply to forwarded file to download user.";
   } else {
    $path = $telegram->getFile($me, $sendMethods["$count"], find_txt($telegram->getHistory($botusername, 1000000, 1000000)))["result"];
    if($path = "") {
     $newresponse["ok"] = false;
     $newresponse["error_code"] = 400;
     $newresponse["description"] = "Couldn't download file.";
    } else {
     if(filesize($path) != $size)
      $newresponse["ok"] = false;
      $newresponse["error_code"] = 400;
      $newresponse["description"] = "Downloaded file size does not match.";
      } else {
       $newresponse["ok"] = true;
       $newresponse["file_id"] = $_REQUEST['file_id'];
       $newresponse["file_size"] = $size;
       $newresponse["file_path"] = preg_replace('/\/mnt\/vdb\/api\/files\//', '', $path);
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
