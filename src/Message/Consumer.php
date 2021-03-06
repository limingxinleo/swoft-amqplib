<?php
/**
 * This file is part of Swoft.
 *
 * @link     https://swoft.org
 * @document https://doc.swoft.org
 * @contact  limingxin@swoft.org
 * @license  https://github.com/swoft-cloud/swoft/blob/master/LICENSE
 */

namespace Swoftx\Amqplib\Message;

use PhpAmqpLib\Message\AMQPMessage;
use Swoftx\Amqplib\Exceptions\MessageException;

abstract class Consumer extends Message implements ConsumerInterface
{
    protected $queue;

    protected $status = true;

    protected $requeue = true;

    protected $signals = [
        SIGQUIT,
        SIGTERM,
        SIGTSTP
    ];

    abstract public function handle($data): bool;

    public function callback(AMQPMessage $msg)
    {
        $packer = $this->getPacker();
        $body = $msg->getBody();
        $data = $packer->unpack($body);

        try {
            if ($this->handle($data)) {
                $this->ack($msg);
            } else {
                $this->reject($msg);
            }
        } catch (\Throwable $ex) {
            $this->catch($ex, $data, $msg);
        }
    }

    public function consume()
    {
        pcntl_async_signals(true);

        foreach ($this->signals as $signal) {
            pcntl_signal($signal, [$this, 'signalHandler']);
        }

        $this->channel->basic_consume(
            $this->queue,
            $this->routingKey,
            false,
            false,
            false,
            false,
            [$this, 'callback']
        );

        while ($this->status && count($this->channel->callbacks) > 0) {
            $this->channel->wait();
        }

        $this->channel->close();
    }

    /**
     * 消费成功应答
     */
    protected function ack(AMQPMessage $msg)
    {
        $this->channel->basic_ack($msg->delivery_info['delivery_tag']);
    }

    /**
     * 当前消费者拒绝处理
     */
    protected function reject(AMQPMessage $msg)
    {
        $this->channel->basic_reject($msg->delivery_info['delivery_tag'], $this->requeue);
    }

    /**
     * 异常捕获
     * @param \Throwable $ex
     */
    protected function catch(\Throwable $ex, $data, AMQPMessage $msg)
    {
        return $this->reject($msg);
    }

    /**
     * 信号处理器
     * @author limx
     */
    public function signalHandler()
    {
        $this->status = false;
    }

    /**
     * 检验消息队列配置是否合法
     * @author limx
     * @throws MessageException
     */
    protected function check()
    {
        if (!isset($this->queue)) {
            throw new MessageException('queue is required!');
        }

        parent::check();
    }

    protected function declare()
    {
        if (!$this->isDeclare()) {
            $this->channel->exchange_declare($this->exchange, $this->type, false, true, false);

            $header = [
                'x-ha-policy' => ['S', 'all']
            ];
            $this->channel->queue_declare($this->queue, false, true, false, false, false, $header);
            $this->channel->queue_bind($this->queue, $this->exchange, $this->routingKey);

            $key = sprintf('consumer:%s:%s:%s:%s', $this->exchange, $this->type, $this->queue, $this->routingKey);
            $this->getCacheManager()->set($key, 1);
        }
    }

    /**
     * 是否已经声明过exchange、queue并进行绑定
     * @author limx
     * @return bool
     */
    protected function isDeclare()
    {
        $key = sprintf('consumer:%s:%s:%s:%s', $this->exchange, $this->type, $this->queue, $this->routingKey);
        if ($this->getCacheManager()->has($key)) {
            return true;
        }
        return false;
    }
}
