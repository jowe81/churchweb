<?php

class cw_Users {
  
    private $d; //Database access
    
    function __construct($d){
      $this->d = $d;
    }
    
    function check_for_table(){
      return $this->d->table_exists("users");
    }
    
    function create_table(){
      return $this->d->q("CREATE TABLE users (
                          id INT,
                          loginname char(50),
                          password char(50),
                          last_login INT,
                          last_ip char(45),
                          failed_login_attempts INT,
                          blocked_until INT,
                          active TINYINT,
                          login_counter INT,
                          contact_option_type_for_cw_comm CHAR(30)
                        )");
    }

    //Delete table (if extant) and re-create. Add root/default records.
    function recreate_tables($default_records=true){
      if ($this->check_for_table()){
        $this->d->drop_table("users");
      }
      $res=$this->create_table();
      if ($res && $default_records){
        //Add default user
        $this->add_user("admin","bacon",1);
      }
      return $res;
    }

    /* Basic user functions: add, update, delete, identify */
    
    //Add user but ensure uniqueness of login_name
    function add_user($loginname,$password,$person_id=NULL,$active=1){
      $e=array();
      $e["loginname"]=$loginname;
      $e["password"]=$password;
      $e["id"]=$person_id; //This should point to a church directory record
      $e["active"]=$active;      
      if (!$this->loginname_exists($loginname)){
        return $this->d->insert($e,"users");
      }
      return false;
    }    
    
    function delete_user($loginname){
      return $this->d->delete($loginname,"users","loginname");
    }
        
    //Change a user's login (provided the new name doesn't exist yet)
    function change_loginname($old_loginname,$new_loginname){
      //Only do anything if the two loginnames are not the same
      if ($old_loginname!=$new_loginname){
        if (!$this->loginname_exists($new_loginname)){
          $e=array();
          $e["loginname"]=$new_loginname;
          return $this->d->update_record("users","loginname",$old_loginname,$e);
        }
        return false;      
      }
      return true; //If the two loginnames are the same, then return true
    }
    
    //Change a user's password
    function change_password($loginname,$new_password){
      if ($this->loginname_exists($loginname)){
        $e=array();
        $e["password"]=$new_password;
        return $this->d->update_record("users","loginname",$loginname,$e);
      }      
      return false;
    }           
    
    //Activate an account
    function activate_account($loginname){
      if ($this->loginname_exists($loginname)){
        $e=array();
        $e["active"]=1;
        return $this->d->update_record("users","loginname",$loginname,$e);
      }      
      return false;      
    }
    
    //Deactivate an account
    function deactivate_account($loginname){
      if ($this->loginname_exists($loginname)){
        $e=array();
        $e["active"]=0;
        return $this->d->update_record("users","loginname",$loginname,$e);
      }      
      return false;      
    }
    
    function toggle_account_status($loginname){
      $this->account_is_active($loginname) ? $this->deactivate_account($loginname) : $this->activate_account($loginname);
    }

    //Does the login name exist already?
    function loginname_exists($loginname){
      return $this->d->record_exists("users","loginname",$loginname);          
    }

    //Get the loginname that corresponds to $id
    function get_loginname($id){
      if ($e=$this->d->get_record("users","id",$id)){
        return $e["loginname"];    
      }
      return false;
    }

    //Get the password that corresponds to $loginname
    function get_password($loginname){
      if ($e=$this->d->get_record("users","loginname",$loginname)){
        return $e["password"];    
      }
      return false;
    }
    
    /* Create session ID - taken from LifeLog - here used to generate password */
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
    
    //Generate a new password for user with loginname $loginname
    function generate_password($loginname){
      $new_password=substr($loginname,0,3).$this->create_sessionid(3);
      if ($this->change_password($loginname,$new_password)){
        return $new_password;
      }
      return false;            
    }
    
    //Get the id (person) that corresponds to $loginname
    function get_id($loginname){
      if ($e=$this->d->get_record("users","loginname",$loginname)){
        return $e["id"];    
      }
      return false;    
    }
    
    //Set the id (person) that corresponds to $loginname
    function set_id($loginname,$id){
      //Ensure that no other user record refers to this person
      if ((!$this->d->record_exists("users","id",$id)) || ($id==0)){
        if ($e=$this->d->get_record("users","loginname",$loginname)){
          $e["id"]=$id;
          return $this->d->update_record("users","loginname",$loginname,$e);    
        }
      }      
      return false;    
    }

