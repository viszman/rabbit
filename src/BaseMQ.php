<?php

namespace Viszman;


use Exception;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Class BaseMQ
 *
 * @package App
 * usage just extend it
 */
abstract class BaseMQ
{
    /**
     * @var string
     */
    private $queueName;

    /**
     * @var AMQPStreamConnection
     */
    private $connection;
    /**
     * @var AMQPChannel
     */
    private $channel;
    /**
     * @var string
     */
    private $retryQueueName;
    /**
     * @var int
     */
    private $retryTimeSec;
    private $exchange;
    private $retryExchange;

    public function __construct(string $queueName = 'somequeue', int $retryTimeSec = 4)
    {
        $this->queueName = $queueName;
        $this->retryQueueName = $queueName.'_retry';
        $this->retryTimeSec = $retryTimeSec;
        $this->exchange = $this->queueName.'_exchange';
        $this->retryExchange = $this->retryQueueName.'_exchange';
        $this->setupConnection();
    }

    protected function setupConnection(): void
    {
        $settings = $this->getConnectionSettings();
        $exchange = $this->queueName.'_exchange';
        $retryExchange = $this->retryExchange;

        $connection = new AMQPStreamConnection(
            $settings['host'],
            $settings['port'],
            $settings['user'],
            $settings['password'],
            '/',
            false,
            'AMQPLAIN',
            null,
            'en_US',
            8.0
        );
        $channel = $connection->channel();
        $channel->exchange_declare($exchange, 'direct');
        $channel->exchange_declare($retryExchange, 'direct');

        $channel->queue_declare(
            $this->queueName,
            false,
            false,
            false,
            false,
            false,
            ['x-dead-letter-exchange' => ['S', $retryExchange]]
        );
        $channel->queue_declare(
            $this->retryQueueName,
            false,
            false,
            false,
            false,
            false,
            [
                'x-message-ttl' =>
                    [
                        'I',
                        $this->retryTimeSec * 1000,
                    ],
                'x-dead-letter-exchange' =>
                    [
                        'S',
                        $exchange,
                    ],
            ]
        );

        $channel->queue_bind($this->queueName, $exchange);
        $channel->queue_bind($this->retryQueueName, $retryExchange);

        $channel->basic_qos(null, 2, null);

        $this->connection = $connection;
        $this->channel = $channel;
    }

    protected function getConnectionSettings(): array
    {
        $connectionString = getenv('RABBITMQ_URL');
        if (!$connectionString) {
            $connectionString = $_SERVER['RABBITMQ_URL'];
        }

        $settings = explode('@', $connectionString);
        [$user, $password] = explode(':', $settings[0]);
        [$host, $port] = explode(':', $settings[1]);

        return [
            'user' => $user,
            'password' => $password,
            'host' => $host,
            'port' => $port,
        ];
    }

    public function listen(): void
    {
        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }

        $this->close();
    }

    public function close(): bool
    {
        $this->channel->close();
        $this->connection->close();

        return true;
    }

    public function createTask(string $body): void
    {
        $message = new AMQPMessage($body, [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);
        for ($i = 0; $i < 5; $i++) {
            try {
                $this->channel->basic_publish($message, $this->exchange);
                break;
            } catch (Exception $e) {
                print $e->getMessage();
                sleep(2);
            }
        }

    }

    public function setupWorker($callback): void
    {
        $this->channel->basic_consume(
            $this->queueName,
            '',
            false,
            false,
            false,
            false, $callback
        );
    }
}
