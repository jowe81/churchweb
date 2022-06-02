<?php

  class cw_Services {
  
    private $d; //Database access
    
    function __construct($d){
      $this->d = $d;
    }
    
    function check_for_table(){
      return $this->d->table_exists("services");
    }
    
    function create_table(){
      return $this->d->q("CREATE TABLE services (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          lft INT,
                          rgt INT,
                          parent INT,
                          lvl INT,
                          title char(100),
                          type char(20),
                          file char(150),
                          title_long char(150),
                          show_in_menu TINYINT
                        )");
    }

    //Delete table (if extant) and re-create. Add root/default records.
    function recreate_tables($default_records=true){
      if ($this->check_for_table()){
        $this->d->drop_table("services");
      }
      $res=$this->create_table();
      if ($res && $default_records){
        //Add root service manually
        $this->d->q("INSERT INTO services (title,type,show_in_menu) VALUES ('root','node',0)");
        $s_root=1; //Default id for root node
        //Add logout and home -- both are not shown as part of the menu structure, but through extra links
        $s_logout=$this->add_service($s_root,"Logout","script","logout.php","Not shown in menu",0);
        $s_home=$this->add_service($s_root,"Home","script","home.php","Not shown in menu",0);
        //Add other top-line (level 2) records
        $s_my_services=$this->add_service($s_root,"My Services");
        $s_ministries=$this->add_service($s_root,"Ministries");
        $s_office=$this->add_service($s_root,"Office");
        $s_facilities=$this->add_service($s_root,"Facilities");
        $s_community=$this->add_service($s_root,"Community");
        $s_system=$this->add_service($s_root,"System");
        
        //AJAX node (not shown in menu)
        $s_ajax=$this->add_service($s_root,"ajax","node","","Not shown in menu",0);
        //Under 'ajax'
        $this->add_service($s_ajax,"ajax_home","script","ajax/ajax_home.php","Not shown in menu",0);
        $this->add_service($s_ajax,"ajax_db","script","ajax/ajax_db.php","Not shown in menu",0);
        $this->add_service($s_ajax,"ajax_user_preferences","script","ajax/ajax_user_preferences.php","Not shown in menu",0);
        $this->add_service($s_ajax,"ajax_date_picker","script","ajax/ajax_date_picker.php","Not shown in menu",0);
        $this->add_service($s_ajax,"ajax_room_booking","script","ajax/ajax_room_booking.php","Not shown in menu",0);
        $this->add_service($s_ajax,"ajax_church_directory","script","ajax/ajax_church_directory.php","Not shown in menu",0);
        $this->add_service($s_ajax,"ajax_personal_records","script","ajax/ajax_personal_records.php","Not shown in menu",0);
        $this->add_service($s_ajax,"ajax_manage_users","script","ajax/ajax_manage_users.php","Not shown in menu",0);
        $this->add_service($s_ajax,"ajax_manage_groups","script","ajax/ajax_manage_groups.php","Not shown in menu",0);
        $this->add_service($s_ajax,"ajax_add_group","script","ajax/ajax_add_group.php","Not shown in menu",0);
        $this->add_service($s_ajax,"ajax_add_person","script","ajax/ajax_add_person.php","Not shown in menu",0);
        $this->add_service($s_ajax,"ajax_personal_information","script","ajax/ajax_personal_information.php","Not shown in menu",0);
        $this->add_service($s_ajax,"ajax_privacy_settings","script","ajax/ajax_privacy_settings.php","Not shown in menu",0);
        $this->add_service($s_ajax,"ajax_full_directory","script","ajax/ajax_full_directory.php","Not shown in menu",0);
        $this->add_service($s_ajax,"ajax_church_calendar","script","ajax/ajax_church_calendar.php","Not shown in menu",0);
        $this->add_service($s_ajax,"ajax_service_planning","script","ajax/ajax_service_planning.php","Not shown in menu",0);
        $this->add_service($s_ajax,"ajax_music_db","script","ajax/ajax_music_db.php","Not shown in menu",0);
        $this->add_service($s_ajax,"ajax_my_commitments","script","ajax/ajax_my_commitments.php","Not shown in menu",0);
        $this->add_service($s_ajax,"ajax_display_bookings_live","script","ajax/ajax_display_bookings_live.php","Not shown in menu",0);
        $this->add_service($s_ajax,"ajax_display_bookings_list","script","ajax/ajax_display_bookings_list.php","Not shown in menu",0);
        $this->add_service($s_ajax,"ajax_change_password","script","ajax/ajax_change_password.php","Not shown in menu",0);
        $this->add_service($s_ajax,"ajax_changelog","script","ajax/ajax_changelog.php","Not shown in menu",0);
        
        
        //Under 'my_services'
        $s_my_account=$this->add_service($s_my_services,"My Account");
        $s_change_password=$this->add_service($s_my_account,"Change Password","script","my_services/change_password.php");
        $s_privacy_settings=$this->add_service($s_my_account,"My Privacy Settings","script","my_services/privacy_settings.php");
        $s_personal_information=$this->add_service($s_my_account,"My Personal Record","script","my_services/personal_information.php");
        $s_my_churchdir=$this->add_service($s_my_services,"My Church Directory","script","my_services/church_directory.php");
        $s_my_commitments=$this->add_service($s_my_services,"My Commitments","script","my_services/my_commitments.php");
        //Under 'ministries'
        $s_worship_department=$this->add_service($s_ministries,"Worship Department","script","worship_dpt/index.php");
        $s_library=$this->add_service($s_ministries,"Library");
        //--Under 'worship department'
        $s_service_planning=$this->add_service($s_worship_department,"Service Planning","script","worship_dpt/service_planning.php");
        $s_music_database=$this->add_service($s_worship_department,"Music Database","script","worship_dpt/music_db.php");
        //Under 'system'
        $this->add_service($s_system,"Reports/Changelog","script","system/changelog.php");
        $this->add_service($s_system,"Manage Users","script","system/manage_users.php");
        $s_manage_groups=$this->add_service($s_system,"Manage Groups","script","system/manage_groups.php");      
        $this->add_service($s_system,"Manage Sessions","script","system/manage_sessions.php");
        $this->add_service($s_system,"Manage Services","script","system/manage_services.php");
        $this->add_service($s_system,"Test/Upgrades","script","system/testing.php");
        $this->add_service($s_system,"Preferences","script","system/system_preferences.php");
        //Under 'manage groups'
        $this->add_service($s_manage_groups,"Add a group","script","system/add_group.php");            
        //Under 'office'
        $s_church_calendar=$this->add_service($s_office,"Church Calendar","script","office/church_calendar.php");
        $s_booking_system=$this->add_service($s_office,"Room Booking","script","office/room_booking.php");
        $s_full_directory=$this->add_service($s_office,"Full Directory","script","office/full_directory.php");
        $s_personal_records=$this->add_service($s_office,"Personal Records","script","office/personal_records.php");      
        //Under 'personal records'
        $this->add_service($s_personal_records,"Add a person","script","office/add_person.php");
        
        //Under 'facilities'
        $s_manage_rooms=$this->add_service($s_facilities,"Manage Rooms","script","facilities/manage_rooms.php");
        $s_display_bookings=$this->add_service($s_facilities,"Display Bookings");
        //Under 'Display bookings'
        $this->add_service($s_display_bookings,"Live","script","facilities/display_bookings_live.php");
        $this->add_service($s_display_bookings,"Daily List","script","facilities/display_bookings_list.php");
  
        //Under 'manage rooms'      
        $this->add_service($s_manage_rooms,"Add Room","script","facilities/add_room.php");
        $this->add_service($s_manage_rooms,"Edit Room","script","facilities/edit_room.php","",0);
        $this->add_service($s_manage_rooms,"Edit Amenity List","script","facilities/edit_feature_list.php");
        
        //Due to legacy issues with existing installations, later additions come here:
        $s_mediabase=$this->add_service($s_worship_department,"Mediabase","script","worship_dpt/mediabase.php");
        $this->add_service($s_ajax,"ajax_mediabase","script","ajax/ajax_mediabase.php","Not shown in menu",0);
        $s_projector_feeds=$this->add_service($s_worship_department,"Projector Feeds","script","worship_dpt/projector_feeds.php");
        //$this->add_service($s_ajax,"ajax_projector_feeds","script","ajax/ajax_projector_feeds.php","Not shown in menu",0);

        
      }
      return $res;
    }

    /* Basic service functions: add, delete, build_service_tree */
    
    function add_service($parent,$title,$type="node",$file="",$title_long="",$show_in_menu=1){
      //Add only if parent exists
      if ($this->service_exists($parent)){      
        $e=array();
        $e['parent']=$parent;
        $e['title']=$title;
        $e['type']=$type;
        $e['file']=$file;
        $e['title_long']=$title_long;
        $e['show_in_menu']=$show_in_menu;    
        //Make sure that service with this title doesn't exist in this context yet
        if ($res=$this->d->query("SELECT id FROM services WHERE parent=".$e["parent"]." AND title='".$e["title"]."'")){
          if (mysqli_num_rows($res)==0){
            //Service doesn't exist yet - move on
            if ($this->d->insert($e,"services")){
              $this->build_service_tree(); //Must rebuild tree after insert operation
              //Retrieve the record back to be able to return the id under which it was saved
              if ($res=$this->d->query("SELECT id FROM services WHERE parent=".$e["parent"]." AND title='".$e["title"]."'")){
                if ($e=$res->fetch_assoc()){
                  return $e["id"];
                }
              }
            }          
          }
        }  
      }
      return false;
    }  
    
    function delete_service($id){
      //Delete only if service is not a parent
      if (!$this->service_is_parent($id)){
        if ($this->d->delete($id,"services")){
          $this->build_service_tree(); //Must rebuild tree after delete operation
          return true;        
        }      
      }
      return false;
    }
    
  	//Build hierarchy with Modified preorder tree traversal
    //Keep level column as well
  	private function build_service_tree($id=1,$left=1,$lvl=1) {
  		//Assume this is a leaf
  		$right=$left+1;
  		//Get children if there are any
  		$res=$this->d->query("SELECT id FROM services WHERE parent=$id");
  		//Execute function for each child
  		while ($row=$res->fetch_assoc()) {
  			//The rgt value of the last child plus 1 will be the rgt value for the calling node
  			$right=$this->build_service_tree($row["id"],$right,$lvl+1)+1; 
  		}
  		//We know $left, now since the children have been processed we also know $right and can store
  		$this->d->query("UPDATE services SET lft=$left, rgt=$right, lvl=$lvl WHERE id=$id"); 
  		//Return $right for the calling node
  		return $right;
  	}

  	//Display tree (http://articles.sitepoint.com/print/hierarchical-data-database)
  	public function display_service_tree($id=1,$url="") {
      $t=""; //Return string
  		//Retrieve this node
  		$res=$this->d->query("SELECT * FROM services WHERE id=$id");
  		$row=$res->fetch_assoc();
  		//Create empty array of rgt values
  		$right=array();
  		//Retrieve the children of this node
  		if($res=$this->d->query("SELECT * FROM services WHERE lft BETWEEN ".$row["lft"]." AND ".$row["rgt"]." ORDER BY lft ASC;")){
    		//Go through each child
    		while ($row=$res->fetch_assoc()) {
    			//Pop off values from the $right stack if necessary
    			if (count($right)>0) {
    				while($right[count($right)-1]<$row["rgt"]) {
    					array_pop($right);
    				}
    			}
    			//Display indented node name
          $link_a="<a href='$url?a=edit&id=".$row["id"]."'>";
          $link_b="</a> | 
                   <a href='$url?a=move_left&id=".$row["id"]."'>&lt;</a>  
                   <a href='$url?a=move_right&id=".$row["id"]."'>&gt;</a>";
    			$t.=("<div id=\"e".$row["id"]."\">".str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;",count($right))
    					.$link_a.$row["title"]."$link_b (".$row["id"].")</div>");
    			//Add this node to the stack
    			$right[]=$row["rgt"];
    		}
      }
      return $t;
  	}

    /* Utility functions */

    //Return the full record for service $id
    function get_service_record($id){
      return $this->d->get_record("services","id",$id);
    }
    
    //Return title for service $id
    function get_service_title($id){
      if ($e=$this->get_service_record($id)){
        return $e["title"];
      }
      return false;
    }
    
    //Returns html with service path and linked ancestors
    function display_path_to_service($id){
      $t='';
      $e=$this->get_all_ancestors($id);
      array_shift($e); //Shift off the root node (need not display that one)
      foreach ($e as $v){
        $f=$this->get_service_record($v);
        $t.=$f["title"]." > ";
      }   
      $f=$this->get_service_record($id); //Get the title of the service in question
      $t.=$f["title"];
      return $t;
    
    }


    //Return the service_id associated with file $file (typically from $_SERVER["SCRIPT_FILENAME")
    function get_service_id_for_file($file){
      $file=get_cw_relative_path($file);
      if ($res=$this->d->query("SELECT id FROM services WHERE file='$file'")){
        if ($e=$res->fetch_assoc()){
          return $e["id"];
        }
      }
      return false;    
    }
    
    //Return the id of the service with script %$file, but not ajax/ajax_$file
    function get_service_id_for_non_ajax_file($file){
      $file=get_cw_relative_path($file);
      if ($res=$this->d->query("SELECT id,file FROM services WHERE file LIKE '%/$file' OR file='$file'")){
        while ($r=$res->fetch_assoc()){
          if (substr(strtolower($r["file"]),0,10)!="ajax/ajax_"){
            return $r["id"];
          }
        }
      } 
      return false; 
    }
    
    //Get the URL of the related non-ajax service ($id belongs to an ajax service), relative to CW
    function get_non_ajax_service_url($id){
      //Get record for ajax service
      $ajax_service=$this->get_service_record($id);
      //Cut off ajax prefix from filename
      $non_ajax_filename=substr($ajax_service["file"],strrpos($ajax_service["file"],"ajax_")+5);
      //Get service id for non ajax service
      $non_ajax_id=$this->get_service_id_for_non_ajax_file($non_ajax_filename);
      //Get record for non_ajax service
      $non_ajax_service=$this->get_service_record($non_ajax_id);
      //Return CW relative URL
      return $non_ajax_service["file"];          
    }
        
    //Is this service a parent?
    function service_is_parent($id){
      return (mysqli_num_rows($this->d->query("SELECT id FROM services WHERE parent=$id")));
    }
    
    //Does this service exist?
    function service_exists($id){
      return (mysqli_num_rows($this->d->query("SELECT id FROM services WHERE id=$id"))>0);
    }
    
    //Is service $id1 ancestor of service $id2?
    function is_ancestor_of($id1,$id2){
      if (($e1=$this->get_service_record($id1)) && ($e2=$this->get_service_record($id2))){
        //1st service is ancestor of 2nd if its left value is lower and its right value higher than that of 2nd
        return (($e1["lft"]<$e2["lft"]) && ($e1["rgt"]>$e2["rgt"]));
      }      
    }
  
    //Is service $id1 descendant of service $id2?
    function is_descendant_of($id1,$id2){
      if (($e1=$this->d->get_record("services","id",$id1)) && ($e2=$this->d->get_record("services","id",$id2))){
        //1st service is descendant of 2nd if its left value is greater and its right value lower than that of 2nd
        return (($e1["lft"]>$e2["lft"]) && ($e1["rgt"]<$e2["rgt"]));
      }      
    }
    
    //Return array of IDs of direct children of service $id; if $leaves_only is set, skip those children who are themselves parents
    function get_all_direct_children($id,$leaves_only=false){
      $t=array();
      if ($res=$this->d->query("SELECT id FROM services WHERE parent=$id ORDER BY lft")){  
        //These are orderd by lft - i.e. in their order of appearance from left to right! (important for menu display)
        while ($e=$res->fetch_assoc()){
          if ((!$leaves_only) || (!$this->service_is_parent($e["id"]))){
            $t[]=$e["id"];      
          }
        }
      }
      return $t;
    }
    
    //Return ID of parent service
    function get_parent($id){
      if ($e=$this->get_service_record($id)){
        return $e["parent"];
      }
      return false;
    }
    
    //Return array of IDs of ALL descendants of service $id
    function get_all_descendants($id){
      $t=array();
      //Obtain full service record
      $f=$this->get_service_record($id);
      //Services with greater left, smaller right values are descendants
      if ($res=$this->d->query("SELECT id FROM services WHERE lft>".$f["lft"]." AND rgt<".$f["rgt"])){
        while ($e=$res->fetch_assoc()){
          $t[]=$e["id"];      
        }
      }
     return $t;
    }
    
    //Return array of IDs of ALL ancestors of service $id
    function get_all_ancestors($id){
      $t=array();
      //Obtain full service record
      $f=$this->get_service_record($id);
      //Services with smaller left, greater right values are ancestors
      if ($res=$this->d->query("SELECT id FROM services WHERE lft<".$f["lft"]." AND rgt>".$f["rgt"])){
        while ($e=$res->fetch_assoc()){
          $t[]=$e["id"];      
        }
      }
      return $t;
    }

    //What level in the hierarchy is the service at?    
    function get_service_level($id){ 
      //Service level equals the number of ancestors+1 [True, but takes longer than recalling saved value]
      //  return (count($this->get_all_ancestors($id))+1);
      if ($f=$this->get_service_record($id)){
        return $f["lvl"];
      }
      return false;
    }
    
    //Look for a sibling of service $id that has the title $title_of_sibling. Return full record.
    function get_sibling_by_title($id,$title_of_sibling){
      $service_record=$this->get_service_record($id);
      if($res=$this->d->query("SELECT * FROM services WHERE parent=".$service_record["parent"])){
        //Iterate over siblings to see if one fits
        while ($e=$res->fetch_assoc()){
          if (strtolower($e["title"])==strtolower($title_of_sibling)){
            return $e; //Return entire record
          }        
        }
      } 
      return false;
    }
    
    function get_siblings_url_by_title($id,$title_of_sibling){
      if ($e=$this->get_sibling_by_title($id,$title_of_sibling)){
        return $e["file"];
      }
      return false;
    }
    
    //Look for a child of service $id that has the title $title_of_child. Return full record.
    function get_child_by_title($id,$title_of_child){
      if($res=$this->d->query("SELECT * FROM services WHERE parent=$id")){
        //Iterate over siblings to see if one fits
        while ($e=$res->fetch_assoc()){
          if (strtolower($e["title"])==strtolower($title_of_child)){
            return $e; //Return entire record
          }        
        }
      } 
      return false;    
    }

    function get_childs_url_by_title($id,$title_of_child){
      if ($e=$this->get_child_by_title($id,$title_of_child)){
        return $e["file"];
      }
      return false;
    }
        
    function get_service_url($id){
      if ($e=$this->get_service_record($id)){
        return $e["file"];
      }
      return false;      
    }
    
    function get_parents_url($id){
      return $this->get_service_url($this->get_parent($id));
    }    
    
    //Return array of entire table ordered by hierarchy level, horizontal position 
    function get_service_hierarchy(&$result,$id=1,$no_ajax=true){
      /* 
        Pseudo-Code
          Add this node to array
          Get all direct children
          For each child
            get_service_hierarchy(child-id,$result)
      */
      $tmp=$this->get_service_record($id);
      if ((substr($tmp["title"],0,4)!="ajax") || (!$no_ajax)){
        $result[]=$tmp;
        $children=$this->get_all_direct_children($id);
        foreach ($children as $v){
          $this->get_service_hierarchy($result,$v,$no_ajax);
        }      
      }
    }
    
    /* Moving services sideways, i.e. change horizontal order */
    
    //A left sibling exists if a service exists that has a rgt value adjacent to this service's lft value
    function get_left_sibling($id){
      if ($e=$this->get_service_record($id)){
        if ($res=$this->d->query("SELECT id FROM services WHERE rgt=".($e["lft"]-1))){
          if ($f=$res->fetch_assoc()){
            return $f["id"];
          }
        }
      }
      return false;
    }    
    
    //A right sibling exists if a service exists that has a lft value adjacent to this service's rgt value
    function get_right_sibling($id){
      if ($e=$this->get_service_record($id)){
        if ($res=$this->d->query("SELECT id FROM services WHERE lft=".($e["rgt"]+1))){
          if ($f=$res->fetch_assoc()){
            return $f["id"];
          }
        }
      }
      return false;
    }    
    //Swap the positions of the two services (i.e. swap their lft/rgt values)
    function swap_positions($id1,$id2){
      if (($e=$this->get_service_record($id1)) && ($f=$this->get_service_record($id2))){
        list($e["lft"],$f["lft"])=array($f["lft"],$e["lft"]); //Swap lft values
        list($e["rgt"],$f["rgt"])=array($f["rgt"],$e["rgt"]); //Swap rgt values
        return (($this->d->update_record("services","id",$id1,$e)) && ($this->d->update_record("services","id",$id2,$f)));
      }
      return false;    
    }

    //Move the service one position to the left (i.e. swap places with sibling if extant)
    function move_left($id){
      $sibling=$this->get_left_sibling($id);
      if ($sibling>0){
        return $this->swap_positions($id,$sibling);
      }
      return false;
    }

    //Move the service one position to the right (i.e. swap places with sibling if extant)
    function move_right($id){
      $sibling=$this->get_right_sibling($id);
      if ($sibling>0){
        return $this->swap_positions($id,$sibling);
      }
      return false;
    }

    //Return records of all services that have a scriptname starting with 'ajax_'
    function get_ajax_service_records(){
      $t=array();
      if ($res=$this->d->query("SELECT * FROM services WHERE file LIKE 'ajax_%';")){
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
      }
      return $t;
    }
    
    //Is the service an ajax service?
    function is_ajax_service($id){
      $r=$this->get_service_record($id);
      if (is_array($r)){
        if (substr($r["file"],0,10)=="ajax/ajax_"){
          return true;
        }
      }  
      return false;
    }
    
  }
?>
