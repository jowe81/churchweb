<?php

class cw_Sessions {

    private $d; //Database access
    
    function __construct($d){
      $this->d = $d;
    }
    
    function check_for_table(){
      return $this->d->table_exists("sessions");
    }
    
    function create_table(){
      return $this->d->q("CREATE TABLE sessions (
                          session_id char(16) NOT NULL PRIMARY KEY,
                          person_id int,
                          created_at int,
                          updated_at int,
                          no_requests int,
                          ttl int,
                          ip char(39)
                        )");
                        // ip field can accommodate ipv6 addresses
                        // ttl field may hold non-standard time-to-live for a session
    }

    //Delete table (if extant) and re-create. 
    function recreate_tables($default_records=true){
      if ($this->check_for_table()){
        $this->d->drop_table("sessions");
      }
      return $this->create_table();
    }

    /* Basic session functions: init, terminate, update, check, identify */

    function init_session($person_id,$ip,$ttl=0){
      //This is a good time to remove potential zombie sessions
      $this->clear_old_sessions();
      //
      $e=array();
      $e["session_id"]=$this->create_sessionid(16); //Generate session id
      $e["person_id"]=$person_id;
      $e["created_at"]=$e["updated_at"]=time();
      $e["no_requests"]=1;
      $e["ttl"]=$ttl;
      $e["ip"]=$ip;
      //(No need to test uniqueness because session_id has PRIMARY KEY)
      if ($this->d->insert($e,"sessions")){
        //Return session_id upon success
        return $e["session_id"];
      }
      return false;    
    }
    
    function update_session($session_id){
      //Get session record
      if ($e=$this->d->get_record("sessions","session_id",$session_id)){        
        $e["no_requests"]++; //Inc request counter
        $e["updated_at"]=time();
        return $this->d->update_record("sessions","session_id",$session_id,$e);
      }
      return false;      
    }

    function terminate_session($session_id){
      return $this->d->delete($session_id,"sessions","session_id");
    }
        
    function session_is_valid($session_id){
      if ($e=$this->d->get_record("sessions","session_id",$session_id)){
        //Session exists, we retrieved it, no check if it has expired
        return ($e["updated_at"]+$e["ttl"]<time());
      }
      return false;        
    }

    function get_session_owner($session_id){
      if ($e=$this->d->get_record("sessions","session_id",$session_id)){
        return $e["person_id"];
      }
      return false;        
    }

    //Return session status constant
    function status($session_id){
      //Is session extant?
      if ($e=$this->d->get_record("sessions","session_id",$session_id)){
        //Has session timed out?
        if (($e["updated_at"]+$e["ttl"]>time()) && ($e["updated_at"]<=time())){
          //Session is valid
          return CW_SESSION_RECORD_IS_VALID;
        } else {
          return CW_SESSION_RECORD_HAS_TIMED_OUT;        
        }
      } else {
        //Session does not exist
        return CW_SESSION_RECORD_DOES_NOT_EXIST;
      }            
    }
    
    //Remove zombie session records
    function clear_old_sessions(){
      $query="DELETE FROM sessions WHERE (updated_at<".time()."-ttl)";
      return ($this->d->q($query));
    }
    
    function get_active_sessions($person_id=0){
      if ($person_id==0){
        $query="SELECT * FROM sessions WHERE (updated_at>".time()."-ttl)";
      } else {
        $query="SELECT * FROM sessions WHERE (person_id=$person_id) AND (updated_at>".time()."-ttl)";      
      }
      if ($res=$this->d->q($query)){
        $t=array();
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }      
        return $t;
      }
      return false;
    }

    /* Display */
    
    function display(){
      $t="";
      $active_sessions=$this->get_active_sessions();
      $t.="There are ".sizeof($active_sessions)." active sessions.";
      if ($f=$this->d->get_table("sessions","person_id")){
        foreach ($f as $k=>$v){
          $t.="<tr>
                <td>".$v["session_id"]."</td>
                <td>".$v["person_id"]."</td>
                <td>".date("m/d h:i:s",$v["created_at"])."</td>
                <td>".getHumanReadableLengthOfTime(time()-$v["updated_at"])."</td>
                <td>".$v["no_requests"]."</td>
                <td>".getHumanReadableLengthOfTime($v["ttl"])."</td>
                <td>".$v["ip"]."</td>
                <td>
                  <form action='?a=' method='POST'>
                    <input type='submit' name='action' value='Update'/>
                    <input type='submit' name='action' value='Terminate'/>
                    <input type='hidden' name='session_id' value='".$v["session_id"]."'/>
                  </form>
                </td>
              </tr>";
        }  
        $t="<table>
              <tr><td>Session ID</td><td>Owner</td><td>Logged on at</td><td>Idle</td><td>#Rq</td><td>TTL</td><td>Client IP</td></tr>
              $t
            </table>";    
      }    
      return $t;
    }

    /* Create session ID - taken from LifeLog */
  	private function create_sessionid($length)
  	{
  		$sid="";
  		for ($i=0;$i<$length;$i++)
  		{
  			$r=mt_rand(48,122);  //45-57, 65-90, 97-122
  			while ( (($r>57)&&($r<65)) or (($r>90)&&($r<97)) )
  			{ //get one digit
  				$r=mt_rand(48,122);				
  			}
  			$sid=$sid.chr($r);
  		}
  		return $sid;
  	}

}

?>