# Instructions for using the Telegram bot project with GPT
This is a simple Telegram bot written in PHP 8+ using SQLite DB. Thou can easily deploy even multiple instances of this bot on any web hosting that supports SQLite::PDO and cURL.

> [!NOTE]  
> This code is provided as is – the author holds no responsibility for its use, modification, or any possible consequences.
> Use at thy own risk!

> [!WARNING]  
> The author believes the code may have potential vulnerabilities and odd solutions. The code demonstrates basic functionalities of a chatbot. It might not be secure: Use at thy own risk. Just like any OpenSource code :)

---

## Requirements PHP 8+

Install this PHP 8 packages:
- **php8.x-curl** – for execute HTTP-requests (cURL);
- **php8.x-mbstring** – for working wth strings;
- **php8.x-sqlite3** – for usng SQLite.

---

## Proxy Setup

Add the following parameters if u need to use a proxy server (SOCKS5) to send requests.

- Set the `isProxy` to `true` for enable.
- Set parameters:
  - `proxy_addr` – ur proxy server address;
  - `proxy_port` – ur proxy server port;
  - `proxy_user` и `proxy_pass` – ur login n password if the server requires it.

---

## Example for using

Create main PHP file with an unconventional name, like:  
**private_jfcXwc848kXL_bot.php**

> [!IMPORTANT]  
> File ChatGPT.php should be located in the same folder as the main file you will create now. After setting up the main PHP file, open it in the browser to automatically configure Telegram WebHooks..

> [!TIP]
> Its shouldn't have any errors. If you see SQLite errors, fix the permissions for two PHP files.

Example.php:

```php
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . "ChatGPT.php"; # Connect Main Core.

# Load Core
$bot = new ChatGPT();
//$bot->timeout = 15; # Custom value of time-out for requests.
//$bot->memo = 5; Custom value for how many messages the bot should remember. [on timeout]

# DataBase Name
//$bot->db_name = "my_custom_db_name"; # Custom DataBase Name
$bot->db_name = pathinfo(basename(__FILE__), PATHINFO_FILENAME); # Or use the name of the DataBase as the name for this PHP file...

# Telegram & GPT Tokens:
$bot->token_tg = "123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11";
$bot->token_ai = "sk-XXXXXXXXXXXXXXXXXXXXXXXXXXXX";
# if ure using docker, then u can use something like this:
//$bot->token_tg = $_ENV["CHAT_BOT_TG_TOKEN"];
//$bot->token_ai = $_ENV["CHAT_BOT_AI_TOKEN"];

# Use custom API-Endpoints:
//$bot->api_tg = "https://api.telegram.org/bot";
//$bot->api_ai = "for example: https://openrouter.ai/api/v1/chat/completions"; # or comment for use default: https://api.openai.com/v1/chat/completions

# Set custom model
//$bot->model = "My-Custom-Great-Model"; # default: gpt-4o-mini

# Set allowed users Telegram IDs:
$bot->allowed = [
	123456789,
	987654321,
];

# Setup proxy if u need it.
$bot->isProxy = false; # true for enable ; false for disable ;
$bot->proxy_addr = "127.0.0.1";
$bot->proxy_port = 12345;
$bot->proxy_user = "proxy_user";
$bot->proxy_pass = "proxy_pass";

# Start bot ;
$bot->start();
```

## Bot Commands

- `/start` - Start the bot.
- `/clear` - Clear the chat dialog of this conversation.
- `/clearall` - Clear the entire memory of the bot for all authorized users.

**Note:** All commands can only be executed by authorized users.
