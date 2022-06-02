<?php

class cw_Scripture_handling {
  
    private $d; //Database access
    
    public $scripture_refs,$sermons;

    function __construct($d){
      $this->d = $d;
      
      $this->scripture_refs = new cw_Scripture_refs($d);
      $this->sermons = new cw_Sermons($this->d);
      
    }
    
    //Delete tables (if extant) and re-create. Add default records.
    function recreate_tables($default_records=true){
      return (($this->scripture_refs->recreate_tables($default_records)) && ($this->sermons->recreate_tables($default_records)));
    }
    
    /*
      Take an array of scripture reference of the form "start"=>$start,"end"=>$end,
      add them all to the scripture_refs table, and assign them all to the service element
    */  
    function assign_scripture_refs_to_service_element($refs=array(),$service_element_id){
      if ($service_element_id>0){
        foreach ($refs as $v){
          //$v has array with fields $start and $end
          $ref_id=$this->scripture_refs->add_scripture_ref($v["start"],$v["end"]);        
          $this->scripture_refs->assign_scripture_ref_to_service_element($ref_id,$service_element_id);
        }      
        return true;
      }
      return false;    
    }
    
    /*
      Take an array of scripture reference of the form "start"=>$start,"end"=>$end,
      add them all to the scripture_refs table, and assign them all to the music piece
    */  
    function assign_scripture_refs_to_music_piece($refs=array(),$music_piece_id){
      if ($music_piece_id>0){
        $error=false;
        foreach ($refs as $v){
          //$v has array with fields $start and $end
          $ref_id=$this->scripture_refs->add_scripture_ref($v["start"],$v["end"]);
          if ($ref_id!==false){
            if (!$this->scripture_refs->assign_scripture_ref_to_music_piece($ref_id,$music_piece_id)){
              //Couldn't assign the reference
              $error=true;
            }
          } else {
            //Couldn't add the reference
            $error=true;
          }        
        }
        return (!$error);
      }
      return false;    
    }  
      
    function unassign_all_scripture_refs_from_music_piece($music_piece_id){
      $result=false;
      $r=$this->scripture_refs->get_scripture_ref_records_for_music_piece($music_piece_id);
      if (is_array($r)){
        $result=true;
        foreach ($r as $v){
          $result=(($this->scripture_refs->unassign_scripture_ref_from_music_piece($v["id"])) && ($result));
        }        
      }
      return $result;
    }
      
}

?>