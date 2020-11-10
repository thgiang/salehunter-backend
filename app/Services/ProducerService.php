<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RdKafka\Conf;
use RdKafka\Producer;
use Exception;

class ProducerService
{
    const TOPIC_MISSING_ERROR_MESSAGE = 'Topic is not set'; // Topic missing error message
    const FLUSH_ERROR_MESSAGE = 'librdkafka unable to flush, messages might be lost'; // Flush error message

    private static $_instance = null;

    protected $producer;
    protected $topic;
    protected $payload;

    /*
     * Singleton constructor
     */
    private function __construct($broker)
    {
        $this->initialize($broker);
    }

    public function initialize($broker)
    {
        $conf = new Conf();

        $broker = !empty($broker) ? $broker : env('KAFKA_WEBHOOK_BROKER');
        $conf->set('metadata.broker.list', $broker);
		$conf->set('security.protocol', 'SASL_SSL');//sasl_plaintext SASL_SSL
		$conf->set('sasl.mechanisms', 'SCRAM-SHA-256');
		$conf->set('sasl.username', env('KAFKA_USERNAME'));
		$conf->set('sasl.password', env('KAFKA_PASSWORD'));
		$conf->set('ssl.certificate.location', __DIR__ . '/kafka_cert.pem');
		$conf->set('ssl.ca.location', __DIR__ . '/kafka_cert.pem');
		//$conf->set('ssl.username', env('KAFKA_USERNAME'));
		//$conf->set('ssl.password', env('KAFKA_PASSWORD'));
		
		
        // $conf->set('compression.type', 'snappy');

        if (!empty(env('KAFKA_DEBUG', false))) {
            $conf->set('log_level', LOG_DEBUG);
            $conf->set('debug', 'all');
        }

        $this->producer = new Producer($conf);
    }

    public static function getInstance($broker = null)
    {
//        if (empty(static::$_instance)) {
        static::$_instance = new static($broker);
//        }

        return static::$_instance;
    }

    /**
     * Set kafka topic
     *
     * @param string $topic
     * @return $this
     */
    public function setTopic(string $topic)
    {
        $this->topic = $topic;

        return $this;
    }

    /**
     * Get topic
     *
     * @return mixed
     * @throws Exception
     */
    public function getTopic()
    {
        if (!$this->topic) {
            Log::error(self::TOPIC_MISSING_ERROR_MESSAGE);
            throw new Exception(self::TOPIC_MISSING_ERROR_MESSAGE);
        }

        return $this->topic;
    }

    /**
     * Produce and send a single message to broker
     *
     * @param array $data
     * @param null $key
     * @throws Exception
     * @return void
     */
    public function send(array $data, $key = null)
    {
        $topic = $this->producer->newTopic($this->getTopic());

        $this->buildPayload($data);

        // RD_KAFKA_PARTITION_UA, lets librdkafka choose the partition.
        // Messages with the same "$key" will be in the same topic partition.
        // This ensure that messages are consumed in order.
        $topic->produce(RD_KAFKA_PARTITION_UA, 0, $this->payload, $key);

        // pull for any events
        $this->producer->poll(0);

        $this->flush();
    }

    /**
     * Produce and send a multiple message to broker
     *
     * @param array $data
     * @param null $key
     * @throws Exception
     * @return void
     */
    public function sendMultiple(array $dataMultiple, $key = null)
    {
        $topic = $this->producer->newTopic($this->getTopic());

        foreach ($dataMultiple as $data) {
            $this->buildPayload($data);

            // RD_KAFKA_PARTITION_UA, lets librdkafka choose the partition.
            // Messages with the same "$key" will be in the same topic partition.
            // This ensure that messages are consumed in order.
            $topic->produce(RD_KAFKA_PARTITION_UA, 0, $this->payload, $key);

            // pull for any events
            $this->producer->poll(0);
        }

        $this->flush();
    }

    /**
     * librdkafka flush ensure message produced properly
     *
     * @param int $timeout (timeout in milliseconds)
     * @throws Exception
     */
    protected function flush(int $timeout = 10000)
    {
        // $result = $this->producer->flush($timeout);
        for ($flushRetries = 0; $flushRetries < 3; $flushRetries++) {
            $result = $this->producer->flush($timeout);
            if (RD_KAFKA_RESP_ERR_NO_ERROR === $result) {
                break;
            }
        }

        if (RD_KAFKA_RESP_ERR_NO_ERROR !== $result) {
            Log::error(self::FLUSH_ERROR_MESSAGE);
            throw new Exception(self::FLUSH_ERROR_MESSAGE);
        }
    }

    protected function buildPayload(array $data = [])
    {
        $this->payload = json_encode($data);
    }
}