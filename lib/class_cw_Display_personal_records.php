<?php

  class cw_Display_personal_records {
  
    public $auth; //passed in
    private $d; //Database access
    
    function __construct($d,$auth){
      $this->d=$d; //Database access
      $this->auth=$auth;
    }

    //Output JS for editing in modal window
    function output_js(){
      $t="
        <script type='text/javascript'>
          //This will be called by the links from the table and edit links
          //epr = edit_personal_record (short name to save data traffic)
          function epr(id,focus){
              if (!focus){
                var focus='last_name';
              }
              $('#main_content').fadeTo(500,0.3);
              $('#modal').load('".CW_AJAX."ajax_personal_records.php?action=display_edit_dialogue&id=' +id,function(){
                $('#'+focus).focus();
              });
              $('#modal').css('width','1050px');
              $('#modal').css('left','105px');
              $('#modal').css('top','55px');
              $('#modal').fadeIn(200);                        
          }
        </script>      
      ";
      return $t;
    }
  
    //Output filtered table - all individual records (should be wrapped in div id='cd_list' by calling script) 
    function display_list($filter=array()){
      $t=$this->output_js();
      
      $r=$this->auth->personal_records->get_personal_records($filter); 
      /*
        $r[person_id] has all these fields now:
        last_name,first_name,middle_names,maiden_name,gender,birthday,birthday_day,birthday_month,birthday_year,status,public_designation,deceased,notes,active        
      */

      $n=sizeof($r);
      $word="people";
      if ($n==1){
        $word="person";
      }
      $t.="<table id='cd_ind_view_table'>";
      $t.="
        <tr>
          <th>Name</th>
          <th style='width:120px;'>Birthday</th>
          <th style='width:180px;'></th>
          <th style='width:120px;'>Status</th>
          <th style='width:340px;text-align:right;'>".sizeof($r)." $word found&nbsp;</th>
        </tr>
          
      ";
      foreach ($r as $k=>$v){
        //$k is person id
        //Maiden name
        $maiden="";
        if (($v["maiden_name"]!="") && ($v["maiden_name"]!=$v["last_name"])){
          $maiden=" (".$v["maiden_name"].")";
        } 
        //Markup odd or even
        $x++;
        $class="even";
        if (($x%2)>0){
          $class="odd";        
        }

        //Birthday and death day
        $bday="";
        if ($v["birthday"]!=-1){
          $bday="*".date("d",$v["birthday"])."/".date("m",$v["birthday"])."/".date("Y",$v["birthday"]);        
        }
        $dday="";
        //If not deceased, show today's age:
        if ($v["deceased"]==-1){
          if ($v["birthday"]!=-1){
            $dday=" <span class='small_note'>(age ".get_age_in_years($v["birthday"]).")</span>";
          }
        } else {
          $dday="&dagger;".timestamp_to_ddmmyyyy($v["deceased"]);
          if ($v["birthday"]!=-1){
            $dday.=" <span class='small_note'>(died age ".get_age_in_years($v["birthday"],$v["deceased"]).")</span>";
          }
        }
        
        //Public Designation
        $pd="";
        if ($v["public_designation"]!=""){
          $pd=" (<span style='font-style:italic;'>".$v["public_designation"]."</span>)";
        }
        
                
        $t.="<tr id='r".$v["id"]."' class='$class'>
            <td><span style='font-weight:bold'>".$v["last_name"]."</span>$maiden, <span style='font-weight:bold'>".$v["first_name"]." ".$v["middle_names"]."</span>$pd</td>
            <td>$bday</td>
            <td>$dday</td>
            <td>".$v["status"]."</td>
            <td style='text-align:right;'><a class='blink' href='javascript:epr(".$v["id"].");'>edit&nbsp;personal&nbsp;record</a>&nbsp;</td>
            </tr>  
            <script type='text/javascript'>
              $('#r".$v["id"]."').click(function(){
                epr(".$v["id"].");
              });
            </script>          
          ";
      }
      
      $t.="</table>";
      return $t;
    }
  
    //Produce html form - $id=0 means new entry. $user_view=true means restrict for 'my information'
    function display_edit_dialogue($id,$user_view=false){
      //If called from my_personal_information, the user may not have access to ajax_personal_records. 
      $AJAX="ajax_personal_records.php";
      if ($user_view){
        $AJAX="ajax_personal_information.php";
      }
    
      if ($id>0){
        //Edit entry
        $r=$this->auth->personal_records->get_person_record($id);  
        if ($r["gender"]=="m"){
          $male_sel="SELECTED";  
        } elseif ($r["gender"]=="f") {
          $female_sel="SELECTED";        
        } 
        if ($r["status"]=="friend"){
          $friend_sel="SELECTED";
        } elseif ($r["status"]=="member"){
          $member_sel="SELECTED";
        } elseif ($r["status"]=="former member") {
          $former_member_sel="SELECTED";
        }
        //Birthday present?
        if ($r["birthday"]!=-1){
          $bday_day=date("d",$r["birthday"]);
          $bday_month=date("m",$r["birthday"]);
          $bday_year=date("Y",$r["birthday"]);
        }
        if ($r["deceased"]!=-1){
          //Person is deceased
          $dday=date("j",$r["deceased"]);
          $dmonth=date("n",$r["deceased"]);
          $dyear=date("Y",$r["deceased"]);
          $dead=" &dagger;"; //Add the dagger to the titlebar if person is deceased
        }
        $status_record=$this->auth->personal_records->get_latest_status_record($id); //Obtain the record with the latest membership status change - just need the date from here        
        $title="<div class='modal_head'>".$r["first_name"]." ".$r["middle_names"]." ".$r["last_name"]."$dead</div>";
      }
      if (!$user_view){
        $tab0=("
          <div style='width:100%;padding:5px;'>
            <h4>Personal information</h4>
            <div style='float:left;'>
              <form id='edit_personal_record'>
                <table>
                  <tr>
                    <td>Last name:</td>
                    <td><input type='text' id='last_name' name='last_name' value='".$r["last_name"]."'/></td>
                    <td></td>
                  </tr>
                  <tr>
                    <td>First name:</td>
                    <td><input type='text' id='first_name' name='first_name' value='".$r["first_name"]."'/></td>
                    <td></td>
                  </tr>
                  <tr>
                    <td>Middle name(s):</td>
                    <td><input type='text' id='middle_names' name='middle_names' value='".$r["middle_names"]."'/></td>
                    <td></td>
                  </tr>
                  <tr>
                    <td>Maiden name:</td>
                    <td><input type='text' id='maiden_name' name='first_name' value='".$r["maiden_name"]."'/></td>
                    <td></td>
                  </tr>
                  <tr>
                    <td>Gender:</td>
                    <td>
                      <select id='gender' name='gender'>
                        <option value=''>(select...)</option>
                        <option value='f' $female_sel>female</option>
                        <option value='m' $male_sel>male</option>
                      </select>
                    </td>
                    <td></td>
                  </tr>
                  <tr>
                    <td>Birthday:</td>
                    <td>
                      <input style='width:25px;' type='text' id='bday_day' name='bday_day' value='$bday_day'/>
                      <input style='width:25px;' type='text' id='bday_month' name='bday_month' value='$bday_month'/>
                      <input style='width:45px;' type='text' id='bday_year' name='bday_year' value='$bday_year'/>
                      (dd/mm/yyyy)
                    </td>
                    <td></td>
                  </tr>
                  <tr>
                    <td>Deceased:</td>
                    <td>
                      <input style='width:25px;' type='text' id='dday_day' name='dday_day' value='$dday'/>
                      <input style='width:25px;' type='text' id='dday_month' name='dday_month' value='$dmonth'/>
                      <input style='width:45px;' type='text' id='dday_year' name='dday_year' value='$dyear'/>
                      (dd/mm/yyyy)
                    </td>
                    <td><span class='expl'>If person is deceased, enter date of their death</span></td>
                  </tr>
                  <tr>
                    <td>Membership status:</td>
                    <td>
                      <select name='status' id='status'>
                        <option value=''>(select...)</option>
                        <option value='member' $member_sel>member</option>
                        <option value='friend' $friend_sel>friend (has never been a member)</option>
                        <option value='former member' $former_member_sel>former member (left or transferred)</option>
                      </select>
                    </td>
                    <td></td>
                  </tr>
                  <tr>
                    <td>Public designation:</td>
                    <td><input type='text' id='public_designation' name='public_designation' value='".$r["public_designation"]."'/></td>
                    <td><span class='expl'>e.g. 'Youth Pastor'</span></td>
                  </tr>
                  <tr>
                    <td><input type='submit' id='save' value='Save personal record'/></td>
                    <td></td>
                    <td></td>
                  </tr>
                </table>     
              </form> 
            </div>
            <div class='expl'><p>Please make sure the information on this page is accurate. For security reasons users cannot manipulate it themselves.</p></div>      
          </div>
          
          <script type='text/javascript'>
            //JS for this form
  
            //Keep the form from actually submitting (since we do it through ajax)
            $('#edit_personal_record').bind('submit', function(e) {
                e.preventDefault();
            });
                    
            //Save entry
            $('#save').click(function(){
              $.post('".CW_AJAX."ajax_personal_records.php?action=save_record', {
                              last_name:$('#last_name').val(),                            
                              first_name:$('#first_name').val(),
                              middle_names:$('#middle_names').val(),
                              maiden_name:$('#maiden_name').val(),
                              gender:$('#gender').val(),
                              bday_day:$('#bday_day').val(),
                              bday_month:$('#bday_month').val(),
                              bday_year:$('#bday_year').val(),
                              dday_day:$('#dday_day').val(),
                              dday_month:$('#dday_month').val(),
                              dday_year:$('#dday_year').val(),
                              status:$('#status').val(),
                              public_designation:$('#public_designation').val(),
                              person_id:".$r["id"]."
                              
                      },function(rtn){
                if (rtn=='OK'){                      
                  //Success. Leave modal. Refresh dest element.
                  if ( $('#modal').is(':visible') ){
                    init_dest_element();                        
                    $('#main_content').fadeTo(500,1);
                    $('#modal').hide(200);
                  } else {
                    alert('Saved record for '+ $('#first_name').val() + ' ' + $('#last_name').val());
                    //Reset form for new entry
                    $('#edit_personal_record').each(function(){
                      this.reset();
                    });
                    $('#last_name').focus();
                  }
                } else {
                  //Error during update. Show message returned by ajax
                  alert (rtn);
                }                      
              });//end post            
            });//end save
            
            $('#last_name').focus();
            
          </script>
        
        
        ");
      } else {
        //user view
        $pub_des="";
        if ($r["public_designation"]!=""){
          $pub_des="<tr><td>Public designation:</td><td>".$r["public_designation"]."</td></tr>";        
        }
        $middle_names="";
        if ($r["middle_names"]!=""){
          $middle_names="<tr><td>Middle name(s):</td><td>".$r["middle_names"]."</td></tr>";
        }
        $maiden="";
        if ($r["maiden_name"]!=""){
          $maiden="<tr><td>Maiden name:</td><td>".$r["maiden_name"]."</td></tr>";                          
        }
        $gender="";
        if ($r["gender"]=="m"){
          $gender="<tr><td>Gender:</td><td>male</td>";
        } elseif ($r["gender"]=="f"){
          $gender="<tr><td>Gender:</td><td>female</td>";        
        }
        $bday="";
        if ($r["birthday"]!=-1){
          $bday="<tr><td>Birthday:</td><td>".date("F j, Y",$r["birthday"])."</td></tr>";       
        }
        $membership_status="";
        if ($r["status"]!=""){
          $membership_status="<tr><td>Membership status:</td><td>".$r["status"]."</td></tr>";
        }        

        
        $tab0=("
          <div style='width:100%;padding:5px;'>
            <h4>Personal information on file:</h4>
            <div style='float:left;'>
              <table>
                <tr>
                  <td>Last name:</td>
                  <td>".$r["last_name"]."</td>
                </tr>
                <tr>
                  <td>First name:</td>
                  <td>".$r["first_name"]."</td>
                </tr>
                $middle_names
                $maiden
                $gender
                $bday
                $membership_status
                $pub_des
              </table>     
            </div>
            <div class='expl'>
              <p>This information can only be modified by the church office. If you find inaccurate data here, please let them know.</p>
              <p>Use the tabs 'adresses' and 'contact options' to keep the church office current on your contact information.</p>
              <p>Note that not all this information is necessarily visible in the church directory. Change your <a href='privacy_settings.php'>privacy settings</a> to control what can be seen by others.</p>
            </div>                      
          </div>
                    
        
        ");
      
      }
      
      
      
      //Address tab
      
      //Get options for saved_addresses select
      $saved_addresses=$this->auth->church_directory->get_tagged_addresses();
      $saved_addresses_options="<option value='0'>(select from ".sizeof($saved_addresses)." saved addresses)</option>";
      foreach ($saved_addresses as $v){
        $saved_addresses_options.="<option value='".$v["id"]."'>".$v["tag"]." (".$v["street_address"].", ".$v["city"].")</option>";
      }
            
      $tab1="
        <div style='float:left;padding:5px;'>
          <h4>Addresses on file:</h4>
          <div id='addresses_on_file' style='width:300px;height:430px;background:#EEE;overflow-y:auto;'>
            Loading...
          </div>
        </div>
        
        <div style='float:left;padding:5px;'>
          <h4>Add an address:</h4>
          <div style='width:670px;height:430px;background:#EEE;'>
            <div id='accordion'>
              <h4><a href='#'><span style='text-decoration:underline;'>Type an address</span></a></h4>
              <div>
                <table>
                  <tr>
                    <td style='width:150px;'>Street address:</td>
                    <td><input type='text' id='street_address'/></td>
                  </tr>
                  <tr>  
                    <td>Zip-Code:</td>
                    <td><input type='text' id='zip_code'/></td>
                  </tr>
                  <tr>  
                    <td>City:</td>
                    <td><input type='text' id='city' value='".CW_CD_DEFAULT_CITY."'/></td>
                  </tr>
                  <tr>  
                    <td>Province:</td>
                    <td><input type='text' id='province' value='".CW_CD_DEFAULT_PROVINCE."'/></td>
                  </tr>                    
                  <tr>  
                    <td>Country:</td>
                    <td><input type='text' id='country' value='".CW_CD_DEFAULT_COUNTRY."'/></td>
                  </tr>                    
                </table>
                
              </div>
              <h4><a href='#'><span style='text-decoration:underline;'>Use a saved address</span></a></h4>
              <div>
                <table>
                  <tr>
                    <td><select id='saved_addresses' style='width:500px;'>$saved_addresses_options</select></td>
                  </tr>
                </table>
              </div>
            </div>
            <div style='background:white;padding-left:35px;border:1px solid #AAA;margin-top:1px;'>
              <table>
                <tr>
                  <td style='width:150px;'>Apartment no.:</td>
                  <td><input type='text' id='apt_no'/></td>
                  <td></td>
                </tr>
                <tr>  
                  <td>Home phone:</td>
                  <td>
                    <input type='text' id='home_ph_c' style='width:30px;' value='".CW_CD_DEFAULT_COUNTRY_CODE."'/>
                    <input type='text' id='home_ph_a' style='width:50px;' value='".CW_CD_DEFAULT_AREA_CODE."'/>
                    <input type='text' id='home_ph_n' style='width:100px;'/>
                    <input type='text' id='home_ph_e' style='width:30px;'/>
                  </td>
                  <td>
                    <span class='expl'>country code / area code / number / ext.</span>
                  </td>
                </tr>
              </table>
              <table>              
                <tr>  
                  <td><input type='checkbox' id='primary' checked='CHECKED'/> Mark this as primary address</td>
                </tr>
              </table>
            </div>
            <input class='button' type='button' value='store this address' id='save_address' style='float:right;'/>
          </div>
          
        </div>
        
        <script type='text/javascript'>
          //Init the accordion
          $('#accordion').accordion();
          //Load existing addresses
          get_addresses_on_file();
          
          //Province auto complete
          $(function(){
            var provinces = [
              'Ontario','Quebec','British Columbia','Alberta','Manitoba','Sasketchewan','Nova Scotia','New Brunswick','Newfoundland and Labrador','Prince Edward Island','Northwest Territories','Yukon','Nunavut',
              'Washington','California','Florida','Arizona','Illinois'
            ];
            $('#province').autocomplete({source:provinces});
          });
          
          //User chose saved address: get default home phone for that address
          $('#saved_addresses').change(function(){
            $.post('".CW_AJAX."$AJAX?action=get_default_home_phone_for_tagged_address&id=' + $('#saved_addresses option:selected').val(), {}, function(rtn){
              var json = eval('(' + rtn + ')'); //Create JSON object from returned string
              $('#home_ph_c').val(json[0]);   
              $('#home_ph_a').val(json[1]);   
              $('#home_ph_n').val(json[2]);   
              $('#home_ph_e').val(json[3]);   
            });                        
          
          });
          
          //Clear add-address form (after saving)
          function reset_address_form(){
            $('#street_address').val('');
            $('#zip_code').val('');
            $('#city').val('".CW_CD_DEFAULT_CITY."');
            $('#province').val('".CW_CD_DEFAULT_PROVINCE."');
            $('#country').val('".CW_CD_DEFAULT_COUNTRY."');
          
            $('#apt_no').val('');
            $('#home_ph_c').val('".CW_CD_DEFAULT_COUNTRY_CODE."');
            $('#home_ph_a').val('".CW_CD_DEFAULT_AREA_CODE."');
            $('#home_ph_n').val('');
            $('#home_ph_e').val('');
            $('#primary').attr('checked','checked');
                                
            $('#accordion').accordion('option','active',0);
            $('#saved_addresses option:first').attr('selected','selected'); 
          }
          
          function get_addresses_on_file(){
            $('#addresses_on_file').load('".CW_AJAX."$AJAX?action=get_addresses_on_file&person_id=$id');
          }
          
          $('#save_address').click(function(){
            //Which accordion page was open? 0=new address, 1=saved address
            var active=$('#accordion').accordion('option','active'); 
            if (active==0){
              $.post('".CW_AJAX."$AJAX?action=save_address&type=new',{
                person_id:".$id.",
                street_address:$('#street_address').val(),
                zip_code:$('#zip_code').val(),
                city:$('#city').val(),
                province:$('#province').val(),
                country:$('#country').val(),
                home_ph_c:$('#home_ph_c').val(),
                home_ph_a:$('#home_ph_a').val(),
                home_ph_n:$('#home_ph_n').val(),
                home_ph_e:$('#home_ph_e').val(),
                apt_no:$('#apt_no').val(),
                primary:$('#primary').attr('checked')
              }, function(rtn){
                if(rtn!='OK'){
                  alert(rtn);                              
                } else {
                  //Successfully added address - reset form
                  reset_address_form();
                  get_addresses_on_file();
                }
              });
            } else {
              $.post('".CW_AJAX."$AJAX?action=save_address&type=saved',{
                person_id:".$id.",
                saved_address_id:$('#saved_addresses option:selected').val(),
                home_ph_c:$('#home_ph_c').val(),
                home_ph_a:$('#home_ph_a').val(),
                home_ph_n:$('#home_ph_n').val(),
                home_ph_e:$('#home_ph_e').val(),
                apt_no:$('#apt_no').val(),
                primary:$('#primary').attr('checked')              
              }, function(rtn){
                if(rtn!='OK'){
                  alert(rtn);                              
                } else {
                  //Successfully added address - reset form
                  reset_address_form();
                  get_addresses_on_file();
                }
              });            
            }
          });
          
          
        </script>
        
      
      ";
      //end tab1
      
      //Contact options tab
      $tab2="
        <div style='float:left;padding:5px;'>
          <h4>Contact options on file:</h4>
          <div id='contact_options_on_file' style='width:300px;height:430px;background:#EEE;overflow-y:auto;'>
            Loading...
          </div>
        </div>
        
        <div style='float:left;padding:5px;'>
          <h4>Add or edit a contact option:</h4>
          <div style='width:670px;height:430px;background:#EEE;'>


            <div id='accordion2'>
              <h4><a href='#'><span style='text-decoration:underline;'>Cell or fax number</span></a></h4>
              <div>
                <table>
                  <tr>
                    <td style='width:150px;'>Type: </td>
                    <td><select id='contact_options_select_numbers'><option>(Loading...)</option></select></td>
                    <td></td>
                  </tr>
                  <tr>  
                    <td>Number:</td>
                    <td>
                      <input type='text' id='ph_c' style='width:30px;' value='".CW_CD_DEFAULT_COUNTRY_CODE."'/>
                      <input type='text' id='ph_a' style='width:50px;' value='".CW_CD_DEFAULT_AREA_CODE."'/>
                      <input type='text' id='ph_n' style='width:100px;'/>
                      <input type='text' id='ph_e' style='width:30px;'/>
                    </td>
                    <td>
                      <span class='expl'>country / area / no. / ext.</span>
                    </td>
                  </tr>
                </table>              
              </div>
              <h4><a href='#'><span style='text-decoration:underline;'>Email or other</span></a></h4>
              <div>
                <table>
                  <tr>
                    <td style='width:150px;'>Type: </td>
                    <td><select id='contact_options_select_other'><option>(Loading...)</option></select></td>
                    <td></td>
                  </tr>
                  <tr>  
                    <td>Value:</td>
                    <td>
                      <input type='text' id='contact_option_value'/>
                    </td>
                    <td>
                    </td>
                  </tr>
                </table>              
              </div>
            </div>
            
            <input class='button' type='button' value='store this contact option' id='save_contact_option' style='float:right;'/>




          </div>
        </div>      






        <script type='text/javascript'>
          
          //Init the accordion
          $('#accordion2').accordion();

          //Init contact options select
          load_available_contact_options();
          
          get_contact_options_on_file();
          
          function get_contact_options_on_file(){
            $('#contact_options_on_file').load('".CW_AJAX."$AJAX?action=get_contact_options&person_id=$id');
          }
          
          function load_available_contact_options(){
            $('#contact_options_select_numbers').load('".CW_AJAX."$AJAX?action=get_contact_options_select_numbers');          
            $('#contact_options_select_other').load('".CW_AJAX."$AJAX?action=get_contact_options_select_other');          
          }
          
          function reset_contact_options_number_fields(){
            $('#ph_c').val('".CW_CD_DEFAULT_COUNTRY_CODE."');
            $('#ph_a').val('".CW_CD_DEFAULT_AREA_CODE."');
            $('#ph_n').val('');
            $('#ph_e').val('');          
          }
          
          function reset_contact_options_form(){
            $('#contact_option_value').val('');
            reset_contact_options_number_fields();
            load_available_contact_options();
          }
          
          $('#contact_options_select_numbers').change(function(){
            if ($(this).val()!=''){
              $.post('".CW_AJAX."$AJAX?action=get_number&person_id=$id',{ contact_option_type:$(this).val() }, function(rtn){
                if (rtn!=''){
                  var json = eval('(' + rtn + ')'); //Create JSON object from returned string
                  $('#ph_c').val(json[0]);
                  $('#ph_a').val(json[1]);
                  $('#ph_n').val(json[2]);
                  $('#ph_e').val(json[3]);
                  $('#ph_n').focus().select();
                } else {
                  reset_contact_options_number_fields();
                  $('#ph_n').focus();
                }
              });            
            } else {
              reset_contact_options_number_fields();
              $('#ph_n').focus().select();
            }
          });

          $('#contact_options_select_other').change(function(){
            $.post('".CW_AJAX."$AJAX?action=get_other&person_id=$id',{ contact_option_type:$(this).val() }, function(res){            
              $('#contact_option_value').val(res).focus().select();
            });
          });
          
                    
          $('#save_contact_option').click(function(){
            //Which accordion page was open? 0=number, 1=other
            var active=$('#accordion2').accordion('option','active'); 
            if (active==0){
              $.post('".CW_AJAX."$AJAX?action=save_contact_option&type=number',{
                person_id:".$id.",
                contact_option_type:$('#contact_options_select_numbers option:selected').val(),
                ph_c:$('#ph_c').val(),
                ph_a:$('#ph_a').val(),
                ph_n:$('#ph_n').val(),
                ph_e:$('#ph_e').val()
              }, function(rtn){
                if(rtn!='OK'){
                  alert(rtn);                              
                } else {
                  //Successfully added option - reset form
                  reset_contact_options_form();
                  get_contact_options_on_file();
                }
              });
            } else {
              $.post('".CW_AJAX."$AJAX?action=save_contact_option&type=other',{
                person_id:".$id.",
                contact_option_type:$('#contact_options_select_other option:selected').val(),
                contact_option_value:$('#contact_option_value').val()
              }, function(rtn){
                if(rtn!='OK'){
                  alert(rtn);                              
                } else {
                  //Successfully added option - reset form
                  reset_contact_options_form();
                  get_contact_options_on_file();
                }
              });            
            }
          });
          
          
        </script>
      ";      
      //end tab2
            
      //Ministry positions
      $tab3="
        <div style='float:left;padding:5px;'>
          <h4>Ministry positions on file:</h4>
          <div id='ministry_positions_on_file' style='width:300px;height:425px;background:#EEE;overflow-y:auto;text-align:center;padding-top:5px;'>
            <select id='ministry_position_options' style='width:97%;'>
              <option value=''>Loading...</option>
            </select>
            <select id='ministry_positions' style='height:320px;width:97%;' size=\"50\">
              <option>Loading...</option>
            </select>
            <div style='padding-left:5px;'>
              <input style='float:left;' type='button' class='button' id='remove_pos' value='Remove selected'/>
              <input style='float:right;' type='button' class='button' id='toggle_dormancy' value='Toggle active'/>
            </div>
          </div>
        </div>
        
        <div style='float:left;padding:5px;'>
          <h4>&nbsp;</h4>
          <div style='width:670px;height:430px;background:#EEE;'>
          </div>
        </div>
      
        <script type='text/javascript'>
          
          $('#remove_pos').click(function(){
            $('#ministry_positions option:selected').each(function(){
              if ($(this).val()>0){
                $.post('".CW_AJAX."$AJAX?action=remove_position_for_person&person_id=$id&position_id='+ $(this).val(),{},
                  function(rtn){
                    if (rtn=='OK'){
                      //Ministry position deleted successfully. Reload select.
                      reload_ministry_positions();
                    } else {
                      alert(rtn);
                    }
                  } //end rtn                                            
                ); //end post              
              } //end if
            });
          });

          $('#toggle_dormancy').click(function(){
            $('#ministry_positions option:selected').each(function(){
              if ($(this).val()>0){
                $.post('".CW_AJAX."$AJAX?action=toggle_dormancy&person_id=$id&position_id='+ $(this).val(),{},
                  function(rtn){
                    if (rtn=='OK'){
                      //Toggled successfully. Reload select.
                      reload_ministry_positions();
                    } else {
                      alert(rtn);
                    }
                  } //end rtn                                            
                ); //end post              
              } //end if
            });
          });

          //Add an option
          $('#ministry_position_options').change(function(){
            $('#ministry_position_options option:selected').each(function(){
              if ($(this).val()>0){
                $.post('".CW_AJAX."$AJAX?action=add_position_to_person&person_id=$id&position_id='+ $(this).val(),{},
                  function(rtn){
                    if (rtn=='OK'){
                      //Position added successfully. Reload select.
                      reload_ministry_positions();
                    } else {
                      alert(rtn);
                    }
                  } //end rtn                                            
                ); //end post              
              } //end if
            });            
          });
          
          function reload_ministry_positions(){
            $.post('".CW_AJAX."$AJAX?action=get_ministry_positions&person_id=$id',{}, function(res){            
              $('#ministry_positions').html(res);
            });            
          }
          
          function reload_ministry_position_options(){
            $.post('".CW_AJAX."$AJAX?action=get_ministry_position_options',{}, function(res){            
              $('#ministry_position_options').html(res);
            });                      
          }
          
          //Init
          reload_ministry_positions();
          reload_ministry_position_options();
                  
        </script>
      
      ";
      
      //
      if ($user_view){
        $t="
            <div id='tabs'>
                  <ul>
                    <li><a href='#t1'>Personal info</a></li>
                    <li><a href='#t2'>Adresses</a></li>
                    <li><a href='#t3'>Contact options</a></li>
                  </ul>
                  <div id='t1' style='height:500px;'>$tab0</div>                      
                  <div id='t2' style='height:500px;'>$tab1</div>
                  <div id='t3' style='height:500px;'>$tab2</div>
                          
            </div>
            <script type='text/javascript'>
              $('#tabs').tabs();
            </script>        
        ";
      } else {      
        $t="
          $title
          <div id='tabs'>
                <ul>                
                  <li><a href='#t1'>Personal info</a></li>
                  <li><a href='#t2'>Adresses</a></li>
                  <li><a href='#t3'>Contact options</a></li>
                  <li><a href='#t4'>Ministry positions</a></li>
                </ul>
                <div id='t1' style='height:440px;'>$tab0</div>                      
                <div id='t2' style='height:440px;'>$tab1</div>
                <div id='t3' style='height:440px;'>$tab2</div>
                <div id='t4' style='height:440px;'>$tab3</div>
                        
          </div>
          <div style='border-top:1px solid black;padding:5px;height:40px;'><input style='float:right;' class='button' type='button' id='done' value='done'/></div>
          <script type='text/javascript'>
            $('#tabs').tabs();
            $('#done').click(function(){
              close_modal();
            });
          </script>
        
        ";
      }      
      return $t;
    }


  }

?>