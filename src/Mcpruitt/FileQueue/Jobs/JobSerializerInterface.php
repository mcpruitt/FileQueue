<?php
namespace Mcpruitt\FileQueue\Jobs;

interface JobSerializerInterface {
  public function serialize(FileQueueJob $job);

  public function deserialize($string);
}