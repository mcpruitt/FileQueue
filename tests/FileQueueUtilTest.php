<?php
use \Mockery as m;

use \Orchestra\Testbench\TestCase;
use \Mcpruitt\FileQueue\FileQueueUtil as U;

class FileQueueUtilTest extends TestCase {
  public function tearDown(){ m::close(); }

  public function test_simple_path_join(){
    $this->assertSame("c:/temp/", U::joinPaths("c:\\","temp"));
  }

  public function test_relative_path_join(){
    $this->assertSame("test/dir/",U::joinPaths("test/","dir"));
  }
  
  public function test_double_slashes_fixes(){
    $this->assertSame("test/dir/",U::joinPaths("test//dir"));
  }

  public function test_it_adds_trailing_slash(){
    $this->assertSame("test/dir/", U::joinPaths("test/dir"));
  }

  public function test_it_removes_invalid_characters(){
    $invalid_path_characters = array("\\","*","?","\"","<",">","|");
    foreach($invalid_path_characters as $char) {
      $this->assertSame("test/dir/",U::joinPaths("test{$char}","{$char}dir"));
    }
  }

  public function test_it_works_with_leading_slash(){
    $this->assertSame("/var/root/",U::joinPaths("/var","root"));
  }

  public function test_a_leading_slash_can_work_alone(){
    $this->assertSame("/var/root/",U::joinPaths("/","var","root"));
  }

  public function test_getting_non_existant_array_value(){
    $this->assertSame(null,u::getArrayValue(array(),"test",null));
  }

  public function test_get_array_value_default(){
    $this->assertSame("default",u::getArrayValue(array(),"test","default"));
  }

  public function test_get_array_value_with_existant_value(){
    $this->assertSame(5,u::getArrayValue(array("n"=>5),"n"));
  }
}