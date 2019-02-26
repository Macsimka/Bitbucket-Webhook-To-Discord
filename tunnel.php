<?php

/**
 * Class BitbucketWebhookToDiscordException
 */
class BitbucketWebhookToDiscordException extends \Exception
{
    /**
     * BitbucketWebhookToDiscordException constructor.
     *
     * @param                $message
     * @param int            $code
     * @param Exception|null $previous
     */
    public function __construct($message, $code = 0, Exception $previous = null)
    {
        // here can be your code
        parent::__construct($message, $code, $previous);
    }

    /**
     * Writing an error to a file
     *
     * @param $error
     */
    public function errorRecording($error)
    {
        /*$file = fopen("error.txt", "w");
        fwrite($file, "$error\n");
        fclose($file);*/
        exit();
    }
}

/**
 * Class BitbucketWebhookToDiscord
 */
class BitbucketWebhookToDiscord
{
    const DISCORD_BASE_URL = "https://discordapp.com/api/";
    protected $webhookId;
    protected $webhookToken;

    /**
     * DiscordWebhook constructor.
     *
     * @param $webhookId
     * @param $webhookToken
     */
    public function __construct($webhookId, $webhookToken)
    {
        $this->webhookId = $webhookId;
        $this->webhookToken = $webhookToken;
    }

    /**
     * A function that does everything
     *
     * @return mixed
     * @throws BitbucketWebhookToDiscordException
     */
    public function proxy()
    {
        $webhookArray = $this->readJson();
        $converted = $this->convertFromBitbucket($webhookArray);
    }

    /**
     * Reads JSON from Bitbucket and decode to array
     *
     * @return mixed
     * @throws BitbucketWebhookToDiscordException
     */
    public function readJson()
    {
        $pre = file_get_contents('php://input');

        $input = json_decode($pre, true);

        if ($input === false) {
            throw new BitbucketWebhookToDiscordException("BitbucketWebhookToDiscord: Invalid JSON read");
        }

        return $input;
    }

    /**
     * Converts Bitbucket webhook input into format Discord can understand
     *
     * @param $webhookArray
     *
     * @throws BitbucketWebhookToDiscordException
     */
    public function convertFromBitbucket($data)
    {
        $base_link = "https://bitbucket.org/";

		$repo = $data['repository']['name'];
		$url = $base_link . $data['repository']['full_name'];
        
		$user = [
			"name" => $data['actor']['display_name'],
			"icon_url" => $data['actor']['links']['avatar']['href'],
			"url" => $base_link . $data['actor']['username']
		];

		foreach ($data['push']['changes'] as $change) {
			$branch = ($change['new'] !== null) ? $change['new']['name'] : $change['old']['name'];
			$commits = [];
            
			foreach ($change["commits"] as $commit) {
				$commit_hash = substr($commit['hash'], 0, 7);

                $discordMessage = [
                    'username' => isset($commit['author']['user']) ? $commit['author']['user']['display_name'] : "Bitbucket",
                    'avatar_url' => isset($commit['author']['user']) ? $commit['author']['user']['links']['avatar']['href'] : "https://www.shareicon.net/download/2015/09/24/106562_branch_512x512.png",
                    'content' => "[{$repo}:{$branch}:{$commit_hash}]({$commit['links']['html']['href']})\n{$commit['message']}"
                ];

                $this->postToDiscord($discordMessage);
			}
		}
    }

    /**
     * Posts data to Discord
     *
     * @param $data
     *
     * @return mixed
     * @throws BitbucketWebhookToDiscordException
     */
    public function postToDiscord($data)
    {
        sleep(3);
        
        $url = self::DISCORD_BASE_URL . "/webhooks/{$this->webhookId}/{$this->webhookToken}";
        $header = ['Content-Type: application/json',];

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($curl);

        if ($errno = curl_errno($curl)) {
            $error_message = curl_error($curl);
            curl_close($curl);
            throw new BitbucketWebhookToDiscordException("BitbucketWebhookToDiscord: cURL error ({$errno}):\n {$error_message}");
        }

        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $decoded_result = json_decode($result, true);
        $allowed_http_codes = [
            200,    // The request completed successfully
            201,    // The entity was created successfully
            204,    // The request completed successfully but returned no content
            304     // The entity was not modified (no action was taken)
        ];

        if (!in_array($httpcode, $allowed_http_codes) || ($decoded_result === null && $httpcode !== 204)) {
            throw new BitbucketWebhookToDiscordException("BitbucketWebhookToDiscord: Error {$httpcode}:{$result}");
        }

        return $decoded_result;
    }
}

try {
    $id = "";
    $token = "";

    if (!isset($_GET['id']) && !isset($_GET['token'])) {
        throw new BitbucketWebhookToDiscordException("BitbucketWebhookToDiscord: Invalid request: parameter(s) missing");
    } else {
        $id = $_GET['id'];
        $token = $_GET['token'];
    }

    $webhook = new BitbucketWebhookToDiscord($id, $token);
    $webhook->proxy();
} catch (BitbucketWebhookToDiscordException $error) {
    $error->errorRecording($error);
}
