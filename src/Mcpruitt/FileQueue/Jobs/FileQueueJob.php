<?php namespace Mcpruitt\FileQueue\Jobs;

use \Closure;
use \Mcpruitt\FileQueue\FileQueueUtil as U;
use \Illuminate\Container\Container;
use \Illuminate\Queue\Jobs\Job;

class FileQueueJob extends Job
{

    protected $job_name;

    protected $job_data;

    protected $queue_name;

    protected $due_date;

    protected $job_attempts = 0;

    protected $storage_path;

    protected $bubble_exceptions = false;

    /**
     * Create a new job
     * @param Container $c           The application container
     * @param string    $jobQueue    The queue
     * @param string    $jobName     The Name
     * @param array     $jobData     The ata
     * @param integer   $dueDate     The due date
     * @param string    $storagePath The path to save the job
     * @param integer   $attempts    The number of attempts for the job
     */
    public function __construct(Container $c, $jobQueue, $jobName, $jobData,
                                $dueDate, $storagePath = null, $attempts = 0)
    {
        $this->container        = $c;
        $this->job_name         = $jobName;
        $this->job_data         = $jobData;
        $this->queue_name     = $jobQueue;
        $this->due_date         = $dueDate;
        $this->storage_path = $storagePath;
        $this->job_attempts = $attempts;
    }

    /**
     * Set the value indicating if this job should bubble exceptions.
     * @param boolean $val Should exceptions bubble
     */
    public function setBubbleExceptions($val )
    {
        $this->bubble_exceptions = $val;
    }

    /**
     * Should this job bubble exceptions.
     *
     * @return boolean True to bubble exceptions, false otherwise.
     */
    public function getBubbleExceptions()
    {
        return $this->bubble_exceptions;
    }

    /**
     * Fire this job. If exceptions are set to not bubble this will cause the
     * job to be relased with an increasing delay upon failure.
     *
     * @return void
     */
    public function fire()
    {
        $this->job_attempts++;
        try {
            if ($this->job_name instanceof Closure) {
                call_user_func($this->job_name, $this, $this->job_data);
            } else {
                $payload = array(
                    'job'    => $this->job_name,
                    'data' => (array) $this->job_data
                );
                $this->resolveAndFire($payload);
            }
        } catch (\Exception $e) {
            $this->release($this->job_attempts * $this->job_attempts);
            if ($this->bubble_exceptions) {
                throw $e;
            }
        }
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete()
    {
        $id = U::getJobFilename($this->job_name, $this->due_date,
                                $this->attempts());

        $previousId = U::getJobFilename($this->job_name, $this->due_date,
                                        $this->attempts() - 1);

        $inProcessPath = $this->storage_path;

        $currentPath = rtrim(U::joinPaths($inProcessPath, $id), '/');
        if (\File::isFile($currentPath)) {
            \File::delete($currentPath);
        }

        $currentPath = rtrim(U::joinPaths($inProcessPath, $previousId), '/');
        if (\File::isFile($currentPath)) {
            \File::delete($currentPath);
        }
    }

    /**
     * Release the job back into the queue.
     *
     * @param    int     $delay The delay in seconds.
     * @return void
     */
    public function release($delay = 0)
    {
        $startingId = U::getJobFilename($this->job_name,
                                        $this->due_date, $this->attempts() - 1);

        $inProcessPath = $this->storage_path;
        $currentPath = rtrim(U::joinPaths($inProcessPath, $startingId), '/');

        $offset = strlen($inProcessPath) - strlen("inprocess/");
        $regularPath = substr($inProcessPath, 0, $offset);

        $this->due_date = microtime(true) + $delay;
        $id = U::getJobFilename($this->job_name, $this->due_date,
                                $this->attempts());

        $outputPath = $regularPath . $id;

        \File::move($currentPath, $outputPath);
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        return $this->job_attempts;
    }

    /**
     * Get the due date for this job.
     *
     * @return int The due date as a unix timestamp
     */
    public function getDue()
    {
        return $this->due_date;
    }
    
	public function getRawBody()
	{
		//
	}    
}
