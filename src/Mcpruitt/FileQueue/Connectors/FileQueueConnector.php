<?php namespace Mcpruitt\FileQueue\Connectors;

use \Illuminate\Queue\Connectors\ConnectorInterface;
use \Mcpruitt\FileQueue\FileQueue;

class AsyncFileQueueConnector implements ConnectorInterface {

  /**
   * Establish a queue connection.
   *
   * @param  array  $config
   * @return \Illuminate\Queue\QueueInterface
   */
  public function connect(array $config)
  {
    return new FileQueue();
  }
}