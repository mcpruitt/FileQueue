<?php namespace Mcpruitt\FileQueue\Jobs;

use \Closure;
use \Mcpruitt\FileQueue\FileQueueUtil as U;
use \Illuminate\Container\Container;
use \Illuminate\Queue\Jobs\Job;

class FileQueueJob extends Job {

  protected $job_name;

  protected $job_data;

  protected $queue_name;

  protected $due_date;

  protected $job_attempts = 0;

  protected $storage_path;

  public function __construct(Container $c, $jobQueue, $jobName, $jobData, $dueDate, $storagePath = null, $attempts = 0) {
    $this->container    = $c;
    $this->job_name     = $jobName;
    $this->job_data     = $jobData;
    $this->queue_name   = $jobQueue;
    $this->due_date     = $dueDate;
    $this->storage_path = $storagePath;
    $this->job_attempts = $attempts;
  }

  public function fire() {
    $this->job_attempts++;

    if ($this->job_name instanceof Closure) {
      call_user_func($this->job_name, $this, $this->job_data);
    } else {
      $payload = array(
        'job'  => $this->job_name, 
        'data' => (array)$this->job_data
      );
      $this->resolveAndFire($payload);
    }
  }

  /**
   * Delete the job from the queue.
   * 
   * @return void
   */
  public function delete(){
    $id = U::getJobFilename($this->job_name, $this->due_date, $this->attempts() - 1);
    $inProcessPath = $this->storage_path;
    $currentPath = rtrim(U::joinPaths($inProcessPath, $id),'/');
    \File::delete($currentPath);
  }

  /**
   * Release the job back into the queue.
   *
   * @param  int   $delay The delay in seconds.
   * @return void
   */
  public function release($delay = 0){

    $startingId = U::getJobFilename($this->job_name, $this->due_date, $this->attempts() - 1);

    $inProcessPath = $this->storage_path;
    $currentPath = rtrim(U::joinPaths($inProcessPath, $startingId),'/');

    $regularPath = substr($inProcessPath, 0, strlen($inProcessPath) - strlen("inprocess/"));

    

    $this->due_date = microtime(true) + $delay;
    $id = U::getJobFilename($this->job_name, $this->due_date, $this->attempts());
    $outputPath = $regularPath . $id . ".json";

    \File::move($currentPath, $outputPath);
  }

  /**
   * Get the number of times the job has been attempted.
   *
   * @return int
   */
  public function attempts() { return $this->job_attempts; }

  public function getDue() { return $this->due_date; }
}