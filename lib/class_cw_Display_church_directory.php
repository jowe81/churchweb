<?php

  class cw_Display_church_directory {
  
    public $auth; //Auth (passed in), church_directory and personal_records (created by constructor)
    private $d; //Database access
    
    function __construct($auth){
      $this->d=$auth->d; //Database access
      $this->auth=$auth; //cw_Auth
    }
  
    //Return a number of personal records just from cd_people
    //$filter array elements must be keyed according to people table
    function get_personal_records_with_full_primary_address_and_contact_options($filter=array()){
      $t=$this->auth->personal_records->get_personal_records($filter); //Obtain records from cd_people first
      //Iterate over each personal record and get full address or it
      foreach ($t as $k=>$v){
        //Merge this record in $t (the person record) with the appropriate primary address, and replace
        $u=array_merge($this->auth->church_directory->get_full_primary_address_for_person($k),$v); //Merge personal record with primary address
        $u["contact_options"]=$this->auth->church_directory->get_contact_options_for_person($k);
        $t[$k]=$u;
              
      }
      return $t;      
    }

    //Get an array with privacy preferences for the user $person_id
    private function read_privacy_prefs($person_id){
        $upref=new cw_User_preferences($this->d,$person_id);
        return $upref->read_multiple_prefs(23,"show%"); //23 is service id for ajax_privacy_settings    
    }

    
    //Output filtered table - all individual records (should be wrapped in div id='cd_list' by calling script)
    //If $restricted is set, only those data that have been approved for public display in each users privacy settings will show 
    function display_list($filter=array(),$restricted=true){
      
      $r=$this->get_personal_records_with_full_primary_address_and_contact_options($filter); //If person is member of another household, address and home-phone applies from that
      /*
        $r[person_id] has all these fields now:
        id,street_address,city,zip_code,province,country,tag,apt_no,home_phone,is_primray
        last_name,first_name,middle_names,maiden_name,gender,birthday,birthday_day,birthday_month,birthday_year,status,public_designation,deceased,notes,active
        contact_options[array of type->value]
        
      */

      $cnt=0; //counter for actually displayed entries
      //By default, set all privacy flags to 'show' (for full view).
      $ps=array();      
      $ps["show_lastname_firstname"]=true;
      $ps["show_primary_homephone"]=true;
      $ps["show_primary_postal"]=true;
      $ps["show_secondary_postal"]=true;
      $ps["show_middle_names"]=true;
      $ps["show_maiden_name"]=true;
      $ps["show_birthday"]=true;      
      foreach ($r as $k=>$v){
        //$k is person id
        //Get privacy settings
        if ($restricted){
          //If $restricted, overwrite privacy flags with actual preferences
          $ps=$this->read_privacy_prefs($k);
        }
        if ($ps["show_lastname_firstname"]){
          $cnt++;
          //Prepare address info
          $address="";
          if ($ps["show_primary_postal"]){
            if ($v["street_address"]!=""){
              if ($v["apt_no"]!=""){
                $address.=$v["apt_no"]."-";
              }
              $address.=$v["street_address"]."<br/>";        
            }
            //Use city, province, zip if either or all are given 
            if (($v["city"]!="") || ($v["province"]!="") || ($v["zip_code"]!="")){
              $address.=$v["city"]." ".$v["province"]." ".$v["zip_code"]."<br/>";        
            }
            //Use country if given and != CW_CD_DEFAULT_COUNTRY
            if (($v["country"]!="") && ($v["country"]!=CW_CD_DEFAULT_COUNTRY)){
              $address.=$v["country"]."<br/>";        
            }
          }
          
          //Prepare home phone and other contact options:
          if ($ps["show_primary_homephone"]){
            if ($v["home_phone"]!=""){
              $address.="<img src='".CW_ROOT_WEB."img/phone.gif'/> ".$v["home_phone"];
            }
          }
          
          //Prepare contact options
          $c_options="";
          foreach ($v["contact_options"] as $l=>$w){
            if ($ps["show_option_".str_replace(" ","_",$l)] || (!$restricted)){
              $img="";
              if (($l==CW_CD_PERSONAL_EMAIL_CONTACT_OPTION_TYPE) || ($l==CW_CD_WORK_EMAIL_CONTACT_OPTION_TYPE)){
                $img="<img src='".CW_ROOT_WEB."img/email.gif'/>";
              } elseif (($l==CW_CD_PERSONAL_CELL_CONTACT_OPTION_TYPE) || ($l==CW_CD_WORK_CELL_CONTACT_OPTION_TYPE)){
                $img="<img src='".CW_ROOT_WEB."img/cell.gif'/>";              
              } elseif ($l==CW_CD_FAX_CONTACT_OPTION_TYPE){
                $img="<img src='".CW_ROOT_WEB."img/fax.gif'/>";                            
              } elseif ($l==CW_CD_WEBSITE_CONTACT_OPTION_TYPE){
                $img="<img src='".CW_ROOT_WEB."img/web.gif'/>";                                          
              } elseif ($l==CW_CD_ICQ_CONTACT_OPTION_TYPE){
                $img="<img src='".CW_ROOT_WEB."img/icq.gif'/>";                                          
              }
              $c_options.="<div class='cd_contact_options_wrap_div'><div class='cd_contact_options_key_div'>".$l.":</div><div class='cd_contact_options_value_div'>$img ".$w."</div></div>";
            }        
          }
          
          $middle_names="";
          if ($ps["show_middle_names"]){
            $middle_names=" ".$v["middle_names"];          
          }          
          
          //Maiden name
          $maiden="";
          if ($ps["show_maiden_name"]){
            if (($v["maiden_name"]!="") && ($v["maiden_name"]!=$v["last_name"])){
              $maiden=" (".$v["maiden_name"].")";
            }
          } 
          //Markup odd or even
          $x++;
          $class="even";
          if (($x%2)>0){
            $class="odd";        
          }
  
          //Birthday present?
          $bday="";
          if (($v["birthday"]!=-1) && ($ps["show_birthday"])){
            $bday=date("d",$v["birthday"])."/".date("m",$v["birthday"])."/".date("Y",$v["birthday"]);
            if ($v["deceased"]==-1){
              $bday.=" <span style='font-size:70%;'>(age ".get_age_in_years($v["birthday"]).")</span>";
            }
          }
          
          //If not deceased, show today's age:
          if ($v["deceased"]!=-1){
            if ($bday!=""){
              $bday.="<br/>";
            }
            $bday.="&dagger;".timestamp_to_ddmmyyyy($v["deceased"]);
            if ($v["birthday"]!=-1){
              $bday.=" (died age ".get_age_in_years($v["birthday"],$v["deceased"]).")";
            }
          }
          
          //Public Designation
          $pd="";
          if ($v["public_designation"]!=""){
            $pd=$v["public_designation"]."<br/>";
          }
          
                  
          $t.="<tr class='$class'>
              <td><span style='font-weight:bold'>".$v["last_name"]."</span>$maiden, <span style='font-weight:bold'>".$v["first_name"].$middle_names."</span><br/>$pd$bday</td>
              <td>".$address."</td>
              <td>".$c_options."</td>
              <td class='status'>".$v["status"]."</td>
              </tr>            
            ";
        
        }                
      }
      
      $word="people";
      if ($cnt==1){
        $word="person";
      }
            
      $t="<table id='cd_ind_view_table'>
        <tr>
          <th style='width:30%;'></th>
          <th style='width:30%;'></th>
          <th style='width:30%;'></th>
          <th style='width:10%;text-align:right;'>".$cnt." $word found</th>          
        </tr>
        $t
        </table>
      ";
      return $t;
    }
  


  }

?>