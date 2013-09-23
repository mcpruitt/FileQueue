<?php
use \Orchestra\Testbench\TestCase;
use \Mcpruitt\FileQueue\FileQueue;
use \Mockery as m;

class FileQueueTest extends TestCase {

  public function tearDown(){ m::close(); }

  public function test_default_folder(){
    $sut = new FileQueue();
    
    $expectedPath = storage_path() . "/FileQueue/";
    $expectedPath = str_replace("\\", "/", $expectedPath);

    $this->assertSame($expectedPath,$sut->getBaseDirectory());
  }

  public function test_speicfying_a_folder(){
    $sut = new FileQueue(array(
      "directory" => "/var/storage"
    ));
    $this->assertSame("/var/storage/", $sut->getBaseDirectory());
  }

  public function test_specifying_a_folder_during_runtime(){
    $sut = new FileQueue();
    $sut->setBaseDirectory("/tmp");
    $this->assertsame("/tmp/", $sut->getBaseDirectory());
  }

  public function test_default_queue_name_is_default(){
    $sut = new FileQueue();
    $this->assertSame("default", $sut->getDefaultQueueName());
  }

  public function test_specifying_default_queue_name_in_config(){
    $sut = new FileQueue(array("defaultqueue" => "test"));
    $this->assertSame("test",$sut->getDefaultQueueName());
  }

  public function test_special_characters_in_queue_name_removed(){
    $sut = new FileQueue();
    $sut->setDefaultQueueName("invalid>chars");
    $this->assertSame("invalidchars", $sut->getDefaultQueueName());
  }
}