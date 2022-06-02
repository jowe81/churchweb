<?php
class cw_Auth {

  public $services,$users,$groups,$group_memberships,$permissions,$sessions,$personal_records,$church_directory;
  public $d; //Database access
  
  //These get put in by the framework
  public $my_permitted_services=array(); //Array["service_id"]=permission_type for current user
  public $csid; //Current Service ID
  public $csps; //Current level permission status
  public $cuid; //Current User ID (should be same as $_SESSION["person_id"])
  
  function __construct(cw_Db $d){
    $this->d=$d;
    //Create the data objects
    $this->services = new cw_Services($d);
    $this->users = new cw_Users($d);
    $this->groups = new cw_Groups($d);
    $this->group_memberships = new cw_Group_memberships($d);
    $this->permissions = new cw_Permissions($d);
    $this->sessions = new cw_Sessions($d);
    $this->personal_records = new cw_Personal_records($d);
    $this->church_directory = new cw_Church_directory($d);
  }
  
  /* Login, Logout */
  
  //Check credentials, init session, return session_id on success
  //The calling script MUST save $this->my_permitted_services to $_SESSION["my_permitted_services"]
  //The framework MUST copy this variable back into $this->my_permitted_services on every request
  function login($loginname,$password,$ip=""){
    $person_id=$this->users->check_credentials($loginname,$password);
    if ((!($person_id===false)) && ($this->users->account_is_active($loginname)) && (!($this->users->account_currently_blocked($loginname)))){
      //Credentials are okay, account is active, not blocked. Now log the last_login info
      $this->users->update_last_login_info($loginname,time(),$ip);
      //Advance login counter
      $this->users->advance_login_counter($loginname); 
      //Calculate permitted services
      $this->find_permitted_services($person_id,$this->my_permitted_services);
      //Add to the permitted services array the permitted ajax services.
      $this->find_permitted_ajax_services($person_id,$this->my_permitted_services);
      //Init session and return session ID
      return ($this->sessions->init_session($person_id,$ip,SESSION_DEFAULT_TTL));
    }
    //Login failed: log failed attempt
    $this->users->log_failed_login_attempt($loginname,$ip);
    return false;
  }
  
  //Log out one particular session (user might have other active sessions)
  function logout($session_id){
    return $this->sessions->terminate_session($session_id);
  }

  //Validate a http request - called from framework for every page  
  function validate_request($session_id,$service_id){
    //Check validity of $session_id
    if ($this->sessions->status($session_id)==CW_SESSION_RECORD_IS_VALID){
      //Session id is valid.
      //Calling user authorized for requested service? (need not worry about person_id - use $this->my_permitted_services)
      if ($this->current_user_is_authorized($service_id)){
        //Update session
        if ($this->sessions->update_session($session_id)){
          return CW_SESSION_VALIDATED_SUCCESSFULLY;
        } else {
          return CW_SESSION_RECORD_COULD_NOT_BE_UPDATED;
        }
      } else {
        //User doesn't have access to service (or service might not exist)
        return CW_SESSION_OWNER_NOT_AUTHORIZED_FOR_REQUESTED_SERVICE;
      }
    } else {
      //Session is not valid (timed-out or non-extant): return status
      return $this->sessions->status($session_id);
    }
    return false;
  }
  
  //Is the person, either through direct or inherited user permissions,
  //or direct or inherited group permissions, authorized to use the service?
  //The answer is in $this->my_permitted_services (calculated at login time)
  function current_user_is_authorized($service_id){
    return ($this->my_permitted_services[$service_id]>-1);
  }
  
  //Dummy right now, always returns answer for current user - IMPLEMENT!!!
  function user_is_authorized($person_id,$service_id){
    return $this->current_user_is_authorized($service_id);
  }
  
  //Makes sure that the paths from the root node to the permitted_services
  //are accessible (i.e., grant 0 permission where necessary) 
  //Called from find_permitted_services.
  private function correct_ancestor_paths($service_id,&$permitted_services){
    if ($permitted_services[$service_id]==-1){
      //This node has -1, so go through each child to see if one passes up a permission
      $children=$this->services->get_all_direct_children($service_id);
      if (count($children)>0){
        foreach ($children as $c){
         //This node will retain the highest of the childrens' returns (i.e. if one of the children passes up a 0, it will stay)
         $permitted_services[$service_id]=max($this->correct_ancestor_paths($c,$permitted_services),$permitted_services[$service_id]);
        }
        //The highest of the childrens' returns needs to be passed back to parent
        return $permitted_services[$service_id];
      } else {
        //There is no child, so pass -1 back up
        return -1;
      }
    } else {
      //This node has (necessarily direct) authorization, pass 0 back to parent
      return 0;    
    }
  }
  
