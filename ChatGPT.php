<?php

class ChatGPT {
	private		PDO		$db;
	public		int		$timeout		=	30;	# request timeout in seconds ;

	#	Telegram:
	public		string	$db_name;
	public		string	$token_tg;
	public		string	$api_tg			=	"https://api.telegram.org/bot";
	public		array	$allowed		=	[];

	#	GPT:
	public		string	$token_ai;
	public		string	$api_ai			=	"https://api.openai.com/v1/chat/completions";
	public		string	$model			=	"gpt-4o-mini"; # default model ;
	public		string	$CONTEXT		=	""; # default context ;
	public		bool	$isReasoning	=	false;
	public		int		$memo			=	20; # remember last 20 messages ;
	public		int		$minutes		=	60; # at 60 minutes ;

	#	Proxy SOCKS5:
	public		bool	$isProxy		=	false;	# true - enable proxy ; false - disable proxy ;
	public		int		$proxy_port		=	12345;
	public		string	$proxy_addr		=	"";
	public		string	$proxy_user		=	"";
	public		string	$proxy_pass		=	"";

	/**
	 * Запуск бота.
	 * @return void
	 */
	public function start(): void {
		try {
			$this->initDb();

			if ($_SERVER["REQUEST_METHOD"] === "POST") {
				$this->handleIncomingMessage(messageData: json_decode(file_get_contents("php://input"), true));
			} else {
				if ($response = $this->sendRequestToTelegram(method: "getWebhookInfo", params: null)) {
					echo "<pre>" . print_r(value: $response, return: 1) . "</pre>";

					if (isset($response["ok"]) && $response["ok"] && isset($response["result"]["url"])) {
						$currentPageUrl = "https://" . $_SERVER["HTTP_HOST"] . strtok(string: $_SERVER["REQUEST_URI"], token: "?");
						if ($response["result"]["url"] !== $currentPageUrl) {
							$setWebhookResponse = $this->sendRequestToTelegram(method: "setWebhook", params: ["url" => $currentPageUrl]);
							if ($setWebhookResponse) echo "<pre>" . print_r(value: $setWebhookResponse, return: 1) . "</pre>";
						}
					}
				} else {
					throw new Exception("Ошибка при получении информации о webhook.");
				}
			}
		} catch (Exception $ex) {
			echo $ex->getMessage();
		}
	}

	/**
	 * Creating SQLite DataBase.
	 *
	 * @return void
	 * @noinspection SqlDialectInspection
	 * @noinspection SqlNoDataSourceInspection
	 */
	private function initDb(): void {
		if (empty($this->db_name)) throw new InvalidArgumentException("Bot name cannot be empty");
		$this->db = new PDO("sqlite:" . __DIR__ . DIRECTORY_SEPARATOR . $this->db_name . ".sqlite");
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->db->exec("CREATE TABLE IF NOT EXISTS messages (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			chat_id TEXT,
			telegram_message_id INTEGER,
			role TEXT,
			text TEXT,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP
		)");
	}

	/**
	 * Remember sent message in DataBase.
	 *
	 * @param string|int $chat_id
	 * @param int $telegram_message_id
	 * @param string $role
	 * @param string $text
	 * @return void
	 * @noinspection SqlResolve
	 * @noinspection SqlDialectInspection
	 * @noinspection SqlNoDataSourceInspection
	 */
	private function storeMessage(string|int $chat_id, int $telegram_message_id, string $role, string $text): void {
		$stmt = $this->db->prepare("INSERT INTO messages (chat_id, telegram_message_id, role, text) VALUES (?, ?, ?, ?)");
		$stmt->execute([$chat_id, $telegram_message_id, $role, $text]);
	}

