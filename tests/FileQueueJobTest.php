<?php
use \Orchestra\Testbench\TestCase;
use \Mcpruitt\FileQueue\Jobs\FileQueueJob;
use \Mockery as m;

class FileQueueJobTest extends TestCase {

  public static $JobHanlderExampleVariableSet = false;

  public function tearDown() { m::close(); }

  public function test_job_id_with_simple_name(){
    $job = $this->_getJob("simplename", []);
    $this->assertStringStartsWith("job-simplename", $job->getJobId());
  }

  public function test_job_id_with_namespace_name(){
    $job = $this->_getJob("\\Some\\Namespace\\Job", []);
    $this->assertStringStartsWith("job-Some-Namespace-Job", $job->getJobId());
  }

  public function test_job_ends_in_microtime(){
    $testTime = microtime(true);
    $job = $this->_getJob("simplename",[],$testTime);
    $this->assertStringEndsWith("{$testTime}", $job->getJobId());
  }

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

  public function test_deleting_the_job_deletes_the_file(){    
    \File::shouldReceive("exists")->once()->with(m::any())->andReturn(true);
    \File::shouldReceive("delete")->once()->with(m::any())->andReturnNull();
    $job = $this->_getJob(function(){});
    $job->delete();
  }

  public function test_release_moves_job_file_back(){
    \File::shouldReceive("put")->once()->with(m::any(), m::any())->andReturnNull();
    \File::shouldReceive("delete")->once()->with(m::any())->andreturnNull();
    $job = $this->_getJob(function(){});
    $job->release();
  }

  public function test_release_updates_job_due_time(){
    \File::shouldReceive("put")->once()->with(m::any(), m::any())->andReturnNull();
    \File::shouldReceive("delete")->once()->with(m::any())->andreturnNull();
    $job = $this->_getJob(function(){});
    $jobdue = $job->due;
    $job->release(100);
    $this->assertTrue($job->due > $jobdue + 100);
  }

  private function _getJob($jobName = "test", $jobData = null, $time = null, $queue = null) {
    $time = $time === null ? microtime(true) : $time;
    return new FileQueueJob($this->app, $jobName,$jobData,$time,$queue);
  }
}

class JobHandlerExample {
  public function fire($job, $data) {
    FileQueueJobTest::$JobHanlderExampleVariableSet = true;
  }
}