  /*
    Find ajax service permissions
    This function must be called after find_permitted_services, as the rights to ajax-services
    depend on the rights to the regular services as follows:
      for each service script with name ajax_x.php
        if service script with name x.php exists, ajax_x.php inherits the rights from it   
  */
  function find_permitted_ajax_services($person_id,&$permitted_services){
    $ajax=$this->services->get_ajax_service_records();
    foreach ($ajax as $v){
      //Cut the first 10 characters from filename (which are 'ajax/ajax_')
      $name=substr($v["file"],10);
      //Find service ID of related service from which to inherit the right
      if ($w=$this->services->get_service_id_for_non_ajax_file($name)){
        //The permission on the currently examined ajax service ($v) is the same as on service with id $w
        $permitted_services[$v["id"]]=$permitted_services[$w];      
      }
    }
  }
  
  //Searches for ALL services that this person may use through both user and
  //group permissions and saves them in assoc array $permitted_services (service_id->permission_type)
  function find_permitted_services($person_id,&$permitted_services){
    //Obtain the group memberships of this person to pass into recursive function
    $all_group_memberships=$this->group_memberships->get_memberships($person_id);
    //Weed out inactive groups
    $memberships_in_active_groups=array();
    foreach ($all_group_memberships as $k){
      if ($this->groups->is_active($k)){
        $memberships_in_active_groups[]=$k;
      }
    }
    //This function traverses the service tree and stores for each service the greatest direct or
    //inherited permission in $permitted_services(assoc array: service_id->permission_type)
    $this->find_permitted_user_services($person_id,$memberships_in_active_groups,$permitted_services);
    //Now the only issue might be that potentially we have non-permitted ancestors to permitted services
    //Therefore make sure that all ancestor-paths are granted a level 0 permission
    $this->correct_ancestor_paths(1,$permitted_services); 
  }    
  
  //Searches for all services that this person may use through USER PERMISSIONS
  //modifies passed_in array
  function find_permitted_user_services($person_id,$groups,&$permitted_services,$service_id=1,$parent_permission=-1){
    //Begin assuming that there are no direct user or group permissions on this node ->copy parent_permission
    $permitted_services[$service_id]=$parent_permission;
    //Go through all direct user and group permissions on this node and retain the greatest
    $greatest_direct_permission=$parent_permission; //Could set this to -1, but setting it to $parent_permission saves us a max() statement later
    //Consider potential direct user permission:    
    if (!(($direct_user_permission=$this->permissions->get_direct_user_permission_record($person_id,$service_id))===false)){
      //Found direct user permission.
      $greatest_direct_permission=max($greatest_direct_permission,$direct_user_permission["type"]);
    }
    //Consider each potential direct group permission: once for each group_membership
    foreach ($groups as $group_id){
      if (!(($direct_group_permission=$this->permissions->get_direct_group_permission_record($group_id,$service_id))===false)){
        $greatest_direct_permission=max($greatest_direct_permission,$direct_group_permission["type"]);
      }      
    }    
    //We can now assign $greatest_direct_permission to the permission level of the present node    
    $permitted_services[$service_id]=$greatest_direct_permission; 
    //Now repeat for each child
    $children=$this->services->get_all_direct_children($service_id);
    foreach ($children as $c){
     $this->find_permitted_user_services($person_id,$groups,$permitted_services,$c,$permitted_services[$service_id]);
    }
  }

