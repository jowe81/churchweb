<?php

class cw_Help {
  
    private $d; //Database access
    
    function __construct($d){
      $this->d = $d;
    }
    
    function check_for_table(){
      return $this->d->table_exists("help");
    }
    
    function create_table(){
      return $this->d->q("CREATE TABLE help (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          note TEXT
                        )");
    }

    //Delete table (if extant) and re-create. Add root/default records.
    function recreate_tables($default_records=true){
      if ($this->check_for_table()){
        $this->d->drop_table("help");
      }
      $res=$this->create_table();
      if ($res && $default_records){
        $res=$this->add_default_records();
      }
      return $res;
    }
    
    private function add_default_records(){
      $res=true;
      foreach (scandir(CW_ROOT_UNIX.CW_HELP_DIR) as $item) {
          if ($item == '.' || $item == '..') continue;
          if (!$this->add_note_from_file($item)){
            $res=false;
            break;
          }
      }    
      return $res;
    }
        
    function add_note_from_file($filename){
      if ($note=file_get_contents(CW_ROOT_UNIX.CW_HELP_DIR.$filename)){
        return $this->add_note($note);
      }
      return false;        
    }
        
    function add_note($note){
      $e=array();
      $e["note"]=$note;
      return $this->d->insert($e,"help");
    }    
    
    function delete_note($id){
      return $this->d->delete($id,"help","id");
    }
    
    function get_note($id){
      if ($r=$this->d->get_record("help","id",$id)){
        return $r["note"];
      }
      return false;
    }
        
}

?>