<?php
use \Orchestra\Testbench\TestCase;
use \Mcpruitt\FileQueue\Jobs\FileQueueJob;
use \org\bovigo\vfs\vfsStream;
use \Mockery as m;

class FileQueueJobTest extends TestCase {

  protected $_vfs;
  
  public static $JobHanlderExampleVariableSet = false;

  public function tearDown() { m::close(); }

  public function setUp(){
    parent::setUp();
    $this->_vfs = vfsStream::setup("FileQueueTest");
  }

  /*
  public function test_job_id_with_simple_name(){
    $job = $this->_getJob("simplename", array());
    $this->assertStringStartsWith("job-simplename", $job->getJobId());
  }

  public function test_job_id_with_namespace_name(){
    $job = $this->_getJob("\\Some\\Namespace\\Job", array());
    $this->assertStringStartsWith("job-Some-Namespace-Job", $job->getJobId());
  }

  public function test_job_ends_in_microtime(){
    $testTime = microtime(true);
    $job = $this->_getJob("simplename",array(),$testTime);
    $this->assertStringEndsWith("{$testTime}", $job->getJobId());
  }
  */
 
  public function test_fire_increments_attempts(){
    $job = $this->_getJob(function(){});
    
    $job->fire();
    $this->assertEquals(1, $job->attempts());

    $job->fire();
    $this->assertEquals(2, $job->attempts());
  }

  public function test_fire_calls_closure(){
    $called = false;
    $job = $this->_getJob(function() use(&$called) {
      $called = true;
    });
    $job->fire();
    $this->assertTrue($called);
  }

  public function test_fire_calls_job_class(){
    $job = $this->_getJob("JobHandlerExample");
    $job->fire();
    $this->assertTrue(self::$JobHanlderExampleVariableSet);
  }

  private function _getJob($jobName = "test", $jobData = "", $time = null, $queue = null) {
    $time = $time === null ? microtime(true) : $time;
    return new FileQueueJob($this->app, $queue, $jobName, $jobData, $time);
  }
}

class JobHandlerExample {
  public function fire($job, $data) {
    FileQueueJobTest::$JobHanlderExampleVariableSet = true;
  }
}