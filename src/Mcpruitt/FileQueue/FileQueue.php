<?php namespace Mcpruitt\FileQueue;

use \Mcpruitt\FileQueue\FileQueueUtil as U;
use \Mcpruitt\FileQueue\Jobs\FileQueueJob;

class FileQueue extends \Illuminate\Queue\Queue implements \Illuminate\Queue\QueueInterface {

  protected $_config = array();

  protected $_defaultQueueName;

  protected $_baseDirectory;

  /**
   * Create a new file queue with an optional configuration.
   * @param array $config The configuration
   */
  public function __construct($config = array()) {
    $this->_config = $config;
    $this->setDefaultQueueName(U::getArrayValue($config, "defaultqueue", "default"));
    $this->setBaseDirectory(U::getArrayValue($config,"directory",U::joinPaths(storage_path(),"FileQueue")));
  }

  public function setDefaultQueueName($name) {
    $this->_defaultQueueName = trim(U::joinPaths($name), '/'); 
  }  

  public function getDefaultQueueName(){ return $this->_defaultQueueName; }

  public function setBaseDirectory($dir) { $this->_baseDirectory = U::joinPaths($dir); }

  public function getBaseDirectory(){ return $this->_baseDirectory; }

  /**
   * Push a new job onto the queue.
   *
   * @param  string  $job
   * @param  mixed   $data
   * @param  string  $queue
   * @return mixed
   */
  public function push($job, $data = '', $queue = null) {
    return $this->later(0, $job, $data, $queue);
  }

  /**
   * Push a new job onto the queue after a delay.
   *
   * @param  \DateTime|int  $delay
   * @param  string  $job
   * @param  mixed  $data
   * @param  string  $queue
   * @return mixed
   */
  public function later($delay, $job, $data = '', $queue = null) {
    $queue = $queue === null ? "default" : trim($queue);
    $jobDueAfter = microtime(true) + $delay;

    $job =  new FileQueueJob($this->container, $job, $data, $jobDueAfter, $queue); 
    
    $filename = U::joinPaths($this->_getQueueDirectory($queue), "{$job->getJobId()}.json");
    $contents = json_encode($job);    
    \File::put($filename, $contents);
    return 0;
  }

  /**
   * Pop the next job off of the queue.
   *
   * @param  string  $queue
   * @return \Illuminate\Queue\Jobs\Job|null
   */
  public function pop($queue = null) {
    $currentmicrotime = microtime(true);

    $allfiles = scandir($this->_getQueueDirectory($queue));
    foreach($allfiles as $index => $file) {
      if(strlen($file) < 5 || substr($file, -5) !== ".json") unset($allfiles[$index]);
    }
    
    foreach($allfiles as $file) {
      $ex = explode("-", $file);
      $last = (float)$ex[count($ex)-1];
      if($last <= $currentmicrotime) {        
        $fullJobPath = U::joinPaths($this->_getQueueDirectory($queue), $file);

        $queueItem = json_decode(file_get_contents($fullJobPath));        
        $job = $queueItem->job;
        $data = $queueItem->data;
        
        $processingDirectory = U::joinPaths($this->_getQueueDirectory($queue), "inprocess");
        if(!\File::isDirectory($processingDirectory)) \File::mkdir($processingDirectory);
        

        $inprocessFile = U::joinPaths($processingDirectory, $file);
        
        \File::move($fullJobPath, $inprocessFile);
        $job = new FileQueueJob($this->container, $queueItem->job, 
                                     $queueItem->data, $queueItem->due,
                                     $queueItem->queue);
        
        $job->tries = $queueItem->tries;
        return $job;
      }
    }
  }

  public function getStoragePath($queue = null){

  }


  protected function _getQueueName($name = null) { 
    return $name == null ? $this->getDefaultQueueName() : U::joinPaths($name);
  }

  /**
   * Setup the base queue directory and default queue folder.
   */
  protected function _setupQueueDirectory() {
    $baseDirectory = U::joinPaths(storage_path(), "FileQueue");
    if(!\File::isDirectory($baseDirectory)) \File::makeDirectory($baseDirectory);    
    $this->_createSpecificQueueDirectory("default");
  }

  /**
   * Create a folder for a specific queue.
   * @param  string $queue The folder to create
   */
  protected function _createSpecificQueueDirectory($queue) {
    $queueDirectory = U::joinPaths(storage_path(), "FileQueue", $queue);
    if(!\File::isDirectory($queueDirectory)) \File::makeDirectory($queueDirectory);
  }

  protected function _getQueueDirectory($queue = null) {
    $queue = $queue === null ? "default" : trim($queue);
    return U::joinPaths(storage_path(), "FileQueue", $queue);
  }

  protected function _getFilenameForQueueItem($queueitem, $queue, $due) {
    $jobtype = $queueitem->job;
    $jobtype = str_replace("\\", "-", $jobtype);
    $filename = "job-{$jobtype}-{$due}.json";
    return U::joinPaths(storage_path(), "FileQueue", $queue, $filename);
  }


}