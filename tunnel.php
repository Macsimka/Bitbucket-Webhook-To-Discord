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
        $file = fopen("error.txt", "w");
        fwrite($file, "$error\n");
        fclose($file);
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

        return $this->postToDiscord($converted);
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
     * @return array
     * @throws BitbucketWebhookToDiscordException
     */
    public function convertFromBitbucket($webhookArray)
    {
        $username = lcfirst($webhookArray["actor"]["username"]);
        $userUrl = $webhookArray["actor"]["links"]["html"]["href"];
        $avatarUrl = $webhookArray["actor"]["links"]["avatar"]["href"];
        $repositoryName = $webhookArray["repository"]["name"] . "." . $webhookArray["repository"]["scm"];
        $branch = $webhookArray["push"]["changes"][0]["new"]["name"];
        $commits = [];

        foreach ($webhookArray["push"]["changes"][0]["commits"] as $commitArr) {
            $hash = substr($commitArr["hash"], 0, 7);
            $commitMessage = $commitArr["message"];

            $commits[] = [
                "name" => $hash . " (" . date("d-m-Y H:i", strtotime($commitArr["date"])) . ")",
                "value" => $commitMessage,
            ];
        }

        $commits = array_reverse($commits);
        $converted = [
            'embeds' => [
                [
                    "description" => "Pushed in BitBucket \"" . $repositoryName . "\" (Branch: " . $branch . ")\n",
                    "fields" => $commits,
                    "author" => [
                        "name" => $username,
                        "url" => $userUrl,
                        "icon_url" => $avatarUrl,
                    ],
                ],
            ],
        ];

        if ($converted === false) {
            throw new BitbucketWebhookToDiscordException("BitbucketWebhookToDiscord: Invalid output data");
        }

        return $converted;
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
    $id = "id_you_got_from_discord";
    $token = "token_you_got_from_discord";

    if (!isset($_GET['id']) && !isset($_GET['token']) && (!isset($_GET['service']) || $_GET['service'] !== 'Bitbucket')) {
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
