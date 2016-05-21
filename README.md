# Pwrtelegram API
Version 1.1 beta 1.  
Licensed under AGPLv3.  

This repository contains the source code for the pwrtelegram API, a boosted version of the official telegram bot API.

The PWRTelegram API makes use of:  

* The [Caddy](https://github.com/mholt/caddy) web server.
* [HHVM](https://hhvm.com) as fastcgi engine
* [php-mediainfo](https://github.com/mhor/php-mediainfo)
* A modified version of [php-telegram-cli-client](https://github.com/zyberspace/php-telegram-cli-client)
* A modified version of [telegram-cli](https://github.com/pwrtelegram/tg)
* A modified version of [proxy.php](https://github.com/mcnemesis/proxy.php)
* [telegram-lua-load](https://github.com/pwrtelegram/telegram-lua-load)


## Features:  

* All of the official telegram bot API features plus:  
* Downloading of files up to 1.5 GB in size  
* Anonymous file storage (the URL of downloaded files does not contain your bot's token)  
* Uploading of files up to 1.5 GB in size  
* Uploading of files using an URL
* Reuploading of files using a file ID and different file type or file name.
* Uploading of any file/URL/file ID with automagical type recognition.  
* Optional automagical metadata recognition of sent files/URLs/file IDs.  
* Deleting of text messages sent from the bot  
* [You tell me!](https://telegram.me/pwrtelegramgroup)  

## How do I enable it?  

To enable it simply subsitute the url of the bot telegram API (https://api.telegram.org) with the url of the pwrtelegram API (https://api.pwrtelegram.xyz) in your telegram client.

You can use this command to do it:
```
sed -i 's/api\.telegram\.org/api\.pwrtelegram\.xyz/g' client.py
```  
The API will automagically do the rest :)  


## How do I use it?

Just use it as you would use the official telegram bot API, only bear in the following points.

* Please do not abuse of the API by uploading and/or downloading illegal or copyrighted material. The pwrtelegram API creator reserves the right to delete any illegal or copyrighted material that is found on his servers.  

* The PWRTelegram API does not store in any way your bot's token but it does store the bot's username, the sha256 sum of uploaded files and the file ID and the size of uploaded and downloaded files.

* The PWRTelegram API proxies all requests sent to it to api.telegram.org (later will be called official telegram API), except for:

* getUpdates requests.

The response of these requests will be passed trough a piece of code that will filter out messages from @pwrtelegramapi.

* getFile requests.

If a getFile request is made and the file ID points to a file which size is bigger than 20 MB and/or if you provide a (GET or POST) parameter with name ```store_on_pwrtelegram``` and boolean value ```true``` the request is intercepted and the file is downloaded using the PWRTelegram API, else the request is forwarded to the official telegram API.  
The PWRTelegram API will then return a [File](https://core.telegram.org/bots/API#file) object.   
The file can be downloaded via the link ```https://api.pwrtelegram.xyz/file/bot<token>/<file_path>```.  
If the file was downloaded using the PWRTelegram API than you can use the following anonymous url: ```https://storage.pwrtelegram.xyz/<file_path>```.

If the file was downloaded using the PWRTelegram API the download URL will be in the following format:  
```
https://storage.pwrtelegram.xyz/botusername/filename.ext
```  
This way you will be able to safely share the download URL without exposing your bot's token.  

 * sendDocument, sendPhoto, sendVideo, sendAudio, sendVoice, sendSticker requests.  

All of the above requests will be processed using the PWRTelegram API.  

The usage of these methods is exactly the same as in the official Telegram BOT API, except that if the request contains a file URL instead of the document (or photo, etc) the file will be downloaded and sent with the given parameters.

The same will happen if you send a file ID that links to a file which type is different from the one specified in the URL or if you also provide a file name along with the file ID.

You can provide a detect parameter: if the value of this parameter is set to true, the PWRTelegram API will automagically obtain the metadata of the provided file/URL and send it along with the file itself (only if it isn't already present in the request).

This is the metadata that will be obtained and sent if the detect parameter is set to true: 

* Documents: file name as caption

* Photos: file name as caption

* Video: file name, width, height and duration as caption, width, height and duration

* Audio: track name, author, duration as title, author, duration

* Voice: duration as duration  


You can also provide a name parameter containing the name of the file to be sent (this is useful when sending files from a URL). If this parameter is set the PWRTelegram API will rename the file/URL you sent and resend the file with the new file name.  



* sendFile

Use this method to send any file/URL/file ID. This method will automagically recognize the type of file/URL uploaded and send it using the correct telegram method. It will also automagically read file metadata and attach it to the request (only if it isn't already provided in the request). On success, the sent Message is returned.

This is the metadata that will be obtained and sent (only if it isn't already provided in the request) along with the file:

* Documents: file name as caption

* Photos: file name as caption

* Video: file name, width, height and duration as caption, width, height and duration

* Audio: track name, author, duration as title, author, duration

* Voice: duration as duration

| Parameters           	| Type                                                                           	| Required 	| Description                                                                                                                                                                  	|
|----------------------	|--------------------------------------------------------------------------------	|----------	|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------	|
| chat_id              	| Integer or String                                                              	| Yes      	| Unique identifier for the target chat or username of the target channel (in the format @channelusername)                                                                     	|
| message_id           	| Integer                                                                        	| Yes      	| Unique identifier of the sent message                                                                                                                                        	|
| file                 	| String                                                                         	| Yes      	| File to send. You can either pass a URL as String to send a file from a URL, or a file ID as a string to reupload a file already present on the Telegram servers or upload a new file using *multipart/form-data*.                                                	|
| caption              	| String                                                                         	| Optional 	| File caption (will only be applied if the sent file/URL is a photo, a video or a document), 0-200 characters                                                                 	|
| duration             	| Integer                                                                        	| Optional 	| Duration of the sent file/URL in seconds (will only be applied if the sent file/URL is an audio file, a video or a voice recording)                                          	|
| performer            	| String                                                                         	| Optional 	| Performer of the sent file/URL (will only be applied if the sent file/URL is an audio file)                                                                                  	|
| title                	| String                                                                         	| Optional 	| Title of the sent file/URL (will only be applied if the sent file/URL is an audio file)                                                                                      	|
| width                	| Integer                                                                        	| Optional 	| Width of the sent file/URL (will only be applied if the sent file/URL is a video file)                                                                                       	|
| height               	| Integer                                                                        	| Optional 	| Height of the sent file/URL (will only be applied if the sent file/URL is a video file)                                                                                      	|
| name                 	| String                                                                         	| Optional 	| Name of the file to be sent. If set, the file will be sent with the specified file name.                                                                                     	|
| disable_notification 	| Boolean                                                                        	| Optional 	| Sends the message silently. iOS users will not receive a notification, Android users will receive a notification with no sound.                                              	|
| reply_to_message_id  	| Integer                                                                        	| Optional 	| If the message is a reply, ID of the original message                                                                                                                        	|
| reply_markup         	| InlineKeyboardMarkup or ReplyKeyboardMarkup or ReplyKeyboardHide or ForceReply 	| Optional 	| Additional interface options. A JSON-serialized object for an inline keyboard, custom reply keyboard, instructions to hide reply keyboard or to force a reply from the user. 	|


* deleteMessage

Use this method to delete text messages sent by the bot or via the bot (for inline bots).  
On success, if the message is deleted by the bot, a json array is returned with the following values:

* ok => true

* result => "The message was deleted successfully."

Otherwise the error is returned.

| Parameters        	| Type              	| Required             	| Description                                                                                                                                              	|
|-------------------	|-------------------	|----------------------	|----------------------------------------------------------------------------------------------------------------------------------------------------------	|
| chat_id           	| Integer or String 	| No (see description) 	| Required if inline_message_id is not specified. Unique identifier for the target chat or username of the target channel (in the format @channelusername) 	|
| message_id        	| Integer           	| No (see description) 	| Required if inline_message_id is not specified. Unique identifier of the sent message                                                                    	|
| inline_message_id 	| String            	| No (see description) 	| Required if chat_id and message_id are not specified. Identifier of the inline message                                                                   	|


* Do not send more than 50 different files (as in files with different sha256sums) without processing updates. Once reached this limit, files will not be sent if there's an unprocessed message from a user that isn't @pwrtelegramapi in front of the message queue.  

* For now the only supported message update method is getUpdates.  

* Please note that this API is still in beta and there might be small bugs. To report them contact [Daniil Gentili](https://telegram.me/danogentili).  


## Known bugs

This API cannot download video and voice files bigger than 20 mb. This is a bug of tg-cli.  
The metadata recognition feature works only with files smaller than 40 mb.  


For questions contact https://telegram.me/danogentili or the [official support group](https://telegram.me/pwrtelegramgroup).

Share this API and its offical channel (https://telegram.me/pwrtelegram) with all of your friends! :) 

Feel free to contribute with pull Requests.  


Daniil Gentili (http://daniil.it)




[Privacy Policy of pwrtelegram.xyz website](http://privacypolicies.com/privacy/view/Yv8dZc)  
[Cookie Policy of pwrtelegram.xyz website](https://cookie.daniil.it/?w=pwrtelegram)
