<?php

  require_once "../lib/framework.php";
  
  $prd = new cw_Display_personal_records($d,$a);  

 if ($_GET["action"]=="display_edit_dialogue") {
    echo $prd->display_edit_dialogue($a->cuid,true);
  } elseif ($_GET["action"]=="save_address"){
    //Save a newly typed address
    //Trim potential whitespace around each submitted value
    foreach ($_POST as $k=>$v){
      $_POST[$k]=trim($v);
    }
    //Put phone# together 
    $home_ph=cw_Church_directory::phone_nr_to_str($_POST["home_ph_c"],$_POST["home_ph_a"],$_POST["home_ph_n"],$_POST["home_ph_e"]);    
    if ($_GET["type"]=="saved"){
      //Add saved address to the person
      if ($a->church_directory->assign_address_to_person($_POST["person_id"],$_POST["saved_address_id"],$_POST["apt_no"],$home_ph,($_POST["primary"]!=""))){
        echo "OK";
      } else {
        echo "Unable to save address (while trying to use previously saved address record)";      
      }
    } else {
      //Add new address
      if ($a->church_directory->add_full_address_for_person($_POST["person_id"],$_POST["street_address"],$_POST["city"],$_POST["zip_code"],$_POST["province"],$_POST["country"],$_POST["apt_no"],$home_ph,($_POST["primary"]!=""))){
        echo "OK";
      } else {
        echo "Unable to save address";
      }      
    }
  } elseif ($_GET["action"]=="get_default_home_phone_for_tagged_address"){
    if ($_GET["id"]>0){
      //Get the address in question
      $r=$a->church_directory->get_address_record($_GET["id"]);
      $parts=cw_Church_directory::explode_phone_number($r["default_home_phone"]);      
      //Output array as json (in utilities_misc.php)    
      echo one_dim_array_to_json($parts);
    }
  } elseif ($_GET["action"]=="get_addresses_on_file"){
    //Get addresses on file
    $addresses_on_file=$a->church_directory->get_all_full_addresses_for_person($_GET["person_id"]);
    $aof="";
    $no=0;
    foreach ($addresses_on_file as $k=>$v){
      //Format address in div
      $background="";
      //No==0 means this is the primary address
      $add_class="";
      $primary_title="secondary ";
      if ($no==0){
        $add_class="primary_addr";
        $primary_title="primary ";
      }
      
      if ((time()-$v["modified_at"])<20){
        $saved_at="saved just now";
      } else {
        $saved_at="saved ".getHumanReadableLengthOfTime(time()-$v["modified_at"])." ago";
      }
      
      $aof.="<div class='addr_div $add_class' id='addr".$k."'>";
      //Link to delete address
      $aof.="<div class='delete_addr'><div>$primary_title postal address</div>$saved_at [<a id='deladdr$k'>delete</a>]</div>";
      if ($v["street_address"]!=""){
        if ($v["apt_no"]!=""){
          $aof.=$v["apt_no"]."-";
        }        
        $aof.=$v["street_address"]."<br/>";
      }
      if ($v["city"]!=""){
        if ($v["zip_code"]!=""){
          $aof.=$v["city"].", ".$v["zip_code"]."<br/>";
        } else {
          $aof.=$v["city"]."<br/>";        
        }
      } else {
        if ($v["zip_code"]!=""){
          $aof.=$v["zip_code"]."<br/>";                      
        }
      }
      if ($v["province"]!=""){
        if ($v["country"]!=""){
          $aof.=$v["province"].", ".$v["country"]."<br/>";
        } else {
          $aof.=$v["province"]."<br/>";        
        }        
      } else {
        if ($v["country"]!=""){
          $aof.=$v["country"]."<br/>";
        }                
      }
      if ($v["home_phone"]!=""){
          $aof.="".$v["home_phone"]."<br/>";      
      }
      $aof.="</div>";
      $no++; //Counter only used to identify primary address - which is the first
    }
    $script="<script type='text/javascript'>";
    foreach ($addresses_on_file as $k=>$v){
      $script.="
        $('#deladdr$k').click(function(){
          if (confirm('Are you sure you want to delete this address?')){
          
            $.post(
              '".CW_AJAX."ajax_personal_information.php?action=delete_address&person_id=".$_GET["person_id"]."&address_id=".$v["id"]."',
              {},
              function(rtn){
                if(rtn!='OK'){
                  alert(rtn);
                } else {
                  get_addresses_on_file();
                }              
              }
            );
          
          
          }          
        });
      ";
    }
    $script.="</script>";
    echo $aof.$script;
  } elseif ($_GET["action"]=="delete_address"){
      if ($a->church_directory->unassign_address_from_person($_GET["person_id"],$_GET["address_id"])){
        echo "OK";
      } else {
        echo "Could not delete address (person_id: ".$_GET["person_id"].", address_id: ".$_GET["address_id"].")";
      }
  } elseif ($_GET["action"]=="get_contact_options"){
    //Get contact options for full list
    $options=$a->church_directory->get_contact_options_for_person($_GET["person_id"]);
    //Get default email contact option type for cw comm
    $default_email=$a->users->get_contact_option_type_for_cw_comm($_GET["person_id"]); 
    //$icon_codes=$a->church_directory->get_contact_option_icon_codes();
    $t="";
    foreach ($options as $k=>$v){
      //Get full record to find out when this option was saved
      $full_record=$a->church_directory->get_contact_option($_GET["person_id"],$k);
      $class='';
      $make_default_link="";
      if ($default_email==$k){
        $class=' primary_addr';
        $make_default_link='';
      } else {
        //If this is any email address, offer 'Set default'
        if (strpos($k,"email")!==false){
          $make_default_link=" [<a id='def".hash("md5",$k)."'>set default</a>]";        
        }
      }
      if ((time()-$full_record["modified_at"])<20){
        $saved_at="saved just now";
      } else {
        $saved_at="saved ".getHumanReadableLengthOfTime(time()-$full_record["modified_at"])." ago";
      }
      $t.="
        <div class='addr_div$class'>
          <div class='delete_addr'><div style='float:left;'>$k</div>$saved_at [<a id='opt".hash("md5",$k)."'>delete</a>]$make_default_link</div> 
          $v
        </div>
      ";
    }

    $script="<script type='text/javascript'>";
    foreach ($options as $k=>$v){
      $script.="
        $('#opt".hash("md5",$k)."').click(function(){
          if (confirm('Are you sure you want to delete this contact option?')){
          
            $.post(
              '".CW_AJAX."ajax_personal_information.php?action=delete_contact_option&person_id=".$_GET["person_id"]."&contact_option_type=$k',
              {},
              function(rtn){
                if(rtn!='OK'){
                  alert(rtn);
                } else {
                  get_contact_options_on_file();
                }              
              }
            );
          
          }          
        });
        
        $('#def".hash("md5",$k)."').click(function(){          
          $.post(
            '".CW_AJAX."ajax_personal_information.php?action=contact_option_set_default&person_id=".$_GET["person_id"]."&contact_option_type=$k',
            {},
            function(rtn){
              alert(rtn)
              get_contact_options_on_file();
            }
          );          
        });        
      ";
    }
    $script.="</script>";

    echo $t.$script;
  } elseif ($_GET["action"]=="contact_option_set_default"){
      if ($a->users->set_contact_option_type_for_cw_comm($_GET["person_id"],$_GET["contact_option_type"])){
        echo "Your ".$_GET["contact_option_type"]." has been set as default email for ChurchWeb communication.";
      } else {
        echo "Could not change default email address.";
      }
    
  } elseif ($_GET["action"]=="get_contact_options_select_numbers"){
    //Options for selectbox (the types - phone numbers only)
    $t="<option value=''>(select...)</option>";
    $types=$a->church_directory->get_contact_option_types_numbers();
    foreach ($types as $v){
      $t.="<option value='".$v["type_name"]."'>".$v["type_name"]."</option>";      
    }
    echo $t;    
  } elseif ($_GET["action"]=="get_contact_options_select_other"){
    //Options for selectbox (the types - non-phone numbers only)
    $t="<option value=''>(select...)</option>";
    $types=$a->church_directory->get_contact_option_types_other();
    foreach ($types as $v){
      $t.="<option value='".$v["type_name"]."'>".$v["type_name"]."</option>";      
    }
    echo $t;    
  } elseif ($_GET["action"]=="save_contact_option"){
    //
    if ($_POST["contact_option_type"]!=""){
      if ($_GET["type"]=="number"){
        //Store a phone number
        //Put phone# together 
        if ($ph=cw_Church_directory::phone_nr_to_str($_POST["ph_c"],$_POST["ph_a"],$_POST["ph_n"],$_POST["ph_e"])){
          //Store #
          if ($a->church_directory->add_contact_option($_POST["person_id"],$_POST["contact_option_type"],$ph)){
            echo "OK";        
          } else {
            echo "Number could not be stored: database problem";                          
          }
        } else {
          //Problem with number
          echo "Number could not be stored. You probably did not provide a valid phone number";                  
        }
      } else {
        //Store value for other type
        if ($_POST["contact_option_value"]!=""){
          if ($a->church_directory->add_contact_option($_POST["person_id"],$_POST["contact_option_type"],$_POST["contact_option_value"])){
            //If this was an email address, and there is now only one email address (i.e. it was the first provided email), mark it as default for CW communication
            if ($a->church_directory->contact_option_type_is_email_address($_POST["contact_option_type"])){
              //It was an email address
              if ($a->church_directory->get_number_of_email_addresses_for_person($_POST["person_id"])==1){
                //It was the first email address -> set default
                $a->users->set_contact_option_type_for_cw_comm($_POST["person_id"],$_POST["contact_option_type"]);
              }
            }
            echo "OK";        
          } else {
            echo "Contact option could not be stored: database problem";                          
          }      
        } else {
          echo "Could not store contact option: you did not enter a value for option '".$_POST["contact_option_type"]."'";
        }
      }
    } else {
      echo "Could not store contact option: you must select a contact option type (e.g. personal cell, work email etc)";
    }
  } elseif ($_GET["action"]=="get_number"){
    //Get a number, return as JSON
    if ($x=$a->church_directory->get_contact_option($_GET["person_id"],$_POST["contact_option_type"])){
      //$x["value"] has number as string - now make array
      $ar=cw_Church_directory::explode_phone_number($x["value"]);    
      //Output array as json (in utilities_misc.php)    
      $result=one_dim_array_to_json($ar);
      echo $result;
    }
  } elseif ($_GET["action"]=="get_other"){
    //Get value for a specific contact option
    $x=$a->church_directory->get_contact_option($_GET["person_id"],$_POST["contact_option_type"]);
    echo $x["value"];
  } elseif ($_GET["action"]=="delete_contact_option"){
    //Delete an option for user
    if ($a->church_directory->delete_contact_option($_GET["person_id"],$_GET["contact_option_type"])){
      //If this was an email address, and there is now only one email address, mark that remaining address as default for CW communication
      if ($a->church_directory->contact_option_type_is_email_address($_GET["contact_option_type"])){
        //It was an email address
        if ($a->church_directory->get_number_of_email_addresses_for_person($_GET["person_id"])==1){
          //It was the first email address -> set default
          $first_email=$a->church_directory->get_first_email_address_for_person($_GET["person_id"]);//returns array with type_name as key and email address as value
          $tmp=array_keys($first_email);
          $type_name=$tmp[0];
          $a->users->set_contact_option_type_for_cw_comm($_GET["person_id"],$type_name);
        }
      }
      echo "OK";
    } else {
      echo "Could not delete contact option '".$_GET["contact_option_type"]."'";
    }
  }
    
  $p->nodisplay=true;
?>