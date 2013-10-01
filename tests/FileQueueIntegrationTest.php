<?php
use \Orchestra\Testbench\TestCase;

use \Mcpruitt\FileQueue\Jobs\FileQueueJob;

use org\bovigo\vfs\vfsStream; 
use org\bovigo\vfs\visitor\vfsStreamStructureVisitor; 
use org\bovigo\vfs\visitor\vfsStreamPrintVisitor;

use \Mockery as m;

class FileQueueIntegrationTest extends TestCase {

  protected $_jobNameRegex = "/job\-(?<jobname>.*)\-(?<jobdue>[0-9]+\.[0-9]+)\-(?<jobattempts>[0-9]*)\.json/";

  public static $JobHanlderExampleVariableSet = false;

  public function tearDown() { m::close(); }

  public function setUp(){
    vfsStream::setup("root");    
    parent::setUp();
  }

  protected function getPackageProviders() {
    return array("Mcpruitt\FileQueue\FileQueueServiceProvider");
  }

  protected function getEnvironmentSetUp($app) {
    $app['config']->set('queue.connections.file', array(
      'driver'    => 'file',
      'directory' => vfsStream::url("root/custom-base-dir")
    ));
    $app['config']->set('queue.default','file');
  }

  /**
   * The base folder should be created when a job is pushed, not before.
   */
  public function test_base_folder_is_created_on_push(){
    $this->assertFalse(is_dir($this->app['queue']->getBaseDirectory()));
    \Queue::push(function(){}, array());
    $this->assertFileExists($this->app['queue']->getBaseDirectory());
  }

  /**
   * After pushing an item to the queue the default folder should be created.
   */
  public function test_default_queue_folder_is_created_on_push(){
    $defaultFolder = vfsStream::url("root/custom-base-dir/default");
    $this->assertFalse(is_dir($defaultFolder));
    \Queue::push(function(){}, array());
    $this->assertFileExists($defaultFolder);
  }

  /**
   * After pushing an item to the queue a job file should be created.
   */
  public function test_pushing_a_job_creates_a_job_file(){
    // Arrange
    $baseDir = vfsStream::url("root/custom-base-dir/default/");
    
    // Act
    \Queue::push(function(){}, array());    
    $jsonFiles = File::allFiles($baseDir);

    // Assert
    $this->assertEquals(1, count($jsonFiles));    
  }

  public function test_job_filename_is_in_proper_format(){
    // Arrange
    $baseDir = vfsStream::url("root/custom-base-dir/default/");
    
    // Act
    \Queue::push(function(){}, array());    
    $jsonFiles = File::allFiles($baseDir);
    
    // Assert
    $this->assertRegExp($this->_jobNameRegex, $jsonFiles[0]->getFileName());
  }

  public function test_inprocess_folder_is_not_created_from_push(){
    \Queue::push(function($job) use (&$inprocess) {}, array());
    $this->assertFalse(file_exists(vfsStream::url("root/custom-base-dir/default/inprocess")));
  }

  public function test_job_is_moved_during_processing(){

    \Queue::push(function($job) use (&$inprocess) {}, array());

    $job = \Queue::pop();
    $this->assertFileExists(vfsStream::url("root/custom-base-dir/default/inprocess"),
                            "Inprocess directory should exist while job is being processed.");

    $this->assertEquals(1, count(scandir(vfsStream::url("root/custom-base-dir/default/inprocess"))),
                        "The inprocess directory should hold the job file.");

    $this->assertEquals(1, count(scandir(vfsStream::url("root/custom-base-dir/default"))),
                        "The default directory should only have the inprocess directory.");
  }

  public function test_releasing_a_job_moves_it_back_to_main_folder(){
    \Queue::push(function($job){
      $job->release();
    }, array());

    $this->assertEquals(1, $this->fileCountInVfsDirectory("root/custom-base-dir/default"));
    $job = \Queue::pop();
    $this->assertEquals(0, $this->fileCountInVfsDirectory("root/custom-base-dir/default"));

    $job->fire();
    $this->assertEquals(1, $this->fileCountInVfsDirectory("root/custom-base-dir/default"));
  }

