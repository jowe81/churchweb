<?php

class cw_Conversations {
  
    public $d,$auth; //Passed in
    
    function __construct($auth){
      $this->auth = $auth;
      $this->d = $this->auth->d;
    }
    
    function check_for_table($table="conversations"){
      return $this->d->table_exists($table);
    }
    
    function create_tables(){
      return (($this->d->q("CREATE TABLE conversation_contributions (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          conversation_id INT,
                          note_no INT,
                          contribution_no INT,
                          person_id INT,
                          timestamp INT UNSIGNED,
                          content text
                        )"))
                &&        
             ($this->d->q("CREATE TABLE conversations (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          person_id INT,
                          latest_contribution INT UNSIGNED,
                          full_update_after INT UNSIGNED,
                          number_of_notes INT,
                          title varchar(50)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE conversation_sync_marks (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          conversation_id INT,
                          session_id varchar(32),
                          timestamp INT UNSIGNED
                        )")));
                        
      /*
        conversations
          - latest_contribution: timestamp of when the last contribution got added
          - full_update_after: timestamp after which the conversation should be reloaded (because something was deleted, or a note was added)
      */
    }

    function check_and_drop_tables($tables=array()){
      foreach ($tables as $table){
        if ($this->check_for_table($table)){
          $this->d->drop_table($table);
        }            
      }
    }

    //Delete tables (if extant) and re-create. Add default records.
    function recreate_tables($default_records=true){
      $tables=array(
        "conversation_contributions",
        "conversations",
        "conversation_sync_marks"
      );
      $this->check_and_drop_tables($tables);      
      $res=$this->create_tables();
      if ($res && $default_records){
        //Add default records
      }
      return $res;
    }

    /* conversations */
    
    //Create conversation record and return id
    function add_conversation($person_id=0,$title=""){
      if ($person_id==0){
        $person_id=$this->auth->cuid; //use current user if no person_id given
      }
      $e=array();
      $e["person_id"]=$person_id;
      $e["title"]=$title;
      $e["number_of_notes"]=0;
      $e["full_update_after"]=time();
      return $this->d->insert_and_get_id($e,"conversations");
    }
    
    function drop_conversation($id){
      //To drop conversation, delete all contributions and the conversation record
      if ($this->drop_all_contributions($id)){
        return $this->delete_conversations_record($id);
      }
      return false;
    }

    private function touch_conversation($id,$field="full_update_after"){
      $e=array();
      $e[$field]=time();
      return $this->d->update_record("conversations","id",$id,$e);
    }
    
    private function delete_conversations_record($id){
      return $this->d->delete($id,"conversations");
    }    
    
    function get_conversations_record($id){
      return $this->d->get_record("conversations","id",$id);
    }
    
    private function register_new_note($id){
      if ($e=$this->get_conversations_record($id)){
        $e["number_of_notes"]++;
        return $this->d->update_record("conversations","id",$id,$e);      
      }
      return false;
    }

    private function unregister_note($id){
      if ($e=$this->get_conversations_record($id)){
        $e["number_of_notes"]--;
        return $this->d->update_record("conversations","id",$id,$e);      
      }
      return false;
    }
        
    /* conversation_contributions */
    
    function get_conversation_contributions_record($id){
      return $this->d->get_record("conversation_contributions","id",$id);    
    }
    
    function add_contribution($content,$conversation_id,$note_no=0,$person_id=0){
      /*                                                                    
        PSEUDO:
          if note_no not given
            find out how many notes the conversation has, and add the next one
          if note_no>0
            find out how many contributions the note has, and add the next one
      */      
      //Clear old sync marks
      $this->clear_old_sync_marks();
      if ($conv_rec=$this->get_conversations_record($conversation_id)){
        if ($note_no==0){
          //Note number was not given, so its a new note
          if ($this->register_new_note($conversation_id)){ //Save the new # of notes in the conversation record;
            $note_no=$conv_rec["number_of_notes"]+1;
            //Since it's a new note the contribution number must be 1
            $contribution_no=1;
            //Need to set "full_update_after" field
            $this->touch_conversation($conversation_id);
          } else {
            //Couldn't register new note
            return false;
          }
        } else {
          //Note number given - so find out how many contributions the note has and count one up for new note number
          $contribution_no=$this->get_number_of_contributions_for_note($conversation_id,$note_no)+1;
        }       
        if ($person_id==0){
          $person_id=$this->auth->cuid; //use current user if no person_id given
        }
        //Now we have valid $note_no, $contribution_no and $person_id for new contributions
        $e=array();
        $e["conversation_id"]=$conversation_id;
        $e["note_no"]=$note_no;
        $e["contribution_no"]=$contribution_no;
        $e["person_id"]=$person_id;
        $e["content"]=$content;
        $e["timestamp"]=time();
        $result=$this->d->insert_and_get_id($e,"conversation_contributions");
        $this->touch_conversation($conversation_id,"latest_contribution"); //Set the latest_contribution flag to the current timestamp
        return $result;
      }      
      return false;
    }
    
    function get_number_of_contributions_for_note($conversation_id,$note_no){
      $query="SELECT count(*) FROM conversation_contributions WHERE conversation_id=$conversation_id AND note_no=$note_no;";
      if ($res=$this->d->q($query)){
        if ($r=$res->fetch_row()){
          return $r[0];
        }
      }
      return false;
    }
    
    function drop_note($conversation_id,$note_no){
      /*
        To drop a note:
          -delete all it's contributions in conversation_contributions
          -for all contributions with a higher note_no do note_no--
          -reduce number_of_notes in conversations record
      */
      $query1="DELETE FROM conversation_contributions WHERE conversation_id=$conversation_id AND note_no=$note_no;";
      $query2="UPDATE conversation_contributions SET note_no=note_no-1 WHERE note_no>$note_no;";
      if ($this->d->q($query1) && $this->d->q($query2)){
        $this->touch_conversation($conversation_id);      
        return $this->unregister_note($conversation_id);
      }
      return false;
    }
    
    function drop_contribution($id){
      /*
        Dropping contributions goes by id
        -get record in question to obtain $conversation_id and $note_no
          if this is the first contribution for the note $note_no (i.e. the note itself)
              -call drop_note instead
           else
              -delete conversations_contribution record
              -for all following contributions with same conversation_id and note_no, do contribution_no--        
      */
      if ($r=$this->get_conversation_contributions_record($id)){
        $conversation_id=$r["conversation_id"];
        $note_no=$r["note_no"];
        if ($this->contribution_is_original_note($id)){
          //Drop entire note
          return $this->drop_note($conversation_id,$note_no);        
        } else {
          $query="UPDATE conversation_contributions SET contribution_no=contribution_no-1 WHERE conversation_id=$conversation_id AND note_no=$note_no;";
          if ($this->d->delete($id,"conversation_contributions")){
            $this->touch_conversation($conversation_id);
            return ($this->d->q($query));          
          }              
        }
      }
      return false;
    }
    
    private function drop_all_contributions($conversation_id){
      $query="DELETE FROM conversation_contributions WHERE conversation_id=$conversation_id";
      $this->touch_conversation($conversation_id);      
      return $this->d->q($query);      
    }
    
    //Return an array of IDs
    function get_contribution_ids_for_note($conversation_id,$note_no){
      $t=array();
      $query="SELECT id FROM conversation_contributions WHERE conversation_id=$conversation_id AND note_no=$note_no ORDER BY contribution_no";
      if ($res=$this->d->q($query)){
        while ($r=$res->fetch_row()){
          $t[]=$r[0];
        }
        return $t;
      }
      return false;    
    }
    
    //Is this contribution a response to a note, or the note itself?
    function contribution_is_original_note($contribution_id){
      if ($r=$this->get_conversation_contributions_record($contribution_id)){
        $t=$this->get_contribution_ids_for_note($r["conversation_id"],$r["note_no"]);
        if ($contribution_id==$t[0]){
          return true;
        }
      }    
    }
    
    function get_new_contributions($conversation_id,$timestamp){
      $query="SELECT * FROM conversation_contributions WHERE timestamp>$timestamp;";
      return $this->d->select_flat_json($query);
    }
    
    /* conversation_sync_marks */
    
    private function get_sync_mark_record($conversation_id){
      $session_id=$_SESSION["session_id"];
      $query="SELECT * FROM conversation_sync_marks WHERE conversation_id=$conversation_id AND session_id='$session_id';";
      if ($res=$this->d->q($query)){
        if ($r=$res->fetch_assoc()){
          return $r;
        }      
      }
      return false;
    }
    
    function set_sync_mark($conversation_id){
      if ($conversation_id>0){
        if ($e=$this->get_sync_mark_record($conversation_id)){
          //Sync mark exists - update
          $e["conversation_id"]=$conversation_id;
          $e["timestamp"]=time()-1; //-1 = safety buffer
          return $this->d->update_record("conversation_sync_marks","id",$e["id"],$e);        
        } else {
          //New sync mark
          $e=array();
          $e["session_id"]=$_SESSION["session_id"];
          $e["conversation_id"]=$conversation_id;
          $e["timestamp"]=time()-1; //-1 = safety buffer        
          return $this->d->insert($e,"conversation_sync_marks");              
        }
      }
      return false;
    }
    
    //Has the latest change to the conversation happened prior to the latest reload (the timestamp saved with the sync mark)?
    function check_sync_mark($conversation_id){
      if ($conv_rec=$this->get_conversations_record($conversation_id)){
        $session_id=$_SESSION["session_id"];
        if ($r=$this->get_sync_mark_record($conversation_id)){
          //$r["timestamp"] is the last reload, $conv_rec["latest_contribution"] the latest addition
          if (($r["timestamp"]>$conv_rec["latest_contribution"]) && ($r["timestamp"]>$conv_rec["full_update_after"])){
            //There has been no added contributions since the last reload, nor has anything been deleted. In other words: no change, sync is ok.
            return true;
          } else {
            //There has been either an update since the last reload, or something got deleted
            if ($r["timestamp"]<=$conv_rec["full_update_after"]){
              //Need to request a full update, return false
              return false;              
            } else {
              //Need only to request additions after $r["timestamp"]; return the timestamp
              return $r["timestamp"];
            } 
          }
        }      
      }
      return false; //false indicates need for full update    
    }
    
    /* Sync marks older than a day are considered expired */
    function clear_old_sync_marks(){
      $query="DELETE FROM conversation_sync_marks WHERE timestamp<".(time()-DAY);
      return ($this->d->q($query));
    }

    //Return ids of new contributions if there are any
    function check_for_new_contributions($latest_loaded_contribution_sql_id){
      if ($r=$this->get_conversation_contributions_record($latest_loaded_contribution_sql_id)){
        $t=array();
        $query="SELECT id FROM conversation_contributions WHERE conversation_id=".$r["conversation_id"]." AND note_no=".$r["note_no"]." AND contribution_no>".$r["contribution_no"]." ORDER BY contribution_no;";
        if ($res=$this->d->q($query)){
          while ($r=$res->fetch_assoc()){
            $t[]=$r["id"];
          }
        }
        return $t;        
      }      
      return false;
    }
    
    function get_note_no_from_contribution($contribution_id){
      if ($r=$this->get_conversation_contributions_record($contribution_id)){
        return $r["note_no"];
      }
      return false;
    }
     

    
}

?>