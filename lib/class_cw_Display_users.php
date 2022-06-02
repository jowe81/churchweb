<?php

  class cw_Display_users {
  
    private $d,$auth; //Database access
    
    function __construct($d,$auth){
      $this->d=$d;
      $this->auth=$auth;
    }

    //Output JS for editing in modal window
    function output_js(){
      $t="
        <script type='text/javascript'>
          //ea=edit account
          function ea(id){
              $('#main_content').fadeTo(500,0.3);
              $('#modal').load('".CW_AJAX."ajax_manage_users.php?action=display_edit_dialogue&id=' +id);
              $('#modal').css('width','1180px');
              $('#modal').css('left','35px');
              $('#modal').css('top','70px');
              $('#modal').fadeIn(200);                        
          }
        </script>      
      ";
      return $t;
    }
  
    //Output filtered table - all individual records (should be wrapped in div id='cd_list' by calling script) 
    function display_list($filter=array()){
      $t=$this->output_js();
      
      //Retrieve array of user records w/ last_name and first_name            
      $r=$this->d->res_to_array($this->d->select_joined("users","people","id","*","first_name,last_name",cw_Db::array_to_query_conditions($filter),"people.last_name,people.first_name"));
                          
      /*
        $r[] has all these fields now:
        id,last_name,first_name,loginname,password,active,last_login,last_ip,failed_login_attempts,blocked_until        
      */
      
      //How many found?
      $n=sizeof($r);
      $word="users";
      if ($n==1){
        $word="user";
      }
      
      //ouput table
      $t.="<table id='cd_ind_view_table'>";
      $t.="
        <tr>
          <th>Name</th>
          <th style='width:180px;'>Login name</th>
          <th style='width:180px;'>Last login</th>
          <th style='width:220px;'>Failed login attempts</th>
          <th style='width:120px;'>Account status</th>
          <th style='width:140px;text-align:right;'>".sizeof($r)." $word found&nbsp;</th>
        </tr>
          
      ";
      foreach ($r as $v){
        //Markup odd or even
        $x++;
        $class="even";
        if (($x%2)>0){
          $class="odd";        
        }
        //Last login
        $last_login="never logged in";
        if ($v["last_login"]>0){
          $last_login=date("d/m/Y H:i:s",$v["last_login"]);
        }
        //Failed login attempts
        $failed_login_attempts="-";
        if ($v["failed_login_attempts"]>0){
          $blocked="";
          if ($v["blocked_until"]>time()){
            $blocked=" - acct. blocked";
          }
          $failed_login_attempts="<span style='color:red;'>".$v["failed_login_attempts"]."$blocked</span>";
        }     
        //Account active?
        $acct_status="active";
        if ($v["active"]==0){
          $acct_status="<span style='color:red;'>disabled</span>";
        }
                           
                
        $t.="<tr id='r".$v["id"]."' class='$class'>
            <td><span style='font-weight:bold'>".$v["last_name"]."</span>$maiden, <span style='font-weight:bold'>".$v["first_name"]." ".$v["middle_names"]."</span>$pd</td>
            <td>".$v["loginname"]."</td>
            <td>".$last_login."</td>
            <td>".$failed_login_attempts."</td>
            <td>".$acct_status."</td>
            <td style='text-align:right;'><a class='blink' href='javascript:ea(".$v["id"].");'>edit&nbsp;account</a>&nbsp;</td>
            </tr>  
            <script type='text/javascript'>
              $('#r".$v["id"]."').click(function(){
                ea(".$v["id"].");
              });
            </script>          
          ";
      }
      
      $t.="</table>";
      return $t;
    }
  
    //Produce html form
    function display_edit_dialogue($id=0){
    
      $expl_div="<div class='expl' style='width:172px;position:absolute;left:988px;top:22px;'>
                  <p>Changes you make here affect the users' account credentials and privileges. If you change login name, hit 'generate new password', or change the account status, the user will be notified via email.</p>
                  <p>Assigning group memberships or permissions affects the users' effective privileges displayed on the right.</p>
                  <p>Note that changed privileges have no effect on the user's current sessions. They become effective at the login time.</p>
                   
                </div>";
    
      $name=$this->auth->personal_records->get_name_first_last($id);
      $title="<div class='modal_head'>Edit user account for $name</div>";
      
      //Get options for groups to add memberships for
      $group_options="<option value='0'>(select a group to add...)";
      if ($f=$this->auth->groups->get_table()){
        foreach ($f as $v){
          $group_options.="<option value='".$v["id"]."'>".$v["name"]."</option>";
        }
      }
      
      //Get options for service nodes to add permissions for
      $permission_options="<option value='0'>(select service node to add...)";
      $f=array();
      $this->auth->services->get_service_hierarchy($f);
      foreach ($f as $v){
        $permission_options.="<option value='".$v["id"]."'>".str_repeat("&nbsp;&nbsp;",($v["lvl"]-1)).$v["title"]."</option>";
      }
      
      //Account active? Get checkbox value
      $acct_status="";
      if ($this->auth->users->account_is_active($this->auth->users->get_loginname($id))){
        $acct_status="CHECKED";
      }
      
      $t=("
        $title
        <div style='width:100%;margin:0px auto;padding:2px;'>
          <div style='float:left;width:968px;padding:5px;border:1px solid #CCC;margin:1px;'>
            <h4>Account details</h4>
            <table style='width:100%;'>
              <tr>
                <td>Login name: <input type='text' id='loginname' value='".$this->auth->users->get_loginname($id)."' /></td>
                <td><input class='button' type='button' id='change_loginname' value='change login name' /></td>
                <td><input class='button' type='button' id='generate_password' value='generate new password' /></td> 
                <td style='width:300px; text-align:center;'>Account status: <input type='checkbox' id='acct_status' value='1' $acct_status />  active</td>
              </tr>
            </table>
          </div>
          
          <div style='float:left;width:100%;'>
            <div style='width:260px;height:460px;padding:5px;float:left;border:1px solid #CCC;margin:1px;'>
              <h4>Group memberships</h4>
              <table>
                <tr>
                  <td><select id='groups' style='width:250px;'>$group_options</select></td>
                </tr>
                <tr>
                  <td><select id='group_memberships' size='20' style='width:250px;height:340px;'><option>Loading...</option></select></td>
                </tr>
                <tr>
                  <td>
                    <input class='button' type='button' id='revoke_group_membership' value='revoke selected'/>
                    <input class='button' type='button' id='revoke_all_group_memberships' value='revoke all'/>
                  </td>
                </tr>
              </table>
            </div>
            
            <div style='width:340px;height:460px;padding:5px;float:left;border:1px solid #CCC;margin:1px;'>
              <h4>User permissions</h4>
              <table>
                <tr>
                  <td>
                    Type for new permission:<br/>
                    <input type='radio' name='permission_type' id='permission_type' value='".CW_AUTH_LEVEL_VIEWER."' CHECKED />viewer
                    <input type='radio' name='permission_type' id='permission_type' value='".CW_AUTH_LEVEL_EDITOR."'/>editor
                    <input type='radio' name='permission_type' id='permission_type' value='".CW_AUTH_LEVEL_ADMIN."' />admin
                  </td>
                </tr>
                <tr>
                  <td><select id='services' style='width:330px;'>$permission_options</select></td>
                </tr>
                <tr>
                  <td><select id='direct_user_permissions' size='20' style='width:330px;height:298px;'><option>Loading...</option></select></td>
                </tr>
                <tr>
                  <td>
                    <input class='button' type='button' id='revoke_user_permission' value='revoke selected'/>
                    <input class='button' type='button' id='revoke_all_user_permissions' value='revoke all'/>
                  </td>
                </tr>
              </table>              
            </div>    
                                                                      
            <div style='width:340px;height:460px;padding:5px;float:left;border:1px solid #CCC;margin:1px;'>
              <h4>Effective privileges</h4>
              <div id='effective_privileges' style='height:440px;overflow-y:auto;'>Loading...</div>
            </div>
            
            <div style='width:180px;height:45px;padding:5px;padding-top:420px;float:left;border:1px solid white;margin:1px;'>
              <input class='button' type='submit' id='exit' style='width:180px;height:40px;' value='exit'/>              
            </div>
          </div>
          
          $expl_div        
        </div>        
        <script type='text/javascript'>
          //JS for this form


          //Initial load of list of memberships, permissions, and effective privileges
          reload_group_memberships(); 
          reload_direct_user_permissions();
          reload_effective_privileges();
          
          $('#exit').focus();
          
          ///////////////////////// Account details
          
          //Change loginname on blur of loginname input
          $('#change_loginname').click(function(){
            $.post('".CW_AJAX."ajax_manage_users.php?action=change_loginname&person_id=$id&loginname=' + $('#loginname').val(), {},function(rtn){
              if (rtn=='OK'){
                alert('New credentials for $name are: \\n\\nLoginname: \''+ $('#loginname').val() + '\'\\nPassword: (unchanged)');
              } else {
                if (rtn=='NOCHANGE'){
                  //No change happened because user didn't change the loginname
                } else {
                  //Get original loginname back, change could not be execeuted
                  $.post('".CW_AJAX."ajax_manage_users.php?action=get_loginname&person_id=$id', {},function(rtn){
                    $('#loginname').val(rtn);
                  });                
                  alert(rtn);
                }
              }
            });
          }); 
          
          //Change account status
          $('#acct_status').change(function(){
            $.post('".CW_AJAX."ajax_manage_users.php?action=toggle_acct_status&person_id=$id', {},function(rtn){
              if (rtn=='1'){
                $('#acct_status').attr('checked',true);
              } else {
                $('#acct_status').attr('checked',false);              
              }
            });
          });
          
          //Generate passord
          $('#generate_password').click(function(){
            $.post('".CW_AJAX."ajax_manage_users.php?action=generate_password&person_id=$id', {},function(rtn){
              alert(rtn);
            });            
          });
          
          
          ///////////////////////// Group memberships
          
          
          function reload_group_memberships(){
            $.post('".CW_AJAX."ajax_manage_users.php?action=get_group_memberships&person_id=$id',{},function(rtn){
              var json = eval('(' + rtn + ')'); //Create JSON object from returned string   
              var sel_html='';
              $.each(json,function(id,r){
                sel_html+='<option id=\"' + r.id + '\">' + r.name +'</option>';
                        
              });
              $('#group_memberships').html(sel_html);      
            });            
          }
          
          //User selected a group to add
          $('#groups').change(function(){
            if ($(this).val()>0){ //Avoid ajax call when the dummy/descriptor element is called 
              $('#groups option:selected').each(function(){
                $.post('".CW_AJAX."ajax_manage_users.php?action=add_group_membership&person_id=$id&group_id='+ $(this).val(),{},
                  function(rtn){
                    if (rtn=='OK'){
                      //Group membership added succesfully. Reload group_memberships select and eff prvlg.
                      reload_group_memberships();
                      reload_effective_privileges();
                    }
                  } //end rtn                                            
                ); //end post              
              });
            }
          });
                   
          //Revoke the selected group(s)         
          $('#revoke_group_membership').click(function(){
            $('#group_memberships option:selected').each(function(){
              $.post('".CW_AJAX."ajax_manage_users.php?action=revoke_group_membership&person_id=$id&group_id='+ $(this).attr('id'),{},
                function(rtn){
                  if (rtn=='OK'){
                    reload_group_memberships();                                
                    reload_effective_privileges();
                  } else {
                    alert(rtn);
                  }
                } //end rtn
              ); //end post
            }); //end each
          });         

          //Revoke all groups (except default)
          $('#revoke_all_group_memberships').click(function(){
            $.post('".CW_AJAX."ajax_manage_users.php?action=revoke_all_group_memberships&person_id=$id',{},
              function(rtn){
                if (rtn=='OK'){
                  reload_group_memberships();                                
                  reload_effective_privileges();
                } else {
                  alert(rtn);
                }
              } //end rtn
            ); //end post
          });         
              
          //////////////////////// User permissions
          
          //User selected a permission to add
          $('#services').change(function(){
            $('#services option:selected').each(function(){
              if ($(this).val()>0){
                $.post('".CW_AJAX."ajax_manage_users.php?action=add_user_permission&person_id=$id&permission_type=' + $('input:radio[name=permission_type]:checked').val() + '&service_id='+ $(this).val(),{},
                  function(rtn){
                    if (rtn=='OK'){
                      //User permission added succesfully. Reload user permissions select and effective privileges
                      reload_direct_user_permissions();
                      reload_effective_privileges();
                    }
                  } //end rtn                                            
                ); //end post              
              } //end if
            });
          });
          
          function reload_direct_user_permissions(){
            $.post('".CW_AJAX."ajax_manage_users.php?action=get_direct_user_permissions&person_id=$id',{},function(rtn){
              var json = eval('(' + rtn + ')'); //Create JSON object from returned string   
              var sel_html='';
              $.each(json,function(id,r){
                var type_label='N/A';
                if (r.type==1){
                  type_label='".strtolower($this->auth->permissions->int_to_full_permission_type(1))."';                 
                }
                if (r.type==2){
                  type_label='".strtolower($this->auth->permissions->int_to_full_permission_type(2))."';                 
                }
                if (r.type==3){
                  type_label='".strtolower($this->auth->permissions->int_to_full_permission_type(3))."';                 
                }
                sel_html+='<option id=\"' + r.id + '\">' + r.title + ' (' + type_label + ')</option>';
                        
              });
              $('#direct_user_permissions').html(sel_html);      
            });            
          }
          
          //Revoke the selected permission(s)         
          $('#revoke_user_permission').click(function(){
            $('#direct_user_permissions option:selected').each(function(){
              $.post('".CW_AJAX."ajax_manage_users.php?action=revoke_user_permission&person_id=$id&service_id='+ $(this).attr('id'),{},
                function(rtn){
                  if (rtn=='OK'){
                    reload_direct_user_permissions();                                
                    reload_effective_privileges();
                  } else {
                    alert(rtn);
                  }
                } //end rtn
              ); //end post
            }); //end each
          });         

          //Revoke all permissions 
          $('#revoke_all_user_permissions').click(function(){
            $.post('".CW_AJAX."ajax_manage_users.php?action=revoke_all_user_permissions&person_id=$id',{},
              function(rtn){
                if (rtn=='OK'){
                  reload_direct_user_permissions();                                
                  reload_effective_privileges();
                } else {
                  alert(rtn);
                }
              } //end rtn
            ); //end post
          });         
          
          /////////////////////////
          
          function reload_effective_privileges(){
            $('#effective_privileges').load('".CW_AJAX."ajax_manage_users.php?action=get_effective_privileges&person_id=$id');
          }    
              
              
              
              
              
                            
          /////////////////Exit
          $('#exit').click(function(){
            $('#main_content').fadeTo(500,1);
            $('#modal').hide(200);
            init_dest_element();
          });
          
          
        </script>
      
      
      ");
      return $t;    
    }


  }

?>