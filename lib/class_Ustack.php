<?php

  /*
    "Unique-Stack class"
    Should probably be rather called unique-stash; technically not a stack.
    A stash that accepts any number of values, but will store each of them only once.
  */
  
  class ustack {
  
    private $e;
    
    function __construct(){
      $this->e=array();
    }
    
    //Add one or several elements ($e can be array or just a string)
    function add($e){
      if (is_array($e)){
        $this->add_array($e);
      } else {
        $this->add_single_value($e);
      }
    }
      
    function add_array($e){
      foreach ($e as $v){
        $this->add_single_value($v);
      }      
    }
    
    function add_single_value($v){
        if (!in_array($v,$this->e)){
          //Value not yet extant: add
          if (($v!="") || ($v>0)){
            $this->e[]=$v;
          }
        }
    }
    
    function retrieve_array(){
      return $this->e;
    }
    
    function retrieve_string($delim=','){
      $t="";
      foreach ($this->e as $v){
        $t.=$v.",";
      }
      //Take away last comma
      return substr($t,0,-1);
    }
  }
?>