    //Is this combination of $loginname and $password valid? If yes, return id of person
    function check_credentials($loginname,$password){
      if ($e=$this->d->get_record("users","loginname",$loginname)){
        if ($e["password"]==$password){
          return $e["id"];            
        }
      }
      return false;      
    }
    
    function account_is_active($loginname){
      if ($e=$this->d->get_record("users","loginname",$loginname)){
        return ($e["active"]>0);    
      }
      return false;        
    }
    
    function account_currently_blocked($loginname){
      if ($e=$this->d->get_record("users","loginname",$loginname)){
        return ($e["blocked_until"]>time());    
      }
      return false;            
    }
    
    //Return timestamp of last login
    function get_last_login($loginname){
      if ($e=$this->d->get_record("users","loginname",$loginname)){
        if ($e["last_login"]>0){
          return $e["last_login"]; 
        }
      }
      return -1; //Timestamp of -1 means never logged in      
    }
    
    //Called at login time
    function update_last_login_info($loginname,$last_login,$last_ip){
      if ($this->loginname_exists($loginname)){
        $e=array();
        $e["last_login"]=$last_login; //This should be a timestamp
        $e["last_ip"]=$last_ip;
        $e["failed_login_attempts"]=0; //Reset this counter after successful login
        $e["blocked_until"]=0; //Reset this, too (not strictly necessary, but tidy :))
        return $this->d->update_record("users","loginname",$loginname,$e);
      }      
      return false;                
    }
    
    //Called at login time
    function advance_login_counter($loginname){
      if ($e=$this->d->get_record("users","loginname",$loginname)){
        $e["login_counter"]++;    
        return $this->d->update_record("users","loginname",$loginname,$e);
      }
      return false;          
    }
    
    function get_login_count($loginname){
      if ($e=$this->d->get_record("users","loginname",$loginname)){
        return $e["login_counter"];
      }
      return false;                
    }
        
    function log_failed_login_attempt($loginname,$ip){
      if ($e=$this->d->get_record("users","loginname",$loginname)){
        $e["failed_login_attempts"]=(intval($e["failed_login_attempts"])+1); //increase counter
        $e["last_ip"]=$ip;
        if ($e["failed_login_attempts"]>CW_MAX_FAILED_LOGIN_ATTEMPTS){
          $e["blocked_until"]=time()+CW_TEMPORARY_ACCOUNT_BLOCK_LENGTH;
        }
        return $this->d->update_record("users","loginname",$loginname,$e);        
      }
      return false;                      
    }

    //Set the kind of email address the user wants to receive communication to (typically CW_CD_PERSONAL_EMAIL_CONTACT_OPTION_TYPE)
    function set_contact_option_type_for_cw_comm($id,$contact_option_type){
      if ($e=$this->d->get_record("users","id",$id)){
        $e["contact_option_type_for_cw_comm"]=$contact_option_type;
        return $this->d->update_record("users","id",$id,$e);    
      }
      return false;          
    }
    
    function get_contact_option_type_for_cw_comm($id){
      if ($e=$this->d->get_record("users","id",$id)){
        if ($e["contact_option_type_for_cw_comm"]!=""){
          return $e["contact_option_type_for_cw_comm"]; 
        }
      }
      return false;          
    }

    //Display users as a table
    function display(){
      $t="";
      if ($f=$this->d->get_table("users",$loginname)){
        foreach ($f as $k=>$v){
          $t.="<tr>
                <td><form action='?a=' method='POST'><input type='text' name='loginname' value='".$v["loginname"]."'/></td>
                <td><input type='text' name='password' value='".$v["password"]."'/></td>
                <td><input type='text' name='id' value='".$v["id"]."'/></td>
                <td>
                  <input type='submit' name='action' value='Save'/>
                  <input type='submit' name='action' value='Delete'/>
                  <input type='hidden' name='old_loginname' value='".$v["loginname"]."'/>
                  </form>
                </td>
              </tr>";
        }
        $t="<table>
              <tr><td>Login name</td><td>Password</td><td>Account owner (person-id)</td><td>Actions</td></tr>
              $t
            </table>";    
      }
      return $t;
    }
}

?>