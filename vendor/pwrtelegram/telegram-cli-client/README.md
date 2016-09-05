###Update 2015-12-23:

**As i currently have no time to work on this project anymore and telegram released its own [bot-api](https://core.telegram.org/bots), there won't be many new updates to this project in the future. I will try to go through all currently open issues so there is nothing left in the issue section anymore (even if i need to close some things with `wontfix`).  
If someone wants to takeover this project i am willing to give him push-rights or link to his fork on github/packagist.**

**If you want to port your project to the new bot-api take a look at the [unofficial php-sdk](https://github.com/irazasyed/telegram-bot-sdk).**

<hr />

zyberspace/telegram-cli-client
==============================
php-client for [telegram-cli](https://github.com/vysheng/tg/)

[![Packagist](https://img.shields.io/packagist/v/zyberspace/telegram-cli-client.svg)](https://packagist.org/packages/zyberspace/telegram-cli-client)
[![Codacy](https://www.codacy.com/project/badge/4175a9bbf88547cdbd94cf57c457068d)](https://www.codacy.com/app/zyberspace/php-telegram-cli-client)
[![License](https://img.shields.io/github/license/zyberspace/php-telegram-cli-client.svg)](https://www.mozilla.org/MPL/2.0/)

Requirements
------------
 - a running [telegram-cli](https://github.com/vysheng/tg/) listening on a unix-socket (`-S`) or a port (`-P`) and returning all answers as JSON (`--json`).  
   Needs to be configured already (phone-number, etc.).
 - php >= 5.3.0

Usage
-----

###Setup telegram-cli
[telegram-cli](https://github.com/vysheng/tg/) needs to run on a unix-socket (`-S`) or a port (`-P`), so *telegram-cli-client* can connect to it. All answers need to be returned as JSON (`--json`).  
You should also start it with `-W` so the contact-list gets loaded on startup.  
For this example we will take the following command (execute it from the dir, where you installed telegram-cli, not the php-client), `-d` lets it run as daemon.:

```shell
./bin/telegram-cli --json -dWS /tmp/tg.sck &
```

If you run the telegram-cli under another user than your php-script and you are using linux, you need to change the rights of the socket so that the php-script can access it (thanks to [dennydai](https://github.com/dennydai) for this!).  
For example, add both to a `telegram`-group and then do

```shell
chown :telegram /tmp/tg.sck
chmod g+rwx /tmp/tg.sck
```

If you never started telegram-cli before, you need to start it first in normal mode, so you can type in your telegram-phone-number and register it, if needed (`./bin/telegram-cli`).

To stop the daemon use `killall telegram-cli` or `kill -TERM [telegram-pid]`.

###Install telegram-cli-client with composer
In your project-root:

```shell
composer require --update-no-dev zyberspace/telegram-cli-client
```

Composer will then automatically add the package to your project requirements and install it (also creates the `composer.json` if you don't have one already).  
If you want to use the discovery-shell, remove the `--update-no-dev` from the command.

###Use it

```php
require('vendor/autoload.php');
$telegram = new \Zyberspace\Telegram\Cli\Client('unix:///tmp/tg.sck');

$contactList = $telegram->getContactList();
$telegram->msg($contactList[0]->print_name, 'Hey man, what\'s up? :D');
```

###Use it with the discovery-shell
A really easy way to learn the api is by using the embedded [discover-shell](https://github.com/zyberspace/php-discovery-shell). You need to install the dev-dependencies for this (`composer update --dev`).

```shell
$ ./discovery-shell.php
-- discovery-shell to help discover a class or library --

Use TAB for autocompletion and your arrow-keys to navigate through your method-history.
Beware! This is not a full-featured php-shell. The input gets parsed with PHP-Parser to avoid the usage of
eval().
> $telegram->getContactList();
array(13) {
  [...]
}
> $telegram->msg('my_contact', 'Hi, i am sending this from a php-client for telegram-cli.');
true
```

Documentation
-------------
To create the docs, just run `phpdoc` in the the project-root.  
An online-version is available at [phpdoc.zyberware.org/zyberspace/telegram-cli-client](http://phpdoc.zyberware.org/zyberspace/telegram-cli-client/).

Supported Commands
------------------
You can execute every command with the `exec()`-method from the `RawClient`-class, but there are also several command-wrappers available ([doc-link](http://phpdoc.zyberware.org/zyberspace/telegram-cli-client/classes/Zyberspace.Telegram.Cli.Client.html)) that you should prefer to use (see the `example.php`).  
If you prefer to only use your own command-wrappers instead, just extend the `RawClient`-class (takes care about the socket-connection and has some helper-methods).

In your command-wrappers or if you use `exec()` directly, please don't forget to escape the peers (contacts, chat-names, etc.) with `escapePeer()` and all string-arguments with `escapeStringArgument()` to avoid errors.

API-Stability
-------------
The api of the commands wrappers will definitely change in the future, for example `getHistory()` only returns the raw answer of the telegram-cli right now and is not really useful. Therefore you should not use this in production yet.

**If you still want to use this in your project, make sure you stay at the same [minor](http://semver.org/spec/v2.0.0.html)-version (`~0.1.0` or `0.1.*` for example)**.

License
-------
This software is licensed under the [Mozilla Public License v. 2.0](http://mozilla.org/MPL/2.0/). For more information, read the file `LICENSE`.
