# Pwrtelegram API
Version 1.0 beta 2.  
Licensed under GPLv3.  

This repository contains the source code for the pwrtelegram API, a boosted version of the official telegram bot API.

## Features:  

* All of the official telegram bot API features plus:  
* Uploading of files up to 1.5 GB in size  
* Downloading of files up to 1.5 GB in size  
* Uploading of files using an url  
* [You tell me!](https://telegram.me/danogentili)  

## How do I enable it?  

To enable it simply subsitute the url of the bot telegram API (https://API.telegram.org) with the url of the pwrtelegram API (https://API.pwrtelegram.xyz) in your telegram client.

You can use this command to do it:

sed -i 's/API\.telegram\.org/API\.pwrtelegram\.xyz/g' client.py

The API will automagically do the rest :)  


## How do I use it?

Just use it as you would use the official telegram bot API, only bear in the following points.

* Please do not abuse of the API by uploading and/or downloading illegal or copyrighted material. The pwrtelegram API creator reserves the right to delete any illegal or copyrighted material that is found on his servers.  

* The PWRTelegram API does not store in any way your bot's token but it does store the bot's username, the sha256 sum of uploaded files and the file ID of uploaded and downloaded files.

* The PWRTelegram API proxies all requests sent to it to API.telegram.org (later will be called official telegram API), except for:

 * getUpdates requests.
The responses of this method will be passed trough a piece of code that filters out messages from @pwrtelegramAPI.

 * getFile requests.

If a getFile request is called and the file ID points to a file which size is bigger than 20 MB and/or if you provide a (GET or POST) parameter with name ```store_on_pwrtelegram``` and value y the request is intercepted and the file is downloaded using the PWRTelegram API.  
The PWRTelegram API will then return a [File](https://core.telegram.org/bots/API#file) object.   
The file can be downloaded via the link ```https://API.pwrtelegram.xyz/file/bot<token>/<file_path>```. If the file was downloaded using the PWRTelegram API than you can use the following url: ```https://storage.pwrtelegram.xyz/<file_path>```.

If the file was downloaded using the PWRTelegram API the download URL will be in the following format:  
```
https://storage.pwrtelegram.xyz/botusername/filename.ext
```  
This way you will be able to safely share the download URL without exposing your bot's token.  

 * sendDocument, sendPhoto, sendVideo, sendAudio, sendVoice, sendSticker requests.
If a sendDocument, sendPhoto, sendVideo, sendAudio or a sendVoice request is sent with a file which size is bigger than 40 MB, the API will pass the file to a function that will upload the file to telegram and return the uploaded file's id.
If a sendDocument, sendPhoto, sendVideo, sendAudio, sendVoice or a sendSticker request is sent with a URL in place of the document (or photo, etc) the PWRTelegram API will download the file (only if its size is smaller than 1.5 GB), upload it on telegram and return its file id.  

If, due to any of the above conditions, the sent file or URL is uploaded using the PWRTelegram API, the next time you resend the same file (checked using sha256) the file won't be reuploaded and the file ID will be fetched from a database.

* Do not send more than 50 different files (as in files with different sha256sums) without processing updates. Files will not be sent if there's an unprocessed message from a user that isn't @pwrtelegramapi in front of the message queue.  

* For now the only supported message update method is getUpdates.  

* Please note that this API is still in beta and there might be small bugs. To report them contact [Daniil Gentili](https://telegram.me/danogentili)  

For questions contact https://telegram.me/danogentili

Share this API with all of your friends! :) 

Feel free to contribute with pull Requests.  


Daniil Gentili (http://daniil.it)




[Privacy Policy of pwrtelegram.xyz website](http://privacypolicies.com/privacy/view/Yv8dZc)
[Cookie Policy of pwrtelegram.xyz website](https://cookie.daniil.it/?w=pwrtelegram)