  //Searches for all services that this group may use through its permissions
  //and returns a comma-separated list of them
  function find_permitted_group_services($group_id,&$permitted_services,$service_id=1,$parent_permission=-1){
    //Disregard this group unless it's marked active (inactive groups don't effect permissions)
    if ($this->groups->is_active($group_id)){
      //Obtain direct permission if there is one
      $direct_permission=$this->permissions->get_direct_group_permission_record($group_id,$service_id); 
      if (!is_array($direct_permission)){
        //No direct permission exists, so 'copy' the inherited permission
        $direct_permission=array();
        $direct_permission["type"]=$parent_permission;
      }
      //Either way now this node gets the greater of the direct permission and the parent node's permission
      $permitted_services[$service_id]=max($direct_permission["type"],$parent_permission); 
      //Now repeat for each child
      $children=$this->services->get_all_direct_children($service_id);
      foreach ($children as $c){
       $this->find_permitted_group_services($group_id,$permitted_services,$c,$permitted_services[$service_id]);
      }
    }
  }

  // Get Current Service Parent Link - i.e. full path to the parent script of the current service
  // (Used whenever a html link to the parent script is required)
  function get_cspl(){
    return CW_ROOT_WEB.$this->services->get_parents_url($this->csid);
  }
  
  //************************************************* 
  
  //Call this for user requested account activation (through login.php)
  //Will only activate accounts that have never been used and are not in fact active already
  function activate_account_by_lastname_firstname($lastname,$firstname){
    if ($id=$this->personal_records->get_personid_by_lastname_firstname($lastname,$firstname)){
      //Found the person.
      if ($loginname=$this->users->get_loginname($id)){
        //Account exists.
        if (!$this->users->account_is_active($loginname)){
          //Account is inactive
          if ($this->users->get_last_login($loginname)==-1){
            //User has never been logged in
            if ($this->users->generate_password($loginname)){
              //New password has been set
              $this->users->activate_account($loginname);
              //All is well - return person_id
              return $id;        
            }          
          }
        }
      }
    }
    return false;  
  }
  
  //Send the person with $id an email with their login credentials
  function email_login_credentials($id){
    if ($loginname=$this->users->get_loginname($id)){
      $password=$this->users->get_password($loginname);
      return $this->send_system_email($id,"Your ChurchWeb login credentials at ".CW_CHURCH_NAME,
        "Your login credentials for your ChurchWeb account are as follows:\n\nLogin name: $loginname \nPassword:   $password \n\nTo login, go to ".CW_FULL_PUBLIC_URL."login.php\n\nRegards,\n".CW_CHURCH_NAME
      );      
    }
    return false;
  }
  
  //Send a system email to person with id $id
  function send_system_email($id,$subject,$msg){
    if ($recipient=$this->get_cw_comm_email_for_user($id)){
      //Destination email found
  		return mail($recipient,$subject,"Hi ".$this->personal_records->get_first_name($id).",\n\n".$msg.CW_SYSTEM_MAIL_FOOTER,CW_SYSTEM_MAIL_HEADER);              
    }
    return false;    
  }
  
  //Return the email address that the user uses for CW
  function get_cw_comm_email_for_user($id){
    if ($id>0){
      //Regular user
      //What kind of email address does the user want for cw communication?
      if ($email_type=$this->users->get_contact_option_type_for_cw_comm($id)){
        //Obtain address
        if ($recipient=$this->church_directory->get_contact_option($id,$email_type)){
          return $recipient["value"];
        }
      }
    } else {
      //Guest
      return $this->personal_records->get_guest_email($id);
    }
    return false;    
  }
  
  //Reset preferences for person $id
  function apply_default_user_preferences($id){
    $u=new cw_User_preferences($this->d,$id); //Create upref object for person $id
    //Privacy settings
    $s=$this->services->get_service_id_for_file('ajax/ajax_privacy_settings.php');
    $u->write_pref($s,"show_lastname_firstname","CHECKED");  
    $u->write_pref($s,"show_primary_postal","CHECKED");  
    $u->write_pref($s,"show_primary_homephone","CHECKED");  
    $u->write_pref($s,"show_option_".str_replace(" ","_",CW_CD_PERSONAL_EMAIL_CONTACT_OPTION_TYPE),"CHECKED"); //spaces in the contact option type replaced with underscore  
  
  }
  
  
  /*********************************************/
  
  function have_mediabase_permission($type=CW_V){
  	if (($sv=new cw_Services($this->d)) && ($mb_service_id=$sv->get_service_id_for_file('worship_dpt/mediabase.php'))){
  		if ($this->my_permitted_services[$mb_service_id]>=$type){
  			return true;
  		}
  	}
  	return false;
  }
  
  
}
?>