  public function test_deleting_a_job_removes_the_file(){
    \Queue::push(function($job){
      $job->delete();
    }, array());
    $job = \Queue::pop();
    $this->assertEquals(1, $this->fileCountInVfsDirectory("root/custom-base-dir/default/inprocess"));
    $job->fire();
    $this->assertEquals(0, $this->fileCountInVfsDirectory("root/custom-base-dir/default/inprocess"),
                        "After firing the job delete is called and it should be removed.");
  }

  public function test_pop_only_takes_one_job(){
    \Queue::push(function(){}, array());
    \Queue::push(function(){}, array());
    $this->assertEquals(2, $this->fileCountInVfsDirectory("root/custom-base-dir/default"));

    \Queue::pop();
    $this->assertEquals(1, $this->fileCountInVfsDirectory("root/custom-base-dir/default"));

    \Queue::pop();
    $this->assertEquals(0, $this->fileCountInVfsDirectory("root/custom-base-dir/default"));    
  }

  public function test_releasing_with_delay_udpates_filename(){
    // Arrange
    \Queue::push(function($job){ $job->release(100); },array());

    // Act
    $job = \Queue::pop();
    
    $first = $this->firstFile("root/custom-base-dir/default/inprocess");
    preg_match($this->_jobNameRegex, $first, $m);
    $first_timestamp = (float)$m[2];

    $job->fire();
    
    $second = $this->firstFile("root/custom-base-dir/default/");
    preg_match($this->_jobNameRegex, $second, $m);    
    $second_timestamp = (float)$m[2];

    // Assert
    $this->assertGreaterThan(100, $second_timestamp - $first_timestamp);
  }

  public function test_releasing_without_delay_updates_filename(){
    // Arrange
    \Queue::push(function($job){ $job->release(); },array());    
    
    // Act
    $job = \Queue::pop();
    
    $first = $this->firstFile("root/custom-base-dir/default/inprocess");
    preg_match($this->_jobNameRegex, $first, $m);
    $first_timestamp = (float)$m[2];

    $job->fire();

    $second = $this->firstFile("root/custom-base-dir/default/");
    preg_match($this->_jobNameRegex, $second, $m);    
    $second_timestamp = (float)$m[2];

    // Assert
    $this->assertNotSame($first_timestamp, $second_timestamp);
  }

  public function test_attempts_are_updated(){
    \Queue::push(function($job){}, array());

    $job = \Queue::pop();
    $this->assertEquals(0, $job->attempts());

    $job->fire();
    $this->assertEquals(1, $job->attempts());

    $job->fire();
    $this->assertEquals(2, $job->attempts());    
  }


  public function test_attempts_are_preserved(){
    \Queue::push(function($job){ $job->release(); }, array());
    $job = \Queue::pop();

    $job->fire();
    $this->assertEquals(1, $job->attempts(), "Job should have 1 attempt after firing.");

    $job = \Queue::pop();
    $this->assertEquals(1, $job->attempts(), "Job should retain the 1 attempt after firing.");
  }

  public function test_bubble_exceptions(){
    $this->app['queue']->setBubbleExceptions(false);
    $exceptionThrown = false;
    \Queue::push(function($job){
      throw new \Exception("Hello World");
    });

    $job = \Queue::pop();
    try {
      $job->fire();
    } catch(\Exception $e) {
      $exceptionThrown = true;
    }
    $this->assertFalse($exceptionThrown);
  }

  public function test_bubble_exceptions_enabled() {
    $this->app['queue']->setBubbleExceptions(true);
    $exceptionThrown = false;
    \Queue::push(function($job){
      throw new \Exception("My Exception");
    });
    $job = \Queue::pop();
    try {
      $job->fire();
    } catch(Exception $e) {
      $exceptionThrown = true;
    }
    $this->assertTrue($exceptionThrown);
    $this->assertSame($job->getBubbleExceptions(), $this->app['queue']->getBubbleExceptions());
  }

  private function fileCountInVfsDirectory($dir) {
    $all = scandir(vfsStream::url($dir));
    $count = 0;
    foreach($all as $one) {
      $realPath = vfsStream::url($dir . "/" . $one);
      if(is_file($realPath) && !is_dir($realPath)) {
        $count++;
      }
    }
    return $count;
  }

  private function firstFile($dir) {
    $dir = vfsStream::url($dir);
    $all = scandir($dir);
    foreach($all as $item) {
      if(is_file($dir . "/" . $item)) return $item;
    }
    return null;
  }
}