<?php namespace Mcpruitt\FileQueue;

use \Mcpruitt\FileQueue\Jobs\FileQueueJob;

class FileQueue extends \Illuminate\Queue\Queue implements \Illuminate\Queue\QueueInterface {

  public function __construct() {
    $this->_setupQueueDirectory();
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
    $queue = $queue === null ? "default" : trim($queue);
    $jobDueAfter = microtime(true) + $delay;

    $job =  new FileQueueJob($this->container, $job, $data, $jobDueAfter, $queue); 
    
    $filename = $this->joinPaths($this->_getQueueDirectory($queue), "{$job->getJobId()}.json");
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
        $fullJobPath = $this->joinPaths($this->_getQueueDirectory($queue), $file);

        $queueItem = json_decode(file_get_contents($fullJobPath));        
        $job = $queueItem->job;
        $data = $queueItem->data;
        
        $processingDirectory = $this->joinPaths($this->_getQueueDirectory($queue), "inprocess");
        if(!\File::isDirectory($processingDirectory)) \File::mkdir($processingDirectory);
        

        $inprocessFile = $this->joinPaths($processingDirectory, $file);
        
        \File::move($fullJobPath, $inprocessFile);
        $job = new FileQueueJob($this->container, $queueItem->job, 
                                     $queueItem->data, $queueItem->due,
                                     $queueItem->queue);
        
        $job->tries = $queueItem->tries;
        return $job;
      }
    }
  }

  /**
   * Setup the base queue directory and default queue folder.
   */
  protected function _setupQueueDirectory() {
    $baseDirectory = $this->joinPaths(storage_path(), "FileQueue");
    if(!\File::isDirectory($baseDirectory)) \File::makeDirectory($baseDirectory);    
    $this->_createSpecificQueueDirectory("default");
  }

  /**
   * Create a folder for a specific queue.
   * @param  string $queue The folder to create
   */
  protected function _createSpecificQueueDirectory($queue) {
    $queueDirectory = $this->joinPaths(storage_path(), "FileQueue", $queue);
    if(!\File::isDirectory($queueDirectory)) \File::makeDirectory($queueDirectory);
  }

  protected function _getQueueDirectory($queue = null) {
    $queue = $queue === null ? "default" : trim($queue);
    return $this->joinPaths(storage_path(), "FileQueue", $queue);
  }

  protected function _getFilenameForQueueItem($queueitem, $queue, $due) {
    $jobtype = $queueitem->job;
    $jobtype = str_replace("\\", "-", $jobtype);
    $filename = "job-{$jobtype}-{$due}.json";
    return $this->joinPaths(storage_path(), "FileQueue", $queue, $filename);
  }

  /**
   * Join a set of paths.
   * @example joinPaths("c:/","temp","somefolder");
   */
  protected function joinPaths() {
    $args = func_get_args();
    $paths = array();
    foreach ($args as $arg) {
        $arg = str_replace("\\", "/", $arg);
        $paths = array_merge($paths, (array)$arg);
    }

    $paths = array_map(create_function('$p', 'return trim($p, "/");'), $paths);
    $paths = array_filter($paths);
    return join('/', $paths);
  }
}