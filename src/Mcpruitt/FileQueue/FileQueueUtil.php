<?php
namespace Mcpruitt\FileQueue;
class FileQueueUtil {

  public static function joinPaths(){
    $badChars = array("\\","*","?","\"","<",">","|");
    $args = func_get_args();
    $paths = array();
    foreach ($args as $argIndex => $arg) {
        $arg = str_replace("\\", "/", $arg);
        foreach($badChars as $char) {
          $arg = str_replace($char, "", $arg);
        }

        $paths = array_merge($paths, (array)$arg);
    }

    $paths = array_map(create_function('$p', 'return $p == "/" ? $p : rtrim($p, "/");'), $paths);
    $paths = array_filter($paths);

    $joined = join('/', $paths);
    while(strpos($joined,"//") !== false) {
      $joined = str_replace("//", "/", $joined);
    }

    return trim($joined) . "/";
  }
  
  public static function getArrayValue($array, $key, $default = null) {
    if($array == null || $key == null) return $default;    
    return isset($array[$key]) ? $array[$key] : $default;
  }

}