	/**
	 * Get message from DataBase using Telegram ID.
	 *
	 * @param string|int $chat_id
	 * @param int $telegram_message_id
	 * @return array|null
	 * @noinspection SqlResolve
	 * @noinspection SqlDialectInspection
	 * @noinspection SqlNoDataSourceInspection
	 */
	private function getMessageByTelegramId(string|int $chat_id, int $telegram_message_id): ?array {
		$stmt = $this->db->prepare("SELECT telegram_message_id, role, text FROM messages WHERE chat_id = ? AND telegram_message_id = ? LIMIT 1");
		$stmt->execute([$chat_id, $telegram_message_id]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return !$row ? null : $row;
	}

	/**
	 * Get the previous messages according to the rule ($this->memo = 20 per $this->minutes = 60)
	 *
	 * @param string|int $chat_id
	 * @return array
	 * @noinspection SqlResolve
	 * @noinspection SqlDialectInspection
	 * @noinspection SqlNoDataSourceInspection
	 */
	private function getContext(string|int $chat_id): array {
		$stmt = $this->db->prepare("SELECT telegram_message_id, role, text FROM messages WHERE chat_id = ? AND datetime(created_at) >= datetime('now', '-$this->minutes minutes') ORDER BY id DESC LIMIT ?");
		$stmt->bindParam(1, $chat_id, PDO::PARAM_INT);
		$stmt->bindParam(2, $this->memo, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		return array_reverse($rows);
	}

	/**
	 * Send Request.
	 *
	 * @param string $url
	 * @param array|string|null $params
	 * @param array|null $headers
	 * @return array|null
	 */
	private function sendCurlRequest(string $url, array|string|null $params = null, ?array $headers = null): ?array {
		if (empty($url)) return null;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
		if (!empty($params)) curl_setopt($ch, CURLOPT_POST, 1);
		if (!empty($params)) curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		if ($this->isProxy) {
			curl_setopt($ch, CURLOPT_PROXY, $this->proxy_addr);
			curl_setopt($ch, CURLOPT_PROXYPORT, $this->proxy_port);
			curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
			if (!empty($this->proxy_user)) curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxy_user . ":" . $this->proxy_pass);
		}

		$result = curl_exec($ch);
		$err    = curl_error($ch);
		curl_close($ch);

		return $err ? ["error" => $err] : json_decode(json: $result, associative: true);
	}

	/**
	 * Send Request to Telegram API.
	 *
	 * @param string $method
	 * @param array|null $params
	 * @return array|null
	 */
	public function sendRequestToTelegram(string $method, ?array $params): ?array {
		if (empty($method)) return null;
		return $this->sendCurlRequest(url: $this->api_tg . $this->token_tg . "/" . $method, params: $params);
	}

	/**
	 * Send Request to GPT API.
	 *
	 * @param array $messages
	 * @return array|null
	 * @throws Exception
	 */
	public function sendRequestToAI(array $messages): ?array {
		$params = [
			"model"					=>	$this->model,
			"messages"				=>	$messages,
		];
		if (!$this->isReasoning) $params["response_format"] = ["type" => "text"];
		if (!$this->isReasoning) $params["max_completion_tokens"] = 2048;
		if (!$this->isReasoning) $params["temperature"] = 1.25;
		if (!$this->isReasoning) $params["presence_penalty"] = 1.25;
		if (!$this->isReasoning) $params["frequency_penalty"] = 1;
		if (!$this->isReasoning) $params["top_p"] = 1;

		$headers = [
			"Content-Type: application/json",
			"Authorization: Bearer " . $this->token_ai,
		];

		for ($i = 0; $i < 5; $i++) {
			$jsonParams = json_encode($params);
			if ($jsonParams === false) throw new Exception("Ошибка JSON: " . json_last_error_msg());
			$response = $this->sendCurlRequest(url: $this->api_ai, params: $jsonParams, headers: $headers);
			if ($response !== null) return $response;
			usleep(1250);
		}

		return null;
	}

	/**
	 * Метод отправки сообщения в Telegram.
	 *
	 * @param string|int|null $chat_id
	 * @param string|null $text
	 * @param array $options
	 * @return false|int
	 */
	public function sendMessage(string|int|null $chat_id, ?string $text, array $options = []): false|int {
		if (empty($chat_id) || empty($text)) return false;

		$params = [
			"chat_id"					=>	$chat_id,
			"text"						=>	$text,
			"parse_mode"				=>	$options["parse_mode"] ?? "markdown",
			"reply_to_message_id"		=>	$options["reply_to_message_id"] ?? null,
			"protect_content"			=>	$options["protect_content"] ?? false,
			"disable_notification"		=>	$options["disable_notification"] ?? false,
		];

		$response = $this->sendRequestToTelegram(method: __FUNCTION__, params: $params);
		return isset($response["ok"]) && $response["ok"] && isset($response["result"]["message_id"]) ? $response["result"]["message_id"] : false;
	}

	/**
	 * Используйте этот метод для изменения текстовых сообщений.
	 * В случае успеха возвращается объект сообщения \TelegramBot\Api\Types\Message.
	 *
	 * @param string|int|null $chatId
	 * @param int|null $messageId
	 * @param string|null $text
	 * @param string|null $parseMode
	 * @param string|null $inlineMessageId
	 * @param bool $disablePreview
	 * @return false|string
	 */
	public function editMessageText(string|int|null $chatId, ?int $messageId, ?string $text, ?string $parseMode = "markdown", ?string $inlineMessageId = null, bool $disablePreview = false): false|string {
		if (empty($chatId) || empty($text) || empty($messageId)) return false;

		$params = [
			"chat_id"					=>	$chatId,
			"message_id"				=>	$messageId,
			"text"						=>	$text,
			"parse_mode"				=>	$parseMode ?? "markdown",
			"inline_message_id"			=>	$inlineMessageId,
			"disable_web_page_preview"	=>	$disablePreview ?? false,
		];

		$response = $this->sendRequestToTelegram(method: __FUNCTION__, params: $params);
		return isset($response["ok"]) && $response["ok"] && isset($response["result"]["message_id"]) ? $response["result"]["message_id"] : false;
	}

	/**
	 * Метод для анимированного изменения сообщения
	 *
	 * @param int|string $chatId
	 * @param int $messageId
	 * @param string $text
	 * @param string $parseMode
	 * @param bool $disablePreview
	 * @param int $sleep
	 * @return void
	 * @throws Exception
	 * @noinspection PhpUnused
	 */
	public function animatedEditMessageText(int|string $chatId, int $messageId, string $text, string $parseMode = "markdown", bool $disablePreview = true, int $sleep = 150000): void {
		$lines = explode(PHP_EOL, $text);
		$preparedText = "";

		foreach ($lines as $line) {
			if (!empty($preparedText)) $preparedText .= PHP_EOL;
			$preparedText .= $line;

			try {
				$this->editMessageText(
					chatId: $chatId,
					messageId: $messageId,
					text: $preparedText,
					parseMode: $parseMode,
					disablePreview: $disablePreview,
				);
			} catch (Exception $ex) {
				if (!str_contains($ex->getMessage(), "message is not modified")) throw $ex;	#	Если ошибка связана с тем, что сообщение не изменилось, просто отбрасываем её.
			}

			usleep($sleep);
		}
	}

	/**
	 * Splitting text into multiple parts for sending on Telegram (due to character limit).
	 *
	 * @param string $text
	 * @return array
	 */
	private function splitMessage(string $text): array {
		$chunks = [];
		while (strlen($text) > 3000) {
			$split_point = strrpos(substr($text, 0, 3000), " ");
			if ($split_point === false) $split_point = 3000;
			$chunks[] = substr($text, 0, $split_point);
			$text = substr($text, $split_point);
		}
		$chunks[] = $text;
		return $chunks;
	}

	/**
	 * Handling user messages.
	 *
	 * @param array $messageData
	 * @return void
	 * @noinspection SqlResolve
	 * @noinspection SqlWithoutWhere
	 * @noinspection SqlDialectInspection
	 * @throws Exception
	 */
	public function handleIncomingMessage(array $messageData): void {
		if (!isset($messageData["message"], $messageData["message"]["text"]) || empty($messageData["message"]["text"])) return;

		$chat_id = $messageData["message"]["chat"]["id"];
		$txt = $messageData["message"]["text"];
		$text = str_replace(
			['\\', '*', '~', '`'],
			['\\\\', '\\*', '\\~', '\\`'],
			$txt
		);

		#	Инициализация сообщения.
		$messageId = $this->sendMessage(chat_id: $chat_id, text: "Запрос принят!");

		# permissions check
		if (!in_array($chat_id, $this->allowed)) {
			$this->editMessageText(chatId: $chat_id, messageId: $messageId, text: "This chat is not authorized.\nUID: <code>$chat_id</code>", parseMode: "html");
			return;
		}

		# /start - is a just start
		if (mb_strtolower($text) === "/start" || mb_strtolower($text) === "старт") {
			$this->editMessageText(chatId: $chat_id, messageId: $messageId, text: "Bot worked!");
			return;
		}

		# /clear - erase memory for this chat.
		if (mb_strtolower($text) === "/clear" || mb_strtolower($text) === "спасибо") {
			$stmt = $this->db->prepare("DELETE FROM messages WHERE chat_id = ?");
			$stmt->execute([$chat_id]);
			$message_text	=	"Этот диалог завершён!\nЕсли нужно что-то вспомнить, просто сделай ответ на нужное сообщение!";
			$this->editMessageText(chatId: $chat_id, messageId: $messageId, text: $message_text);
			return;
		}

		# /clearAll - erase memory for all chats.
		if (mb_strtolower($text) === "/clearAll") {
			$stmt = $this->db->prepare("DELETE FROM messages");
			$stmt->execute();
			$this->editMessageText(chatId: $chat_id, messageId: $messageId, text: "The memory of all chats has been cleared.");
			return;
		}

		$this->editMessageText(chatId: $chat_id, messageId: $messageId, text: "Let's remember previous conversations...");
		$telegram_message_id = $messageData["message"]["message_id"];
		$this->storeMessage($chat_id, $telegram_message_id, "user", $text);
		$context = $this->getContext($chat_id);

		if (isset($messageData["message"]["reply_to_message"])) {
			$this->editMessageText(chatId: $chat_id, messageId: $messageId, text: "The user asked to recall the message...");
			$reply_id = $messageData["message"]["reply_to_message"]["message_id"];
			$repliedMessage = $this->getMessageByTelegramId($chat_id, $reply_id);
			if (!$repliedMessage) {
				$reply_text = $messageData["message"]["reply_to_message"]["text"] ?? "";
				$reply_role = (!empty($messageData["message"]["reply_to_message"]["from"]["is_bot"])) ? "assistant" : "user";
				$this->storeMessage($chat_id, $reply_id, $reply_role, $reply_text);
				$repliedMessage = ["telegram_message_id" => $reply_id, "role" => $reply_role, "text" => $reply_text];
			}
			$context = [$repliedMessage];
		}

		#	Depending on whether "reasoning" is used, we choose a role. Probably, this could help remember the context in reasoning.
		$gptMessages = [];
		if (!empty($this->CONTEXT)) $gptMessages[] = ["role" => !empty($this->isReasoning) ? "user" : "system", "content" => $this->CONTEXT]; # it's possible that won't work...
//		if (empty($this->isReasoning) && !empty($this->CONTEXT)) $gptMessages[] = ["role" => "system", "content" => $this->CONTEXT]; # maybe have to use this instead prev.
		foreach ($context as $msg) $gptMessages[] = ["role" => $msg["role"], "content" => $msg["text"]];

		$this->editMessageText(chatId: $chat_id, messageId: $messageId, text: "Отправляем запрос к ИИ...");
		$aiResponse = $this->sendRequestToAI(messages: $gptMessages);
		if ($aiResponse && isset($aiResponse["choices"][0]["message"]["content"])) {
			$aiText = trim($aiResponse["choices"][0]["message"]["content"]);
			if (!empty($aiText)) {
				$chunks = $this->splitMessage(text: $aiText);
				if (count($chunks) === 1) {
					$this->animatedEditMessageText(chatId: $chat_id, messageId: $messageId, text: $chunks[0]);
				} else {
					$this->animatedEditMessageText(chatId: $chat_id, messageId: $messageId, text: $chunks[0]);
					$secondMessageId = $this->sendMessage(chat_id: $chat_id, text: "Инициализирую вторую часть сообщения", options: ["reply_to_message_id" => $telegram_message_id]);
					$this->animatedEditMessageText(chatId: $chat_id, messageId: $secondMessageId, text: $chunks[1]);
				}
				$this->storeMessage($chat_id, 0, "assistant", $aiText);
			} else {
				$this->sendMessage(chat_id: $chat_id, text: "Ответ от ИИ пустой.");
			}
		} elseif ($aiResponse && isset($aiResponse["error"])) {
			$text = sprintf("ERROR:%s%s%s%s", PHP_EOL, is_array($aiResponse["error"]) && isset($aiResponse["error"]["message"]) ? $aiResponse["error"]["message"] : $aiResponse["error"], str_repeat(PHP_EOL, 2), json_encode($aiResponse, JSON_PRETTY_PRINT));
			$this->sendMessage(chat_id: $chat_id, text: $text);
		} else {
			$this->sendMessage(chat_id: $chat_id, text: "Нет валидного ответа от ИИ.");
		}
	}
}
