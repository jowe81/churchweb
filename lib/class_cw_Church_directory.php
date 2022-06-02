<?php

class cw_Church_directory {
  
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
      return (($this->d->q("CREATE TABLE cd_addresses (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          street_address char(200),
                          city char(100),
                          zip_code char(20),
                          province char(100),
                          country char(50),
                          default_home_phone char(50),
                          tag char(50)
                        )"))
                &&
             ($this->d->q("CREATE TABLE cd_contact_options (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          person_id INT,
                          type char(50),
                          value char(100),
                          modified_at INT                          
                        )"))
                &&
             ($this->d->q("CREATE TABLE cd_contact_option_types (
                          type_name char(30) NOT NULL PRIMARY KEY,
                          icon_code char(50),
                          is_phone_number tinyint,
                          is_email_address tinyint
                        )"))
                &&
             ($this->d->q("CREATE TABLE cd_pictures (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          person_id INT,
                          note TEXT,
                          is_primary tinyint,
                          file_id INT,
                          uploaded_at INT
                        )"))
                &&
             ($this->d->q("CREATE TABLE cd_households (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          head_person_id INT
                        )"))
                &&
             ($this->d->q("CREATE TABLE cd_household_memberships (
                          person_id INT,
                          household_id INT
                        )"))
                &&
             ($this->d->q("CREATE TABLE cd_people_to_addresses (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          person_id INT,
                          address_id INT,
                          apt_no char(30),
                          home_phone char(50),
                          is_primary tinyint,
                          modified_at INT
                        )"))
                &&
             ($this->d->q("CREATE TABLE cd_people_to_contact_options (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          person_id INT,
                          contact_option_id INT,
                          modified_at INT
                        )"))
                        );
    }

    //Delete tables (if extant) and re-create. Add default records.
    function recreate_tables($default_records=true){
      $this->check_and_drop_table("cd_addresses");
      $this->check_and_drop_table("cd_contact_options");
      $this->check_and_drop_table("cd_contact_option_types");
      $this->check_and_drop_table("cd_pictures");
      $this->check_and_drop_table("cd_households");
      $this->check_and_drop_table("cd_household_memberships");
      $this->check_and_drop_table("cd_people_to_addresses");
      $this->check_and_drop_table("cd_people_to_contact_options");
      $res=$this->create_tables();
      if ($res && $default_records){
        //Add default records
        $this->add_contact_option_type("personal email","&#9993;",false,true);        
        $this->add_contact_option_type("personal cell","&#128241;",true);
        $this->add_contact_option_type("work email","&#9993;",false,true);
        $this->add_contact_option_type("work cell","&#128241;",true);
        $this->add_contact_option_type("fax","&#128224;",true);
        $this->add_contact_option_type("website");
        $this->add_contact_option_type("icq number");
        
        $this->add_address("31954 Sunrise Cres.","Abbotsford","V2T 1N6","British Columbia","Canada","+1 (604) 859-8718","Tabor Court");
        $this->add_address("31950 Sunrise Cres.","Abbotsford","V2T 1N5","British Columbia","Canada","+1 (604) 859-8715","Tabor Manor");
        $this->add_address("31944 Sunrise Cres.","Abbotsford","V2T 1N5","British Columbia","Canada","+1 (604) 859-8715","Tabor Home");
        $this->add_address("2021 Primrose St.","Abbotsford","V2S 2Y9","British Columbia","Canada","+1 (604) 851-4004","Menno Terrace East");
        //Default records for testing:
        /*
        $this->add_address("123 Some street","Aldergrove","V2S 2Y9","British Columbia","Canada","","No default phone");
        $this->add_address("123 Some street","Aldergrove","V2S 2Y9","British Columbia","Canada","+1 (604) 851-4004 200","With ext");
        $this->add_full_address_for_person(1,"2305 272St","Aldergrove","V4W 2N7","BC","Canada","BSMT","+1 (604) 996-2981");
        $this->assign_address_to_person(2,1);        
        $this->add_full_address_for_person(3,"Henri-Dunant-Strasse 16","Gelsenkirchen","45889","NRW","Germany","","+49 (209) 883326");
        $this->add_full_address_for_person(4,"","","","","China","","");
        $this->add_contact_option(1,"personal email","john.doe@churchweb.ca");
        $this->add_contact_option(1,"work email","john@somecompany.ca");
        $this->add_contact_option(2,"work email","jane.doe@somecompany.ca");
        $this->add_contact_option(2,"icq number","874440");
        
        $this->add_household(1);
        $this->assign_person_to_household(2,1);
        $this->assign_person_to_household(3,1);
        */
      }
      return $res;
    }
        
    /* Addresses */
    
    function add_address($street_address,$city="",$zip_code="",$province="",$country="",$default_home_phone="",$tag=""){
      $e=array();
      $e["street_address"]=$street_address;
      $e["city"]=$city;
      $e["zip_code"]=$zip_code;
      $e["province"]=$province;
      $e["country"]=$country;
      $e["default_home_phone"]=$default_home_phone;
      $e["tag"]=$tag;
      return ($this->d->insert_and_get_id($e,"cd_addresses"));
    }
    
    function update_address($id,$street_address,$city="",$zip_code="",$province="",$country="",$default_home_phone="",$tag=""){
      $e=array();
      $e["id"]=$id;
      $e["street_address"]=$street_address;
      $e["city"]=$city;
      $e["zip_code"]=$zip_code;
      $e["province"]=$province;
      $e["country"]=$country;
      $e["default_home_phone"]=$default_home_phone;
      $e["tag"]=$tag;
      return ($this->d->update_record("cd_addresses","id",$id,$e));
    }
    
    private function delete_address($id){
      return ($this->d->delete($id,"cd_addresses","id"));      
    }
    
    function get_address_record($id){
      return ($this->d->get_record("cd_addresses","id",$id));    
    }
    
    //Does this address have a value in the 'tag' field?  
    function address_is_tagged($id){
      if ($r=$this->get_address_record($id)){
        return ($r["tag"]!="");
      }
      return false;    
    }    
    
    //Return an array of address records
    function get_tagged_addresses(){
      $t=array();
      if ($res=$this->d->query("SELECT * FROM cd_addresses WHERE tag!='' ORDER BY tag;")){
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
      }
      return $t;    
    }
    
    
    /* Other contact options */
    
    function add_contact_option($person_id,$type,$value){
      $e=array();
      $e["person_id"]=$person_id;
      $e["type"]=$type; //e.g. "email" or "icq"
      $e["value"]=$value;
      $e["modified_at"]=time();
      //For adding make sure this option does not exist yet for this user, otherwise update
      if (!($f=$this->get_contact_option($person_id,$type))){
        return ($this->d->insert($e,"cd_contact_options"));          
      } else {
        //Update
        return ($this->d->update_record("cd_contact_options","id",$f["id"],$e));
      }
      return false;
    }

    function delete_contact_option($person_id,$type){
      return ($this->d->query("DELETE FROM cd_contact_options WHERE person_id=$person_id AND type='$type'"));      
    }
    
    //Get contact option record by id
    function get_contact_option_record($id){
      return ($this->d->get_record("cd_contact_options","id",$id));    
    }
    
    //Get contact option record for person by type
    function get_contact_option($person_id,$type){
      if ($res=$this->d->query("SELECT * FROM cd_contact_options WHERE person_id=$person_id AND type='$type'")){
        if ($r=$res->fetch_assoc()){
          return $r;
        }
      }
      return false;
    }
    
    //How many email addresses do we have for this person?
    function get_number_of_email_addresses_for_person($person_id){
      $email_address_types=$this->get_contact_option_types_email_addresses();
      $cnt=0;
      foreach ($email_address_types as $v){
        if ($this->get_contact_option($person_id,$v["type_name"])){
          $cnt++; //Found an email
        }
      }
      return $cnt;   
    }

    /* Contact option types */

    function add_contact_option_type($type_name,$icon_code="",$is_phone_number=false,$is_email_address=false){
      $e=array();
      $e["type_name"]=$type_name; //e.g. "email" or "icq"
      $e["icon_code"]=$icon_code; //unicode character code
      $e["is_phone_number"]=$is_phone_number;
      $e["is_email_address"]=$is_email_address;
      return ($this->d->insert($e,"cd_contact_option_types"));      
    }

    function delete_contact_option_type($type_name){
      return ($this->d->delete($type_name,"cd_contact_option_types","type_name"));      
    }
  
    //Get the full table will ALL contact option types
    function get_contact_option_types(){
      return ($this->d->get_table("cd_contact_option_types"));    
    }
    
    //Return only those types that are phone numbers
    function get_contact_option_types_numbers(){
      $t=array();
      $x=$this->get_contact_option_types();
      foreach ($x as $v){
        if ($v["is_phone_number"]){
          $t[]=$v;              
        }
      }
      return $t;
    }

    //Return only those types that are email addresses
    function get_contact_option_types_email_addresses(){
      $t=array();
      $x=$this->get_contact_option_types();
      foreach ($x as $v){
        if ($v["is_email_address"]){
          $t[]=$v;              
        }
      }
      return $t;
    }
    
    //Return only those types that are NOT phone numbers
    function get_contact_option_types_other(){
      $t=array();
      $x=$this->get_contact_option_types();
      foreach ($x as $v){
        if (!$v["is_phone_number"]){
          $t[]=$v;              
        }
      }
      return $t;
    }
    
    //Return array with key = type name and value = icon code
    function get_contact_option_icon_codes(){
      $t=array();
      $x=$this->get_contact_option_types();
      foreach ($x as $v){
        $t[$v["type_name"]]=$v["icon_code"];
      }
      return $t;    
    }
    
    //Is this $type_name (e.g. 'work email', 'icq number') and email address?
    function contact_option_type_is_email_address($type_name){
      $email_address_types=$this->get_contact_option_types_email_addresses();
      //$email_address_types contains full sql records, i.e. arrays, therefore loop to compare
      foreach ($email_address_types as $v){
        if ($v["type_name"]==$type_name){
          return true;
        }
      }
      return false;
    }

    /* Pictures */
    
    function add_picture($person_id,$note,$is_primary,$file_id){
      $e=array();
      $e["person_id"]=$person_id;
      $e["note"]=$note;
      $e["is_primary"]=$is_primary;
      $e["file_id"]=$file_id; 
      $e["uploaded_at"]=time();    
      return ($this->d->insert($e,"cd_pictures"));            
    }
    
    function delete_picture($id){
      return ($this->d->delete($id,"cd_pictures","id"));      
    }
    
    function get_picture_record($id){
      return ($this->d->get_record("cd_pictures","id",$id));    
    }
    
    /* Households */
    
    //Does household exist? (If so, return head)
    function household_exists($hh_id){
      if ($r=$this->d->get_record("cd_households","id",$hh_id)){
        return $r["head_person_id"];
      }
      return false;  
    }
    
    //Return array of person ids that are members in the household. Does NOT include the head.
    function get_household_members($hh_id){
      $t=array();
      if ($res=$this->d->query("SELECT person_id FROM cd_household_memberships WHERE household_id=$hh_id")){
        while ($r=$res->fetch_assoc()){
          $t[]=$r["person_id"];
        }
      }
      return $t;    
    }
    
    //Is this person head of their household? Return false, or hh id
    function person_is_head_of_household($person_id){
      if ($r=$this->d->get_record("cd_households","head_person_id",$person_id)){
        return $r["id"];
      }
      return false;    
    }

    //Is this person either head or member of a household (i.e. "plugged in" somewhere)?     
    function person_is_household_head_or_member($person_id){
      return (($this->person_is_head_of_household($person_id)) || ($this->get_household_id_for_person($person_id)));    
    }
    
    //Return the id of the household the person is a MEMBER at (false if person is head of a household or has no household)
    function get_household_id_for_person($person_id){
      if ($r=$this->d->get_record("cd_household_memberships","person_id",$person_id)){
        return $r["household_id"];
      }
      return false;              
    }
    
    //Remove head (and thereby dissolve household) only if there are no other members
    function remove_household_head($hh_id){
      if (sizeof($this->get_household_members($hh_id))==0){
        return $this->d->query("DELETE FROM cd_households WHERE id=$hh_id");
      }
      return false;      
    }
       
    //Add household record with head $person_id only if $person_id is not member or head of another household
    function add_household($person_id){
      if (!$this->person_is_household_head_or_member($person_id)){      
        //Create new household record
        $e=array();
        $e["head_person_id"]=$person_id;
        return $this->d->insert_and_get_id($e,"cd_households");
      }
      return false;      
    }
    
    //Assign person to household only if person is neither head of a household nor member of another household, and if household exists
    function assign_person_to_household($person_id,$hh_id){
      if ((!$this->person_is_household_head_or_member($person_id)) && ($this->household_exists($hh_id))){
        //Create new household membership record
        $e=array();
        $e["person_id"]=$person_id;
        $e["household_id"]=$hh_id;
        return $this->d->insert_and_get_id($e,"cd_household_memberships");
      } 
      return false;     
    }
    
    //Delete household membership if it exists
    function unassign_person_from_household($person_id,$hh_id){
      if ($this->get_household_id_for_person($person_id)==$hh_id){
        return ($this->query("DELETE FROM cd_household_memberships WHERE person_id=$person_id AND household_id=$hh_id"));
      }
      return true;
    }
    
    
    
    /* People to addresses */
    
    private function mark_addresses_non_primary_for_person($person_id){
      return ($res=$this->d->update_record("cd_people_to_addresses","person_id",$person_id,array("is_primary"=>"0")));    
    }
    
    private function mark_address_primary_for_person($person_id,$address_id){
      return ($res=$this->d->query("UPDATE cd_people_to_addresses SET is_primary=1 WHERE person_id=$person_id AND address_id=$address_id"));
    }
    
    private function get_primary_address_id($person_id){
      if ($res=$this->d->query("SELECT address_id FROM cd_people_to_addresses WHERE person_id=$person_id AND is_primary!=0")){
        if ($r=$res->fetch_assoc()){
          return $r["address_id"];
        }      
      }
      return false;
    }
       
    private function get_link_id_to_primary_address($person_id){
      if ($res=$this->d->query("SELECT id FROM cd_people_to_addresses WHERE person_id=$person_id AND is_primary!=0")){
        if ($r=$res->fetch_assoc()){
          return $r["id"];
        }      
      }
      return false;    
    }
    
    //Return a record from table cd_people_to_addresses
    function get_address_link($id){
      return $this->d->get_record("cd_people_to_addresses","id",$id);   
    }
    
    function address_link_exists($id){
      return ($this->get_address_link($id)>0);       
    }
    
    //Also updates the assignment in case it exists already
    function assign_address_to_person($person_id,$address_id,$apt_no="",$home_phone="",$is_primary=true){
      //Check if address exists, whether the link already exists, and whether another primary address exists (in which case that is to be marked non primary)
      if ($this->d->record_exists("cd_addresses","id",$address_id)){
        //Address exists.
        /*
          If this link is primary, unmark potientially existing primary address.
          If it's not primary, check if a primary address exists. If not, overwrite non-primary.
        */
        if ($is_primary){
          $this->mark_addresses_non_primary_for_person($person_id);
        } else {
          if (!$this->get_primary_address_id($person_id)){
            $is_primary=true;
          }
        }        
        //Does the link exist already?
        if ($res=$this->d->query("SELECT * FROM cd_people_to_addresses WHERE person_id=$person_id AND address_id=$address_id")){
          if ($e=$res->fetch_assoc()){
            //Link exists, update 
            $e["apt_no"]=$apt_no;      
            $e["home_phone"]=$home_phone;      
            $e["is_primary"]=$is_primary;
            $e["modified_at"]=time();                
            return ($this->d->update_record("cd_people_to_addresses","id",$e["id"],$e));            
          }
        }
        //Link probably didn't exist, create
        $e=array();
        $e["person_id"]=$person_id;
        $e["address_id"]=$address_id;
        $e["apt_no"]=$apt_no;      
        $e["home_phone"]=$home_phone;      
        $e["is_primary"]=$is_primary;
        $e["modified_at"]=time();
        return ($this->d->insert($e,"cd_people_to_addresses"));                          
      }
    }
    
    function unassign_address_from_person($person_id,$address_id){
      if ($res=$this->d->query("DELETE FROM cd_people_to_addresses WHERE person_id=$person_id AND address_id=$address_id")){
        //Deleted this link. If the address is now out of use AND not tagged, delete it also
        if ((!$this->address_in_use($address_id)) && (!$this->address_is_tagged($address_id))){
          $this->delete_address($address_id);
        }
        //We also need to make sure there's a primary address still for $person_id
        if ($res=$this->d->query("SELECT * FROM cd_people_to_addresses WHERE person_id=$person_id AND is_primary!=0")){
          if (!($r=$res->fetch_assoc())){
            //No primary address exists, so mark the first extant address for this person primary
            if ($res=$this->d->query("SELECT * FROM cd_people_to_addresses WHERE person_id=$person_id")){
              if ($r=$res->fetch_assoc()){
                //Found an address, now mark it primary
                $this->mark_address_primary_for_person($person_id,$r["address_id"]);                
              }
            }                  
          }
        }
        return true;      
      }
      return false;
    }
    
    //Retrieve full address record (ie. with home_phone and apt_no) via a record id from people_to_addresses
    //The record with that ID tells us address, apt & home phone (also person, but that's not relevant here)
    function get_full_address($people_to_addresses_id){
      if ($r=$this->d->get_record("cd_people_to_addresses","id",$people_to_addresses_id)){
        //Got link record, now obtain address
        if ($a=$this->d->get_record("cd_addresses","id",$r["address_id"])){
          //$a has now all address fields except apt_no and home_phone, is_primary, and modified_at. Add manually.
          $a["apt_no"]=$r["apt_no"];
          $a["home_phone"]=$r["home_phone"];
          $a["is_primary"]=$r["is_primary"];
          $a["modified_at"]=$r["modified_at"];          
          return $a;
        }//else address id must have been invalid      
      }
      return false;    
    }
    
    //Retrieve full address record (ie. with home_phone and apt_no) of primary address via $person_id
    function get_full_primary_address_for_person($person_id){
      if ($address_link=$this->get_link_id_to_primary_address($person_id)){
        return $this->get_full_address($address_link);      
      }
      return array();
    }
   
    //Return an array of people_to_addresses record ids
    function get_address_links_for_person($person_id){
      $t=array();
      if ($res=$this->d->query("SELECT id FROM cd_people_to_addresses WHERE person_id=$person_id ORDER BY is_primary DESC")){
        while($r=$res->fetch_assoc()){
          $t[]=$r["id"];
        }
      }
      return $t;
    }
    
    //Return all full addresses for person (with apt_no and home_phone). The TOP one is primary. Address link is the array key.
    function get_all_full_addresses_for_person($person_id){
      $t=array();
      $x=$this->get_address_links_for_person($person_id);
      foreach($x as $v){
        $t[$v]=$this->get_full_address($v);
      }
      return $t;
    } 

    //Returns true if the given address_id shows up in $x or more of the links
    function address_in_use($address_id,$x=1){
      if ($res=$this->d->select("cd_people_to_addresses","*","address_id=$address_id")){
        if ($res->num_rows>=$x){
          return true;
        }
      }  
      return false;  
    }

    /* Addresses and people */
    
    //Add a full address record for a person. Create both the address and the link.
    function add_full_address_for_person($person_id,   $street_address,$city="",$zip_code="",$province="",$country="",   $apt_no,$home_phone,$is_primary=true){
      /*
        Pseudo Code:
        - add_address (create address record)
        - assign_address_to_person (create link with homephone, apt_no, is_primary)
      */
      if ($n=$this->add_address($street_address,$city,$zip_code,$province,$country)){
        //Address created, has id n. Now create link.
        return ($this->assign_address_to_person($person_id,$n,$apt_no,$home_phone,$is_primary));
      }
      return false;      
    }
    
    //Update the associated address record and address link record
    //However, if address is tagged or otherwise used, create new address record and adjust the link record to point to it instead.
    function update_full_address_for_person($person_id,$address_link_id,   $street_address,$city,$zip_code,$province,$country,   $apt_no,$home_phone,$is_primary){
      /*
        Pseudo Code:
        - if link with id $address_link_id exists (in cd_people_to_addresses):
            - if address record is NOT tagged AND not in use by anybody else:
              - update address record with the id found in through $address_link_id
            ELSE
              - create NEW address record
            - update link record ($address_link_id) 
      */
      if ($n=$this->d->get_record("cd_people_to_addresses","id",$address_link_id)){
        //Retrieved the link record
        if ((!$this->address_in_use($n["address_id"],2)) && (!$this->address_is_tagged($n["address_id"]))){
          //Address not in use by anybody else (except the present link) nor tagged, therefore update address record (and link record as applicable, but the pair stays together)
          return (($this->update_address($n["address_id"],$street_address,$city,$zip_code,$province,$country)) && ($this->assign_address_to_person($person_id,$n["address_id"],$apt_no,$home_phone,$is_primary)));        
        } else {
          //Address is in use by someone else and/or tagged: therefore create NEW address record, and manually adjust link record before updating its contents with assign_address_to_person
          if ($new_address_id=$this->add_address($street_address,$city,$zip_code,$province,$country)){
            if ($res=$this->d->query("UPDATE cd_people_to_addresses SET address_id=$new_address_id WHERE person_id=$person_id AND address_id=".$n["address_id"])){
              return ($this->assign_address_to_person($person_id,$new_address_id,$apt_no,$home_phone,$is_primary));                  
            }
          }
        }      
      }
      return false;    
    }
    
    function delete_full_address_for_person($person_id,$address_link_id){
      /*
        Pseudo Code:
        -if person_id and address_link_id are valid and belong together:
          Get record from people_to_addresses (with address_link_id)
          Delete link
          Delete linked address IF IT'S NOT TAGGED AND NOT LINKED TO SOMEONE ELSE
      */
      if ($r=$this->get_address_link($address_link_id)){
        //Address link exists
        if ($r["person_id"]==$person_id){
          //Address link belongs to $person_id
          if ($this->person_exists($person_id)){
            //Person in question exists
            //Now delete link
            $this->unassign_address_from_person($person_id,$r["address_id"]); //Use this fn because it will ensure there's a primary address if one is left after deletion
            //Now make sure that address is NOT tagged and NOT linked to somebody else
            if ((!$this->address_in_use($r["address_id"])) && (!$this->address_is_tagged($r["address_id"]))){
              return $this->delete_address($r["address_id"]);  
            }                 
          }        
        }
      }
      return false;      
    } 
   
    //If $type_names is empty, return ALL options we have
    function get_contact_options_for_person($person_id,$type_names=array()){
      $t=array();
      if ($res=$this->d->query("SELECT type,value FROM cd_contact_options WHERE person_id=$person_id")){
        while ($r=$res->fetch_assoc()){
          if ((sizeof($type_names)==0) || (in_array($r["type"],$type_names))){
            $t[$r["type"]]=$r["value"];
          }
        }
      }
      return $t;    
    }
   
    //Return personal email, or, if not extant, work email
    function get_first_email_address_for_person($person_id){
      $t=array();
      $s=$this->get_contact_options_for_person($person_id);
      if (isset($s["personal email"])){
        $t["personal email"]=$s["personal email"];
      } elseif (isset($s["work email"])){
        $t["work email"]=$s["work email"];
      }
      return $t; //No email found      
    }    

    /////////////////////////////////////////// UTILITIES
   
    //Utility function
    //Expects country, area, number, extension, returns +c (a) n ext. e, or false
    function phone_nr_to_str($c,$a,$n,$e){
      //Only do anything if # was actually given
      if($n!=""){
        $ext_str="";
        //Extension given?
        if ($e!=""){
          $ext_str=" ext. $e";      
        }
        //7-digit number without dash?
        if ((strlen($n)==7) && ($n>0)){
          $n=substr($n,0,3)."-".substr($n,3);
        }
        return "+$c ($a) $n$ext_str";                
      }
      return false;
    }

    //Utility to returns country,area,number,ext as array
    //Expects space-separated parts 
    function explode_phone_number($t){
      $r=explode(" ",$t);
      //Remove element 'ext.' if present         
      if ($x=array_search('ext.',$r)){
        unset($r[$x]); 
      }
      //Remove + from country code
      $r[0]=substr($r[0],1);
      //Remove brackets from area code
      $r[1]=substr(substr($r[1],1),0,-1);
      //Need to make new array because of potentially missing index
      $q=array();
      foreach($r as $v){
        $q[]=$v;
      } 
      return $q;
    }
    
}

?>