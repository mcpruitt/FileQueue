<?php
namespace Mcpruitt\FileQueue\Jobs;

class JsonJobSerializer implements JobSerializerInterface {
  public function serialize(FileQueueJob $job) {
    return json_encode($job);
  }
  public function deserialize($string) {
    return json_decode($string);
  }
}