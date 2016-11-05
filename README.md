# Pwrtelegram API
Version 1.1 beta 3.   
Licensed under AGPLv3.  

[![API status](https://pwr-status.herokuapp.com)](https://pwr-status.herokuapp.com)

This repository contains the source code for the pwrtelegram API, a boosted version of the official telegram bot API.

The PWRTelegram API makes use of:  

* The [Caddy](https://github.com/mholt/caddy) web server.
* [HHVM](https://hhvm.com) as fastcgi engine
* [php-mediainfo](https://github.com/mhor/php-mediainfo)
* A modified version of [php-telegram-cli-client](https://github.com/zyberspace/php-telegram-cli-client)
* A modified version of [telegram-cli](https://github.com/pwrtelegram/tg)
* A modified version of [proxy.php](https://github.com/mcnemesis/proxy.php)
* [telegram-lua-load](https://github.com/pwrtelegram/telegram-lua-load)

This API is written and maintained by [danog](https://github.com/danog) ([@danogentili on telegram](https://telegram.me/danogentili)) with the help of the folks over [@BotDevelopment](https://telegram.me/BotDevelopment), especially [itskenny0](https://github.com/itskenny0) ([@shitposting on telegram](https://telegram.me/shitposting)) and [Rondoozle](https://github.com/Rondoozle) ([@POTUS on Telegram](https://telegram.me/POTUS)).  

It is hosted on Scaleway.

The new PWRTelegram logo was created by [@BayernPars](https://telegram.me/BayernPars).

## Features:  

All of the official telegram bot API features plus:  
* Support for [deep telegram bots](https://telegram.me/daniilgentili).
* Downloading of files up to 1.5 GB in size  
* Anonymous file storage (the URL of downloaded files does not contain your bot's token)
* Uploading of files up to 1.5 GB in size  
* Uploading of files using an URL
* Re-uploading of files using a file ID and different file type or file name.
* Uploading of any file/URL/file ID with automagical type recognition.  
* Uploading of any file/URL/file ID without sending the file to a specific user.  
* Automagical metadata recognition of sent files/URLs/file IDs.  
* Deleting of text messages sent from the bot.  
* Uploading of files bigger than 5 megabytes with inline queries (supports both URLs and direct uploads)
* Automatical type recognition for files sent using answerinlinequery 
* Both webhooks and getupdates are supported.
* webhook requests can be recieved even on insecure http servers.
* Resolving of usernames not only with channels and groups but also with normal users and bots.
* [It is open source](https://github.com/pwrtelegram)!
* [It can be installed on your own server](https://github.com/pwrtelegram/pwrtelegram-backend)!
* [You tell me!](https://telegram.me/pwrtelegramgroup)  

## How do I enable it?  

To enable it simply substitute the URL of the bot telegram API (https://api.telegram.org) with the URL of the pwrtelegram API (https://api.pwrtelegram.xyz for normal bots and https://deepapi.pwrtelegram.xyz for deep telegram bots) in your telegram client.

You can use one of the following commands to do it:
```
sed -i 's/api\.telegram\.org/api\.pwrtelegram\.xyz/g' client.py
# OR
sed -i 's/api\.telegram\.org/deepapi\.pwrtelegram\.xyz/g' client.py
```  

The client can be written in any language, not necessarily python.

Or you can manually substitute ```api.telegram.org``` with ```api.pwrtelegram.xyz``` or ```deepapi.pwrtelegram.xyz``` in your bot,

If you use webhooks you must recall the setwebhook method.  

The API will automagically do the rest :)  

Also please insert the following text in the response to the /start command:  
```
This bot makes use of the @pwrtelegram bot API to enhance its features.
```


## How do I use it?

Just use it as you would use the official telegram bot API, only bear in the following points.

* Please do not abuse of the API by uploading and/or downloading illegal or copyrighted material. The pwrtelegram API creator reserves the right to delete any illegal or copyrighted material that is found on his servers.  

* The PWRTelegram API does not store in any way your bot's token but it does store the bot's username, the sha256 sum of uploaded files and the file ID and the size of uploaded and downloaded files.

* This API won't be able to send big files if getUpdate or webhook requests aren't proxied through it. This is because when the API uploads these files using tg-cli it must obtain a file_id using the official bot API, and that can be done only by intercepting the incoming update with the file.

* The methods used by the PWRTelegram API are the same ones used in the official telegram bot API, but they also have some additional features and in certain cases their behaviour is slightly modified (don't worry, that won't break your clients). There are also some methods that work only with the PWRTelegram API. Here's a list:

* With this API you can use usernames to interact even with normal users and you can get info about bots using their username (with the getChat method).  

* getUpdates and webhook requests.

The response of these requests will be passed trough a piece of code that will filter out messages from @pwrtelegramapi.

* getFile requests.

getFile requests are intercepted and the file is downloaded using the PWRTelegram API.  
The PWRTelegram API will then return a [File](https://core.telegram.org/bots/API#file) object.   
You can use the following anonymous url to download the file: ```https://storage.pwrtelegram.xyz/<file_path>``` (or ```https://deepstorage.pwrtelegram.xyz/<file_path>``` for deep telegram bots).

The anonymous download URL will be in one of the following formats:  
```
https://storage.pwrtelegram.xyz/botusername/filename.ext
https://deepstorage.pwrtelegram.xyz/botusername/filename.ext
```  
This way you will be able to safely share the download URL without exposing your bot's token.  

You can also trigger the caching of files already present on the telegram servers to the anonymous PWRTelegram storage server by downloading the file from the following URL: ```https://api.pwrtelegram.xyz/file/bot<token>/<file_path>``` (or ```https://deepapi.pwrtelegram.xyz/file/bot<token>/<file_path>``` for deep telegram bots). The next time you can download files directly from the anonymous URL.  

If your request times out and you try to redownload the file you will be returned a json array with the following values:

  * ok => true

  * error_code => 202

  * description => Your file is being downloaded. Please try again later.

You can repeat the request until you get the File object.  

 * sendDocument, sendPhoto, sendVideo, sendAudio, sendVoice, sendSticker requests.  

All of the above requests will be processed using the PWRTelegram API.  

The usage of these methods is exactly the same as in the official Telegram BOT API, except that if the request contains a file URL instead of the document (or photo, etc) the file will be downloaded and sent with the given parameters.

The same will happen if you send a file ID that links to a file which type is different from the one specified in the URL or if you also provide a file name along with the file ID.

The PWRTelegram API will automagically obtain the metadata of the provided file/URL and send it along with the file itself (only if it isn't already present in the request).

This is the metadata that will be obtained and sent: 

* Video: width, height and duration as width, height and duration

* Audio: track name, author, duration as title, author, duration

* Voice: duration as duration  


You can also provide a ```file_name``` parameter containing the name of the file to be sent (this is useful when sending files from a URL). If this parameter is set the PWRTelegram API will rename the file/URL you sent and resend the file with the new file name.  



* sendFile

Use this method to send any file/URL/file ID. This method will automagically recognize the type of file/URL uploaded and send it using the correct telegram method. It will also automagically read file metadata and attach it to the request (only if it isn't already provided in the request). On success, the sent Message is returned.

This is the metadata that will be obtained and sent (only if not present in the request) along with the file:

* Video: width, height and duration as width, height and duration

* Audio: track name, author, duration as title, author, duration

* Voice: duration as duration

| Parameters           	| Type                                                                           	| Required 	| Description                                                                                                                                                                  	|
|----------------------	|--------------------------------------------------------------------------------	|----------	|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------	|
| chat_id              	| Integer or String                                                              	| Yes      	| Unique identifier for the target chat or username of the target channel, group or user (in the format @username)                                                                     	|
| message_id           	| Integer                                                                        	| Yes      	| Unique identifier of the sent message                                                                                                                                        	|
| file                 	| String                                                                         	| Yes      	| File to send. You can either pass a URL as String to send a file from a URL, or a file ID as a string to re-upload a file already present on the Telegram servers or upload a new file using *multipart/form-data*.                                                	|
| caption              	| String                                                                         	| Optional 	| File caption (will only be applied if the sent file/URL is a photo, a video or a document), 0-200 characters                                                                 	|
| duration             	| Integer                                                                        	| Optional 	| Duration of the sent file/URL in seconds (will only be applied if the sent file/URL is an audio file, a video or a voice recording)                                          	|
| performer            	| String                                                                         	| Optional 	| Performer of the sent file/URL (will only be applied if the sent file/URL is an audio file)                                                                                  	|
| title                	| String                                                                         	| Optional 	| Title of the sent file/URL (will only be applied if the sent file/URL is an audio file)                                                                                      	|
| width                	| Integer                                                                        	| Optional 	| Width of the sent file/URL (will only be applied if the sent file/URL is a video file)                                                                                       	|
| height               	| Integer                                                                        	| Optional 	| Height of the sent file/URL (will only be applied if the sent file/URL is a video file)                                                                                      	|
| file_name            	| String                                                                         	| Optional 	| Name of the file to be sent. If set, the file will be sent with the specified file name.                                                                                     	|
| disable_notification 	| Boolean                                                                        	| Optional 	| Sends the message silently. iOS users will not receive a notification, Android users will receive a notification with no sound.                                              	|
| reply_to_message_id  	| Integer                                                                        	| Optional 	| If the message is a reply, ID of the original message                                                                                                                        	|
| reply_markup         	| InlineKeyboardMarkup or ReplyKeyboardMarkup or ReplyKeyboardHide or ForceReply 	| Optional 	| Additional interface options. A JSON-serialized object for an inline keyboard, custom reply keyboard, instructions to hide reply keyboard or to force a reply from the user. 	|


* uploadFile, uploadDocument, uploadAudio, uploadVideo, uploadVoice, uploadPhoto, uploadSticker

These methods can be used to upload files to telegram without sending them to a particular user. Their usage is exactly the same as for their sendMethod counterparts, except that the chat_id, disable_notification, reply_to_message_id, reply_markup parameters will be ignored.  

On success, they will return a json array containing the following elements:

* ok => true

* result =>

 * file_id => Uploaded file id

 * file_type => Uploaded file type

 * file_size => Uploaded file size

Otherwise the error is returned.


* deleteMessage

Use this method to delete text messages sent by the bot or via the bot (for inline bots).  
On success, if the message is deleted by the bot, a json array is returned with the following values:

* ok => true

* result => "The message was deleted successfully."

Otherwise the error is returned.

| Parameters        	| Type              	| Required             	| Description                                                                                                                                              	|
|-------------------	|-------------------	|----------------------	|----------------------------------------------------------------------------------------------------------------------------------------------------------	|
| chat_id           	| Integer or String 	| No (see description) 	| Required if inline_message_id is not specified. Unique identifier for the target chat or username of the target channel, group or user (in the format @username) 	|
| message_id        	| Integer           	| No (see description) 	| Required if inline_message_id is not specified. Unique identifier of the sent message                                                                    	|
| inline_message_id 	| String            	| No (see description) 	| Required if chat_id and message_id are not specified. Identifier of the inline message                                                                   	|


* Do not send more than 50 different files (as in files with different sha256sums) without processing updates. Once reached this limit, files will not be sent if there's an unprocessed message from a user that isn't @pwrtelegramapi in front of the message queue (this limitation is only present if you use getupdates).  

* You can use both getupdates and webhooks to get updates

Only remember that you will have to repeat the setwebhook request to enable proxying trough the PWRTelegram API.

* answerInlineQuery

The usage if this method is exactly the same as in the official telegram bot api, except that you can provide URLs to files bigger than 5 megabytes and you can set the file type to ```file``` to enable automatical type and metadata recognition.

You can also upload files using via POST: you just have to upload the files with parameter name equal to ```inline_file0``` where 0 is the number of the file. The number has to be equal to the array index of the InlineQueryResult that will feature that file. The type_url field of that InlineQueryResult must also be empty.

Please note that it's better to upload the big files using the upload methods and store the file ids instead of uploading them directly using the answerInlineQuery method.  


* Please note that this API is still in beta and there might be small bugs. To report them contact [Daniil Gentili](https://telegram.me/danogentili) or [open an issue](https://github.com/pwrtelegram/pwrtelegram/issues) or [submit a pull request with a fix](https://github.com/pwrtelegram/pwrtelegram) or write to [@pwrtelegramgroup](https://telegram.me/pwrtelegramgroup).  

* getBackend

This method returns a Chat object with info about the backend pwrtelegram user.  


## Known bugs

See the issues of the repos of the [pwrtelegram organization](https://github.com/pwrtelegram).

## How can I help?

You can help by doing one or more of the following things:

* Proofreading this documentation and sending a message to the [official support group](https://telegram.me/pwrtelegramgroup) if you have some corrections.
* Peer reviewing the source code of this API and reporting bugs by contacting [Daniil Gentili](https://telegram.me/danogentili) or [opening an issue](https://github.com/pwrtelegram/pwrtelegram/issues) or [submitting a pull request with a fix](https://github.com/pwrtelegram/pwrtelegram) or writing to [@pwrtelegramgroup](https://telegram.me/pwrtelegramgroup).  
* Starring the [repositories of the project](https://github.com/pwrtelegram) and sharing it around with your friends :)

## More info

For questions contact https://telegram.me/danogentili or the [official support group](https://telegram.me/pwrtelegramgroup).

Share this API and its official channel (https://telegram.me/pwrtelegram) with all of your friends! :) 

Feel free to contribute with pull Requests.  


Daniil Gentili (http://daniil.it)




[Privacy Policy of pwrtelegram.xyz website](http://privacypolicies.com/privacy/view/Yv8dZc)  
[Cookie Policy of pwrtelegram.xyz website](https://cookie.daniil.it/?w=pwrtelegram)
