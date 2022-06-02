<?php

class cw_Personal_records {
  
    private $d; //Database access
    
    function __construct($d){
      $this->d = $d;
    }
    
    function check_for_table($table="people"){
      return $this->d->table_exists($table);
    }
    
    //Makes sure table is dropped
    private function check_and_drop_table($table){
      if ($this->check_for_table($table)){
        $this->d->drop_table($table);
      }    
    }
    
    function create_tables(){
      return (($this->d->q("CREATE TABLE people (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          last_name char(100),
                          first_name char(100),
                          middle_names char(100),
                          maiden_name char(100),
                          gender char(1),
                          birthday int,
                          birthday_day tinyint,
                          birthday_month tinyint,
                          birthday_year smallint,
                          status char(30),
                          public_designation char(100),
                          deceased int,
                          notes TEXT,
                          active TINYINT
                        )"))
                &&        
             ($this->d->q("CREATE TABLE guests (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          name varchar(40),
                          email varchar(50)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE memberships (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          person_id INT,
                          timestamp INT,
                          status char(30)
                        )"))
                        );
    }

    //Delete tables (if extant) and re-create. Add default records.
    function recreate_tables($default_records=true){
      $this->check_and_drop_table("people");
      $this->check_and_drop_table("guests");
      $this->check_and_drop_table("memberships");
      $res=$this->create_tables();
      if ($res && $default_records){
        //Default person
        $this->add_person("Weber","Johannes","Markus","","m","354614400","member","Worship Pastor",-1,"");
        
        //Test persons (do not use these for real because they won't be given accounts)
        /*
        $this->add_person("Duck","Simone","","","w","-1","member","",-1,"");
        $this->add_person("Lowen","Rick","","","m","-1","member","",-1,"");
        $this->add_person("Warkentin","Ron","","","m","-1","member","",-1,"");
        */      
      }
      return $res;
    }

    ///////////////////////////////////////////////-/---------------------------TESTING
    /* Create random string - taken from LifeLog */
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
    
    //Not suitable for account testing - these people won't be given default accounts!
    function add_random_people($n){
      for ($i=0;$i<$n;$i++){
        $ln=$this->create_sessionid(10);
        $fn=$this->create_sessionid(10);
        $tl=$this->create_sessionid(10);
        $bd=mt_rand(-9999999,999999999);
        $this->add_person($ln,$fn,"Pete","","m",$bd,"member",$tl,-1,"");      
      }
    }
    ///////////////////////////////////////////////-/------------------------END TESTING


    /* People */
    
    function add_guest($name,$email=""){
      if ($name!=""){
        $e=array();
        $e["name"]=$name;
        $e["email"]=$email;
        return $this->d->insert_and_get_id($e,"guests");
      }
      return false;
    }
    
    function get_guest_name($id){
      $id=abs($id); //Guest ids are negative outside the database 
      if ($res=$this->d->query("SELECT name FROM guests WHERE id=$id;")){
        if ($r=$res->fetch_assoc()){
          return $r["name"];
        }
      }
      return false;
    }
    
    function get_guest_email($id){
      $id=abs($id); //Guest ids are negative outside the database 
      if ($res=$this->d->query("SELECT email FROM guests WHERE id=$id;")){
        if ($r=$res->fetch_assoc()){
          return $r["email"];
        }
      }
      return false;
    }
    
    
    function add_person($last_name,$first_name,$middle_names="",$maiden_name="",$gender="",$birthday_ts=-1,$status="",$public_designation="",$deceased_ts=-1,$notes="",$active=true){
      $e=array();
      $e["last_name"]=$last_name;    
      $e["first_name"]=$first_name;    
      $e["middle_names"]=$middle_names;
      $e["maiden_name"]=$maiden_name;    
      $e["gender"]=$gender;
      $e["birthday"]=$birthday_ts;
      $e["birthday_day"]=$e["birthday_month"]=$e["birthday_year"]=0;      
      if ($birthday_ts!=-1){
        $e["birthday_day"]=date("j",$birthday_ts);    
        $e["birthday_month"]=date("n",$birthday_ts);    
        $e["birthday_year"]=date("Y",$birthday_ts);      
      }
      $e["status"]=$status;
      $e["public_designation"]=$public_designation;    
      $e["deceased"]=$deceased_ts; //Time of death
      $e["notes"]=$notes;
      $e["active"]=$active;
      $id=$this->d->insert_and_get_id($e,"people");
      if ($id>0){
        $this->change_status($id,$status); //Create record for membership status history
        return $id; //Return person id of new person      
      }
      return false;
    }
    
    function update_person($id,$last_name,$first_name,$middle_names="",$maiden_name="",$gender="",$birthday_ts=0,$status="",$public_designation="",$deceased_ts=0,$notes="",$active=true){
      $e=array();
      $e["id"]=$id;
      $e["last_name"]=$last_name;    
      $e["first_name"]=$first_name;    
      $e["middle_names"]=$middle_names;
      $e["maiden_name"]=$maiden_name;    
      $e["gender"]=$gender;
      $e["birthday"]=$birthday_ts;
      $e["birthday_day"]=$e["birthday_month"]=$e["birthday_year"]=0;      
      if ($birthday_ts!=-1){
        $e["birthday_day"]=date("j",$birthday_ts);    
        $e["birthday_month"]=date("n",$birthday_ts);    
        $e["birthday_year"]=date("Y",$birthday_ts);      
      }
      $e["status"]=$status;
      $e["public_designation"]=$public_designation;    
      $e["deceased"]=$deceased_ts; //Time of death
      $e["notes"]=$notes;
      $e["active"]=$active;
      return (($this->d->update_record("people","id",$id,$e)) && ($this->change_status($id,$status)));
    }
    
    function delete_person($id){
      return ($this->d->delete($id,"people","id"));
    }
    
    function get_person_record($id){
      return ($this->d->get_record("people","id",$id));    
    }
    
    function person_exists($id){
      return ($this->get_person_record($id)>0);
    }
    
    function get_name_first_last($id){
      if ($id>0){
        if ($r=$this->get_person_record($id)){
          return $r["first_name"]." ".$r["last_name"];
        } 
      } else {
        return $this->get_guest_name($id);
      }
      return false;   
    }
    
    function get_name_first_last_initial($id,$omit_dot=false){
      $omit_dot ? $dot="" : $dot=".";
      if ($id>0){
        if ($r=$this->get_person_record($id)){
          return $r["first_name"]." ".substr($r["last_name"],0,1).$dot;
        } 
      } else {
        return $this->get_guest_name($id);
      }
      return false;   
    }
    
    function get_first_name($id){
      if ($id>0){
        if ($r=$this->get_person_record($id)){
          return $r["first_name"];
        }
      } else {
        return $this->get_guest_name($id);
      } 
      return false;       
    }
    
    
    
    function get_personid_by_lastname_firstname($lastname,$firstname){
      if ($res=$this->d->query("SELECT id FROM people WHERE LOWER(last_name)=LOWER('$lastname') AND LOWER(first_name)=LOWER('$firstname')")){
        if ($res->num_rows==1){
          //Make sure that there is only one person with this name
          if ($r=$res->fetch_assoc()){
            return $r["id"];
          }          
        }
      }
      return false; //means that there is either no person or more than 1 person with the name in question
    }
    
    //Return a number of personal records keyed by person_id just from cd_people
    //This function does AND filtering of parts of string fields
    //$filter array elements must be keyed according to people table
    function get_personal_records($filter=array(),$fields="*"){
      $t=array();
      $cond=cw_Db::array_to_query_conditions($filter);
      if ($res=$this->d->select("people",$fields,$cond." ORDER BY last_name,first_name")){
        while ($r=$res->fetch_assoc()){
          $t[$r["id"]]=$r;
        }
      }
      return $t;      
    }
    
    function get_names($filter=array()){
      return $this->get_personal_records($filter,"id,last_name,first_name");
    }
      
      
    //Membership status
    
    function change_status($person_id,$status,$timestamp=0){
      //Abort if $status is not valid
      if (($status!="friend") && ($status!="member") && ($status!="former member")){
        return false;
      }
      //Abort if status is same as previously stored, but return true
      if ($this->get_status($person_id)==$status){
        return true;
      }
      //If no timestamp is given, use NOW
      if ($timestamp==0){
        $timestamp=time();
      }      
      //Prepare record
      $e=array();
      $e["person_id"]=$person_id;
      $e["status"]=$status;
      $e["timestamp"]=$timestamp;
      
      return $this->d->insert($e,"memberships");      
    } 
    
    function get_latest_status_record($person_id){
      //Retrieve latest status change record 
      if ($res=$this->d->query("SELECT * FROM memberships WHERE person_id=$person_id ORDER BY timestamp DESC LIMIT 1")){
        $r=$res->fetch_assoc();
        return $r;
      }
      return false;        
    }
    
    function get_status($person_id){
      //Retrieve latest status change to determine current status
      if ($r=$this->get_latest_status_record($person_id)){
        return $r["status"];
      }
      return false;    
    }   
    
    
    //Return JSON.
    function get_name_autocomplete_suggestions($term){
      $term=mysqli_real_escape_string($this->d->db,$term);
      $query="
        SELECT
          id,last_name,first_name
        FROM
          people
        WHERE
          (last_name LIKE \"%$term%\"
        OR
          first_name LIKE \"%$term%\")
        ORDER BY
          last_name;
      ";    
      if ($res=$this->d->q($query)){
        $t=array();
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
        //Now make JSON string
        $z="";
        foreach ($t as $v){
          $label=$v["first_name"]." ".$v["last_name"];
          $value=substr($v["first_name"],0,1).". ".$v["last_name"]." (#".$v["id"].")";
          $z.=",
            {
              \"id\":\"".$v["id"]."\",
              \"label\":\"$label\",
              \"value\":\"$value\"
            }";
        }
        //Cut first comma
        if ($z!=""){
          $z=substr($z,1);
          $z="[ $z ]";
          return $z;        
        }
      }
      return false;
    }
    
}

?>