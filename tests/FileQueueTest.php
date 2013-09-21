<?php
use \Orchestra\Testbench\TestCase;
use \Mcpruitt\FileQueue\FileQueue;
use \Mockery as m;

class FileQueueTest extends TestCase {

  public function tearDown(){ m::close(); }

  public function test_it_creates_directory_on_construction(){
    \File::shouldReceive("isDirectory")->twice()->with(m::any())->andReturn(false);
    \File::shouldreceive("makeDirectory")->twice()->with(m::any())->andReturnNull();
    $sut = new FileQueue();
  }
}