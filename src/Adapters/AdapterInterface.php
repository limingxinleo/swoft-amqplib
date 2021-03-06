<?php
/**
 * This file is part of Swoft.
 *
 * @link     https://swoft.org
 * @document https://doc.swoft.org
 * @contact  limingxin@swoft.org
 * @license  https://github.com/swoft-cloud/swoft/blob/master/LICENSE
 */
namespace Swoftx\Amqplib\Adapters;

use PhpAmqpLib\Connection\AbstractConnection;
use Swoftx\Amqplib\ConfigInterface;
use Swoftx\Amqplib\Params;

interface AdapterInterface
{
    public function initConnection(ConfigInterface $config, Params $params): AbstractConnection;
}
