<?php

class cw_Maintenance {

    private $d; //Database access
    private $auth;
    
    function __construct($auth){
      $this->d = $auth->d;
      $this->auth = $auth;
    }
    
    /* Database maintenance functions */

    // clear unused writer / arranger / translator names, unused themes, style_tags, copyright_holders
    function music_db_clear_unused_records(){
      $mdb=new cw_Music_db($this->auth);
      $mdb->delete_unused_writers();
      $mdb->delete_unused_copyright_holders();
      $mdb->delete_unused_themes();
      $mdb->delete_unused_style_tags(); 
      $mdb->delete_unused_sources();         
    }
    
    //clear available_volunteer records that might exist for services in the past
    function service_planning_clear_unused_records(){
      //Find the most-recent but past church service
      //query returns array of available_volunteer record ids to delete (one day grace) 
      $q="
        SELECT DISTINCT
          available_volunteers.id
        FROM
          available_volunteers,church_services,events
        WHERE
          available_volunteers.service_id=church_services.id
            AND church_services.id=events.church_service
            AND events.is_rehearsal=0
            AND events.timestamp<".(time()-DAY).";
      ";      
      if ($res=$this->d->q($q)){
        if ($event_positions=new cw_Event_positions($this->d)){
          while($r=$res->fetch_assoc()){
            $event_positions->delete_available_volunteers_record($r["id"]);
          }        
        }
      }
    }
    
    //Clear sync marks for inactive sessions for one or all users
    function clear_old_sync_marks($person_id=0){
      //Get active sessions for the person (or all active sessions)
      $active_sessions=$this->auth->sessions->get_active_sessions($person_id);
      //Put together a condition that excludes active session ids from the pref_names to be deleted
      $cond="";
      if (is_array($active_sessions)){
        foreach ($active_sessions as $v){
          $cond.=" AND NOT (pref_name LIKE \"%".$v["session_id"]."%\")";
        }
        $cond=substr($cond,4);
        $cond.=" AND description=\"SYNC_MARK\"";
      } else {
        $cond="description=\"SYNC_MARK\"";
      }
      if ($upref=new cw_User_preferences($this->d,$this->auth->cuid)){
        if ($person_id==0){
          //Delete old sync marks regardless of person_id
          return $upref->delete_prefs_by_condition($this->auth->services->get_service_id_for_file("ajax/ajax_service_planning.php"),$cond,true);
        } else {
          //Delete old sync marks for current user
          return $upref->delete_prefs_by_condition($this->auth->services->get_service_id_for_file("ajax/ajax_service_planning.php"),$cond);                
        }
      }
      return false;
    }


}

?>