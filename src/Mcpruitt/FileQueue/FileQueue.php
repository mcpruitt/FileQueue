<?php namespace Mcpruitt\FileQueue;

use \Mcpruitt\FileQueue\FileQueueUtil as U;
use \Mcpruitt\FileQueue\Jobs\FileQueueJob;

class FileQueue extends \Illuminate\Queue\Queue implements \Illuminate\Queue\QueueInterface {

  protected $_config = array();

  protected $_defaultQueueName;

  protected $_baseDirectory;

  protected $_bubbleExceptions;

  protected $_jobNameRegex = "/job\-(?<jobname>.*)\-(?<jobdue>[0-9]+\.[0-9]+)\-(?<jobattempts>[0-9]*)\.json/";

  /**
   * Create a new file queue with an optional configuration.
   * @param array $config The configuration
   */
  public function __construct($config = array()) {
    $this->_config = $config;
    $this->setDefaultQueueName(U::getArrayValue($config, "defaultqueue", "default"));
    $this->setBaseDirectory(U::getArrayValue($config,"directory",U::joinPaths(storage_path(),"FileQueue")));
    $this->setBubbleExceptions(U::getArrayValue($config,"bubbleexceptions", true));
  }

  /**
   * Set the default queue name.
   * @param string $name The default queue name
   */
  public function setDefaultQueueName($name) {
    $this->_defaultQueueName = trim(U::joinPaths($name), '/'); 
  }  

  /**
   * Get the default queue name.
   * @return string The default queue name.
   */
  public function getDefaultQueueName(){ 
    return $this->_defaultQueueName; 
  }

  /**
   * Set the base path for the queue.
   * @param string $dir The base path for the queue.
   */
  public function setBaseDirectory($dir) { 
    $this->_baseDirectory = U::joinPaths($dir); 
  }

  /**
   * Get the base path for the queue.
   * @return string The base path for the queue.
   */
  public function getBaseDirectory(){ 
    return $this->_baseDirectory; 
  }


  public function setBubbleExceptions($val ){
    $this->_bubbleExceptions = $val;
  }

  public function getBubbleExceptions(){ 
    return $this->_bubbleExceptions; 
  }


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
    // Get the queue name
    $queue = $queue === null ? "default" : trim($queue);

    // Calculate when the job is due
    $jobDueAfter = microtime(true) + $this->getSeconds($delay);

    // Create the payload
    $payload = $this->createPayload($job, $data);


    $jobFilename = U::getJobFilename($job, $jobDueAfter);

    // Get the filename
    $filename = rtrim(U::joinPaths($this->_getQueueDirectory($queue, true),  "{$jobFilename}"),'/');

    // Save the job
    \File::put($filename, $payload);

    return 0;
  }

  /**
   * Pop the next job off of the queue.
   *
   * @param  string  $queue
   * @return \Illuminate\Queue\Jobs\Job|null
   */
  public function pop($queue = null) {
    $queue = $queue === null ? "default":$queue;
    $currentmicrotime = microtime(true);
    $allfiles = scandir($this->_getQueueDirectory($queue,true));
    foreach($allfiles as $index => $file) {
      if(strlen($file) < 5 || substr($file, -5) !== ".json") {
        unset($allfiles[$index]);
      }
    }
    

    foreach($allfiles as $file) {
      preg_match($this->_jobNameRegex, $file, $matches);
      $due = (float)$matches['jobdue'];
      $attempts = (int)$matches['jobattempts'];

      if($due > $currentmicrotime) {
        continue;
      }

      $fullJobPath = trim(U::joinPaths($this->_getQueueDirectory($queue), $file),'/');
      $queueItem = json_decode(file_get_contents($fullJobPath));

      $job = $queueItem->job;
      $data = $queueItem->data;
        
      $processingDirectory = U::joinPaths($this->_getQueueDirectory($queue), "inprocess");
      if(!\File::isDirectory($processingDirectory)) {
        \File::makeDirectory($processingDirectory,0777, true);
      }

      $inprocessFile = rtrim(U::joinPaths($processingDirectory, $file),'/');

      \File::move($fullJobPath, $inprocessFile);
      $job = new FileQueueJob($this->container, $queue, $job, $data, $due, $processingDirectory, $attempts);
      $job->setBubbleExceptions($this->_bubbleExceptions);
      return $job;    
    }
  }

  protected function _getQueueDirectory($queue = null, $createIfMissing = false) {
    $queue = $queue === null ? "default" : trim($queue);
    $path = U::joinPaths($this->getBaseDirectory(), $queue);
    if($createIfMissing && !\File::isDirectory($path)) {
      \File::makeDirectory($path, 0770, true);
    }
    return $path;
  }
}