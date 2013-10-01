<?php namespace Mcpruitt\FileQueue;

use \Mcpruitt\FileQueue\FileQueueUtil as U;
use \Mcpruitt\FileQueue\Jobs\FileQueueJob;

class FileQueue extends \Illuminate\Queue\Queue
                implements \Illuminate\Queue\QueueInterface {

    protected $_config = array();

    protected $_defaultQueueName;

    protected $_baseDirectory;

    protected $_bubbleExceptions;

    protected $_jobNameRegex
      = "/job\-(?<jobname>.*)\-(?<due>[0-9]+\.[0-9]+)\-(?<tries>[0-9]*)\.json/";

    /**
     * Create a new file queue with an optional configuration.
     * @param array $config The configuration
     */
    public function __construct($config = array())
    {
        $this->_config = $config;
        $this->setDefaultQueueName(
            U::getArrayValue($config, "defaultqueue", "default")
        );
        $this->setBaseDirectory(
            U::getArrayValue($config, "directory",
                             U::joinPaths(storage_path(), "FileQueue"))
        );
        $this->setBubbleExceptions(
            U::getArrayValue($config, "bubbleexceptions", true)
        );
    }

    /**
     * Set the default queue name.
     * @param string $name The default queue name
     */
    public function setDefaultQueueName($name)
    {
        $this->_defaultQueueName = trim(U::joinPaths($name), '/');
    }

    /**
     * Get the default queue name.
     * @return string The default queue name.
     */
    public function getDefaultQueueName()
    {
        return $this->_defaultQueueName;
    }

    /**
     * Set the base path for the queue.
     * @param string $dir The base path for the queue.
     */
    public function setBaseDirectory($dir)
    {
        $this->_baseDirectory = U::joinPaths($dir);
    }

    /**
     * Get the base path for the queue.
     * @return string The base path for the queue.
     */
    public function getBaseDirectory()
    {
        return $this->_baseDirectory;
    }


    public function setBubbleExceptions($val)
    {
        $this->_bubbleExceptions = $val;
    }

    public function getBubbleExceptions()
    {
        return $this->_bubbleExceptions;
    }


    /**
     * Push a new job onto the queue.
     *
     * @param    string    $job
     * @param    mixed     $data
     * @param    string    $queue
     * @return mixed
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->later(0, $job, $data, $queue);
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param    \DateTime|int    $delay
     * @param    string    $job
     * @param    mixed    $data
     * @param    string    $queue
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        // Get the queue name
        $queue = $queue === null ? "default" : trim($queue);

        // Calculate when the job is due
        $jobDueAfter = microtime(true) + $this->getSeconds($delay);

        // Create the payload
        $payload = $this->createPayload($job, $data);


        $jobFilename = U::getJobFilename($job, $jobDueAfter);

        // Get the filename
        $filename = U::joinPaths(
            $this->getQueueDirectory($queue, true), "{$jobFilename}"
        );

        $filename = rtrim($filename, '/');

        // Save the job
        \File::put($filename, $payload);

        return 0;
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param    string    $queue
     * @return \Illuminate\Queue\Jobs\Job|null
     */
    public function pop($queue = null)
    {
        $queue = $queue === null ? "default":$queue;
        $currentmicrotime = microtime(true);

        $allfiles = $this->getFilesForQueue($queue);

        foreach ($allfiles as $file) {
            preg_match($this->_jobNameRegex, $file, $matches);
            $due = (float) $matches['due'];
            $attempts = (int) $matches['tries'];

            if ($due > $currentmicrotime) {
                continue;
            }

            $queueItem = $this->decodeFileFromQueue($file, $queue);
            $this->moveFileToInProcessDirectory($file, $queue);
            $inprocessDir = $this->getInProcessQueueDirectory($queue);

            $job = new FileQueueJob($this->container, $queue, $queueItem->job,
                                    $queueItem->data, $due, $inprocessDir,
                                    $attempts);
            $job->setBubbleExceptions($this->_bubbleExceptions);
            return $job;
        }
        return null;
    }

    protected function getFilesForQueue($queue = null) {
        $allfiles = scandir($this->getQueueDirectory($queue, true));
        foreach ($allfiles as $index => $file) {
            if (strlen($file) < 5 || substr($file, -5) !== ".json") {
                unset($allfiles[$index]);
            }
        }
        return $allfiles;
    }

    protected function moveFileToInProcessDirectory($file, $queue = null) {
        $jobPath = $this->getFullPathToQueueJob($file, $queue);

        $inProcessDir = $this->getInProcessQueueDirectory($queue, true);
        $inprocessFile = trim(U::joinPaths($inProcessDir, $file));
        \File::move($jobPath, $inprocessFile);
    }

    protected function decodeFileFromQueue($file, $queue = null) {
        $fullJobPath = $this->getFullPathToQueueJob($file, $queue);
        $queueItem = json_decode(file_get_contents($fullJobPath));
        return $queueItem;
    }

    protected function getFullPathToQueueJob($file, $queue = null) {
        $path = U::joinPaths($this->getQueueDirectory($queue), $file);
        return trim($path, '/');
    }

    protected function getQueueDirectory($queue = null, $create = false)
    {
        $queue = $queue === null ? "default" : trim($queue);
        $path = U::joinPaths($this->getBaseDirectory(), $queue);
        if ($create && !\File::isDirectory($path)) {
            \File::makeDirectory($path, 0770, true);
        }
        return $path;
    }

    protected function getInProcessQueueDirectory($queue = null, $create = false) {
        $path = U::joinPaths($this->getQueueDirectory($queue), "inprocess");
        if ($create && !\File::isDirectory($path)) {
            \File::makeDirectory($path, 0770, true);
        }
        return $path;
    }
}