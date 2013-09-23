<?php namespace Mcpruitt\FileQueue\Jobs;

use \Closure;
use \Mcpruitt\FileQueue\FileQueueUtil;
use \Illuminate\Container\Container;
use \Illuminate\Queue\Jobs\Job;

class FileQueueJob extends Job {

  /**
   * The class name of the job.
   * @var string
   */
  public $job;

  /**
   * The queue message data.
   * @var string
   */
  public $data;

  /**
   * The queue this job belongs to.
   * @var string
   */
  public $queue;

  /**
   * The microtime representation of when this job can be completed.
   * @var float
   */
  public $due;

  /**
   * The number of attempts.
   * @var integer
   */
  public $attempts = 0;

  /**
   * Create a new job instance.
   *
   * @param  \Illuminate\Container  $container
   * @param  string  $job
   * @param  string  $data
   * @return void
   */
  public function __construct(Container $container, $job, $data, $due, $queue = null) {
    $this->job = $job;
    $this->data = $data;
    $this->queue = $queue === null ? "default" : trim($queue);
    $this->due = $due;
    $this->container = $container;
  }

  /**
   * Fire the job.
   * 
   * @return void
   */
  public function fire() {
    $this->attempts++;
    $this->job instanceof Closure ? 
      call_user_func($this->job, $this, $this->data) 
      : $this->resolveAndFire(array('job' => $this->job, 'data' => $this->data));    
  }

  /**
   * Delete the job from the queue.
   * 
   * @return void
   */
  public function delete(){
    $id = $this->getJobId();
    $path = FileQueueUtil::joinPaths(storage_path(), "FileQueue", $this->queue, "inprocess","{$id}.json");
    if(\File::exists($path)) \File::delete($path);
  }

  /**
   * Release the job back into the queue.
   *
   * @param  int   $delay The delay in seconds.
   * @return void
   */
  public function release($delay = 0){
    $id = $this->getJobId();
    $inProcessPath = FileQueueUtil::joinPaths(storage_path(), "FileQueue", $this->queue, "inprocess","{$id}.json");    
    
    $this->due = microtime(true) + $delay;
    $id = $this->getJobId();
    $contents = json_encode($this);

    $regularPath = FileQueueUtil::joinPaths(storage_path(), "FileQueue", $this->queue,"{$id}.json");

    \File::put($regularPath, $contents);
    \File::delete($inProcessPath);
  }

  /**
   * Get the number of times the job has been attempted.
   *
   * @return int
   */
  public function attempts()
  {
    return $this->attempts;
  }

  /**
   * Get the job identifier.
   *
   * @return string
   */
  public function getJobId() {

    $jobtype = $this->job;    
    // Swap namespace characters to dashes to make the filesystem happy
    if($jobtype instanceof Closure) $jobtype = "anonymous";
    $jobtype = str_replace("\\", "-", $jobtype);
    $jobtype = trim($jobtype, '-');
    return "job-{$jobtype}-{$this->due}";
  }
}