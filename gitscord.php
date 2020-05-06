<?php
class GitHubWebhookToDiscordException extends \Exception
{
    public function __construct($message, $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function errorRecording($error)
    {
        /*$file = fopen("error.txt", "w");
        fwrite($file, "$error\n");
        fclose($file);*/
        exit();
    }
}

class GitHubWebhookToDiscord
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
    
    public function proxy()
    {
        $webhookArray = $this->readJson();
        $converted = $this->convertFromGithub($webhookArray);
    }

    public function readJson()
    {
        $pre = file_get_contents('php://input');

        $input = json_decode($pre, true);

        if ($input === false) {
            throw new GitHubWebhookToDiscordException("GitHubWebhookToDiscord: Invalid JSON read");
        }

        return $input;
    }

    public function convertFromGithub($data)
    {
        $branch_name = substr($data['ref'], 11);
        
        foreach ($data["commits"] as $commit)
        {
            $commit_hash = substr($commit['id'], 0, 7);
            
            $discordMessage = [
                'username' => $data['sender']['login'],
                'avatar_url' => $data['sender']['avatar_url'],
                'content' => "[{$data['repository']['name']}:{$branch_name}:{$commit_hash}]({$commit['url']})\n{$commit['message']}"
            ];

            $this->postToDiscord($discordMessage);
		}
    }

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
            throw new GitHubWebhookToDiscordException("GitHubWebhookToDiscord: cURL error ({$errno}):\n {$error_message}");
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
            throw new GitHubWebhookToDiscordException("GitHubWebhookToDiscord: Error {$httpcode}:{$result}");
        }

        return $decoded_result;
    }
}

try {
        $id = "";
    $token = "";

    if (!isset($_GET['id']) && !isset($_GET['token'])) {
        throw new GitHubWebhookToDiscordException("GitHubWebhookToDiscord: Invalid request: parameter(s) missing");
    } else {
        $id = $_GET['id'];
        $token = $_GET['token'];
    }
    
    $webhook = new GitHubWebhookToDiscord($id, $token);
    $webhook->proxy();
} catch (GitHubWebhookToDiscordException $error) {
    $error->errorRecording($error);
}
