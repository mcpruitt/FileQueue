<?php
namespace Mcpruitt\FileQueue;

class FileQueueUtil
{
    /**
     * Join multiple parts into a single file path.
     * @example joinPaths("dira","dirb","dirc");
     * @return string THe path
     */
    public static function joinPaths()
    {
        $badChars = array("\\","*","?","\"","<",">","|");
        $args = func_get_args();
        $paths = array();
        foreach ($args as $argIndex => $arg) {
            $arg = str_replace("\\", "/", $arg);
            foreach ($badChars as $char) {
                $arg = str_replace($char, "", $arg);
            }
            $paths = array_merge($paths, (array) $arg);
        }

        $f = create_function('$p', 'return $p == "/" ? $p : rtrim($p, "/");');
        $paths = array_map($f, $paths);
        $paths = array_filter($paths);

        $joined = join('/', $paths);
        $prefix = "";

        if (preg_match("/[a-zA-Z0-9]+\:\/\//", $joined, $regexoutput)) {
            $prefix = substr($joined, 0, strlen($regexoutput[0]));
            $joined = substr($joined, strlen($regexoutput[0]));
        }

        while (strpos($joined, "//") !== false) {
            $joined = str_replace("//", "/", $joined);
        }

        return $prefix . trim($joined) . "/";
    }

    /**
     * Get a value out of an array or return the default if it is missing.
     * @param  array $array   The array
     * @param  string $key     The key
     * @param  mixed $default The value to return if the key is not found
     * @return mixed          The value of the key in the array
     */
    public static function getArrayValue($array, $key, $default = null)
    {
        if ($array == null || $key == null) {
            return $default;
        }
        return isset($array[$key]) ? $array[$key] : $default;
    }

    /**
     * Get the filename for a job.
     * @param  mixed  $job      The string or closure job.
     * @param  int  $date     The timestamp
     * @param  integer $attempts The number of attempts made at this job
     * @param  string  $suffix   The suffix for the file
     * @return string            The job name
     */
    public static function getJobFilename($job, $date, $attempts = 0,
                                          $suffix = ".json")
    {
        if ($job instanceof \Closure) {
            $job = "IlluminateQueueClosure";
        }
        $job = str_replace("\\", "-", $job);
        $job = trim($job, '-');

        return "job-{$job}-{$date}-{$attempts}{$suffix}";
    }
}