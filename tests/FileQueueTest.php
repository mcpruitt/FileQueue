<?php
use \Orchestra\Testbench\TestCase;
use \Mcpruitt\FileQueue\FileQueue;
use \org\bovigo\vfs\vfsStream;
use \Mockery as m;

class FileQueueTest extends TestCase {

  protected $_vfs;

  public function tearDown(){ m::close(); }

  public function setUp(){
    parent::setUp();
    $this->_vfs = vfsStream::setup("FileQueueTest");
  }

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

  public function test_pop_gives_null_if_no_jobs_exist(){
    $sut = new FileQueue();
    $this->assertSame(null, $sut->pop());
  }

  public function test_pop_does_not_get_future_jobs(){
    $sut = new FileQueue();
    $sut->later(1000000, function(){}, array());
    $this->assertSame(null, $sut->pop());
  }
}