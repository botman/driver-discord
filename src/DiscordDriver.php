<?php

namespace BotMan\Drivers\Discord;

use Discord\Discord;
use BotMan\BotMan\Users\User;
use BotMan\BotMan\BotManFactory;
use Discord\Parts\Channel\Message;
use Illuminate\Support\Collection;
use React\Promise\PromiseInterface;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Interfaces\DriverInterface;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Interfaces\DriverEventInterface;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;

class DiscordDriver implements DriverInterface
{
    /** @var Message */
    protected $message;

    /** @var Discord */
    protected $client;

    /** @var string */
    protected $bot_id;

    const DRIVER_NAME = 'Discord';

    protected $file;

    /**
     * Driver constructor.
     * @param array $config
     * @param Discord $client
     */
    public function __construct(array $config, Discord $client)
    {
        $this->event = Collection::make();
        $this->config = Collection::make($config);
        $this->client = $client;

        $this->client->on('message', function (Message $message) {
            $this->message = $message;
        });
    }

    /**
     * Connected event.
     */
    public function connected()
    {
    }

    /**
     * Return the driver name.
     *
     * @return string
     */
    public function getName()
    {
        return self::DRIVER_NAME;
    }

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return false;
    }

    /**
     * @return bool|DriverEventInterface
     */
    public function hasMatchingEvent()
    {
        return false;
    }

    /**
     * @param  IncomingMessage $message
     * @return Answer
     */
    public function getConversationAnswer(IncomingMessage $message)
    {
        return Answer::create($this->message->content)->setMessage($message);
    }

    /**
     * Retrieve the chat message.
     *
     * @return array
     */
    public function getMessages()
    {
        $messageText = $this->message->content;
        $user_id = $this->message->author->id;
        $channel_id = $this->message->channel->id;

        $message = new IncomingMessage($messageText, $user_id, $channel_id, $this->message);
        $message->setIsFromBot($this->isBot());

        return [$message];
    }

    /**
     * @return bool
     */
    protected function isBot()
    {
        return false;
    }

    /**
     * @param string|\BotMan\BotMan\Messages\Outgoing\Question|IncomingMessage $message
     * @param IncomingMessage $matchingMessage
     * @param array $additionalParameters
     * @return mixed
     */
    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
        $payload = [
            'message' => '',
            'embed' => '',
        ];

        if ($message instanceof OutgoingMessage) {
            $payload['message'] = $message->getText();

            $attachment = $message->getAttachment();

            if (! is_null($attachment)) {
                if ($attachment instanceof Image) {
                    $payload['embed'] = [
                        'image' => [
                            'url' => $attachment->getUrl(),
                        ],
                    ];
                }
            }
        } else {
            $payload['message'] = $message;
        }

        return $payload;
    }

    /**
     * @param mixed $payload
     * @return PromiseInterface
     */
    public function sendPayload($payload)
    {
        return $this->message->channel->sendMessage($payload['message'], false, $payload['embed']);
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return ! is_null($this->config->get('token'));
    }

    /**
     * Send a typing indicator.
     * @param IncomingMessage $matchingMessage
     * @return mixed
     */
    public function types(IncomingMessage $matchingMessage)
    {
    }

    /**
     * Retrieve User information.
     * @param IncomingMessage $matchingMessage
     * @return User
     */
    public function getUser(IncomingMessage $matchingMessage)
    {
        $user = null;
        $this->client->getUserById($matchingMessage->getSender())->then(function ($_user) use (&$user) {
            $user = $_user;
        });
        if (! is_null($user)) {
            return new User($matchingMessage->getSender(), $user->getFirstName(), $user->getLastName(),
                $user->getUsername());
        }

        return new User($this->message->author->id, '', '', $this->message->author->username);
    }

    /**
     * @return Discord
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Low-level method to perform driver specific API requests.
     *
     * @param $endpoint
     * @param array $parameters
     * @param IncomingMessage $matchingMessage
     * @return \React\Promise\PromiseInterface
     */
    public function sendRequest($endpoint, array $parameters, IncomingMessage $matchingMessage)
    {
    }

    /**
     * Tells if the stored conversation callbacks are serialized.
     *
     * @return bool
     */
    public function serializesCallbacks()
    {
        return false;
    }

    /**
     * Load factory extensions.
     */
    public static function loadExtension()
    {
        $factory = new Factory();

        BotManFactory::extend('createForDiscord', [$factory, 'createForDiscord']);
        BotManFactory::extend('createUsingDiscord', [$factory, 'createUsingDiscord']);
    }
}
