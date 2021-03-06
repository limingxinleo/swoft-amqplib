<?php
/**
 * This file is part of Swoft.
 *
 * @link     https://swoft.org
 * @document https://doc.swoft.org
 * @contact  limingxin@swoft.org
 * @license  https://github.com/swoft-cloud/swoft/blob/master/LICENSE
 */

namespace SwoftTest\Testing;

use PhpAmqpLib\Message\AMQPMessage;
use Swoftx\Amqplib\Connection;
use Swoftx\Amqplib\Message\Consumer;

class DemoConsumer extends Consumer
{
    protected $exchange = 'demo';

    protected $queue = 'demo.queue';

    protected $routingKey = 'test';

    public function handle($data): bool
    {
        $id = $data['id'];
        $reject = $data['reject'] ?? false;

        if ($reject) {
            throw new \Exception('reject = true');
        }

        file_put_contents(TESTS_PATH . '/' . $id, $id);
        return true;
    }

    /**
     * 异常捕获
     * @param \Throwable $ex
     */
    protected function catch(\Throwable $ex, $data, AMQPMessage $msg)
    {
        $id = $data['id'];
        file_put_contents(TESTS_PATH . '/' . $id . 'reject', $id);

        return $this->ack($msg);
    }

    protected function getConnection(): Connection
    {
        return \SwoftTest\Testing\Connection::getInstance()->build();
    }
}
