<?php

  require_once "../lib/framework.php";

  //Load styles
  //$p->stylesheet(CW_ROOT_WEB."css/personal_records.css");

  $person=$a->personal_records->get_person_record($a->cuid);
  $middle_names=$person["middle_names"];
  if ($middle_names==""){
    $middle_names="(not provided)";
  }
  $maiden_name=$person["maiden_name"];
  if ($maiden_name==""){
    $maiden_name="(not provided)";
  }
  $birthday="";
  if ($person["birthday"]!=-1){
    $birthday=date("F j, Y",$person["birthday"]);
  }
  if ($birthday==""){
    $birthday="(not provided)";
  }

  $p_postal="(not provided)";
  if ($primary_address=$a->church_directory->get_full_primary_address_for_person($a->cuid)){
    if (is_array($primary_address)){
      $p_postal="(present";    
      if ($primary_address["city"]!=""){
        $p_postal.=" - in ".$primary_address["city"];          
      }
      if ($primary_address["province"]!=""){
        $p_postal.=", ".$primary_address["province"];          
      }
      if ($primary_address["country"]!=""){
        $p_postal.=", ".$primary_address["country"];          
      }
      $p_postal.=")";
    }
  }

  $p_homephone="(not provided)";
  if ($primary_address["home_phone"]!=""){
    $t=cw_Church_directory::explode_phone_number($primary_address["home_phone"]);
    $p_homephone=cw_Church_directory::phone_nr_to_str($t[0],$t[1],$t[2],$t[3]);
  }

  $all_addresses=$a->church_directory->get_all_full_addresses_for_person($a->cuid);
  $s_postal="(not provided)";
  if (sizeOf($all_addresses)>2){
    $s_postal="(several present)";    
  } elseif (sizeOf($all_addresses)==2){
    reset($all_addresses);
    $s_address=next($all_addresses); //First secondary address
    $s_postal="(present";    
    if ($s_address["city"]!=""){
      $s_postal.=" - in ".$s_address["city"];          
    }
    if ($s_address["province"]!=""){
      $s_postal.=", ".$s_address["province"];          
    }
    if ($primary_address["country"]!=""){
      $s_postal.=", ".$s_address["country"];          
    }
    $s_postal.=")";  
  }

  $options=$a->church_directory->get_contact_options_for_person($a->cuid);
  $c="";
  $optionsjq="";
  foreach ($options as $k=>$v){
    $c.="<tr><td style='width:300px;'><input id='option_".str_replace(" ","_",$k)."' type='checkbox' /> ".$k."</td><td><span class='small_note gray'>$v</span></td></tr>";
    $optionsjq.="arm_checkbox('option_".str_replace(" ","_",$k)."');";
  }
  if ($c==""){
    $c="<span class='small_note gray'>(You have not provided any contact options)</span>";
  }
   
   
   
  $p->p("
    <div id='' style='margin:10px auto;overflow-y:auto;height:500px;width:1090px;left:105px;top:70px;'>
      <div id='tabs'>
        <ul>
          <li><a href='#t1'>Church directory</a></li>          
        </ul>
        <div id='t1'>
          <div class='expl'>
            <p>This page allows you to control which of your personal information will appear in the church directory.</p>
            <p>Note that the church directory can be accessed only by other people of ".CW_CHURCH_NAME.".</p>
            <p>Note also that the personal data and contact information you do provide will be accessible to office and pastoral staff.</p> 
          </div>
          <h4>Fields to appear in the church directory</h4>
          <div id='accordion' style='width:700px;'>
            <h4><a href='#'><span style='text-decoration:underline;'>Personal info</span></a></h4>
            <div>
              <div style='height:230px;'>
                <table>
                  <tr><td style='width:300px;'><input id='lastname_firstname' type='checkbox' /> Last name and first name</td><td><span class='small_note gray'>".$person["last_name"].", ".$person["first_name"]."</span></td></tr>
                  <tr><td><input id='middle_names' type='checkbox' /> Middle name(s)</td><td><span class='small_note gray'>$middle_names</span></td></tr>
                  <tr><td><input id='maiden_name' type='checkbox' /> Maiden name</td><td><span class='small_note gray'>$maiden_name</span></td></tr>
                  <tr><td><input id='birthday' type='checkbox' /> Birthday</td><td><span class='small_note gray'>$birthday</span></td></tr>
                </table>
              </div>
            </div>
            <h4><a href='#'><span style='text-decoration:underline;'>Address and home phone</span></a></h4>
            <div>
              <div style='height:230px;'>
                <table>
                  <tr><td style='width:300px;'><input id='primary_postal' type='checkbox' /> Primary postal address</td><td><span class='small_note gray'>$p_postal</span></td></tr>
                  <tr><td><input id='primary_homephone' type='checkbox' /> Primary home phone</td><td><span class='small_note gray'>$p_homephone</span></td></tr>
                </table>
              </div>
            </div>
            <h4><a href='#'><span style='text-decoration:underline;'>Contact options</span></a></h4>
            <div>
              <div style='height:230px;'>
                <table>
                  $c
                </table>
              </div>
            </div>
          </div>
        </div>      
      </div>
    
    </div>
  
  ");
    
  $p->jquery("
    $('#tabs').tabs();
    $('#accordion').accordion();
    
    function check(id,value){
      if (value=='CHECKED'){
        $('#'+id).attr('checked','CHECKED');      
      } else {
        $('#'+id).removeAttr('checked');            
      }
    }

    function check_warning(id,rtn){
      if ( (id=='lastname_firstname') && (rtn!='CHECKED') ){
        alert('Notice: you deactivated the field for your last name and first name. This means you are no longer listed in the church directory.')
      }
    }

    function arm_checkbox(id){
      //Read initial value
      $.get('".CW_AJAX."ajax_privacy_settings.php?action=read&dom_id='+id,function(rtn){
        check(id,rtn);
      });
      //Assign change event
      $('#'+id).change(function(){
        $.get('".CW_AJAX."ajax_privacy_settings.php?action=toggle&dom_id='+id,function(rtn){
          check(id,rtn);
          check_warning(id,rtn);
        });
      });    
    }    

    arm_checkbox('lastname_firstname');
    arm_checkbox('middle_names');
    arm_checkbox('birthday');
    arm_checkbox('primary_postal');
    arm_checkbox('primary_homephone');
    arm_checkbox('maiden_name');
    $optionsjq
    
  ");


?>