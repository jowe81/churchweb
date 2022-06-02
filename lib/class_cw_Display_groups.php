<?php

  class cw_Display_groups {
  
    private $d,$auth; //Database access
    
    function __construct($d,$auth){
      $this->d=$d;
      $this->auth=$auth;
    }

    //Output JS for editing in modal window
    function output_js(){
      $t="
        <script type='text/javascript'>
          //dm = display modal
          function dm(id){
              $('#main_content').fadeTo(500,0.3);
              $('#modal').load('".CW_AJAX."ajax_manage_groups.php?action=display_edit_dialogue&id=' +id);
              $('#modal').css('width','986px');
              $('#modal').css('left','135px');
              $('#modal').css('top','70px');
              $('#modal').fadeIn(200);                        
          }
        </script>      
      ";
      return $t;
    }
  
    //Output filtered table (by name)- all individual records (should be wrapped in div id='cd_list' by calling script) 
    function display_list($filter=""){
      $t=$this->output_js();
      
      //Retrieve array of user records w/ last_name and first_name            
      $r=$this->d->res_to_array($this->d->select("groups","*","name LIKE '$filter%' ORDER BY name"));
             
      /*
        $r[] has all these fields now:
        id,name,description,active        
      */
      
      //How many found?
      $n=sizeof($r);
      $word="groups";
      if ($n==1){
        $word="group";
      }
      
      //ouput table
      $t.="<table id='cd_ind_view_table'>";
      $t.="
        <tr>
          <th style='width:180px;'>Group name</th>
          <th style='width:180px;'>Group description</th>
          <th style='width:120px;'>Group status</th>
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
        //Group active?
        $grp_status="active";
        if ($v["active"]==0){
          $grp_status="<span style='color:red;'>disabled</span>";
        }
                           
                
        $t.="<tr id='r".$v["id"]."' class='$class'>
            <td><span style='font-weight:bold;'>".$v["name"]."</span></td>
            <td>".$v["description"]."</td>
            <td>".$grp_status."</td>
            <td style='text-align:right;'><a class='blink' href='javascript:dm(".$v["id"].");'>edit&nbsp;group</a>&nbsp;</td>
            </tr>  
            <script type='text/javascript'>
              $('#r".$v["id"]."').click(function(){
                dm(".$v["id"].");
              });
            </script>          
          ";
      }
      
      $t.="</table>";
      return $t;
    }
  
    //Produce html form
    function display_edit_dialogue($id=0){
    
      $expl_div="<div class='expl' style='width:251px;position:absolute;left:712px;top:401px;'>
                  <p>Changes you make here affect privileges for all group members.</p>
                  <p>Deactivating or deleting the group revokes the group's privileges from its members.</p>
                   
                </div>";
    
      $name=$this->auth->groups->get_name($id);
      $title="<div class='modal_head'>Edit group: $name</div>";
      
     
      //Get options for service nodes to add permissions for
      $permission_options="<option value='0'>(select service node to add...)";
      $f=array();
      $this->auth->services->get_service_hierarchy($f);
      foreach ($f as $v){
        $permission_options.="<option value='".$v["id"]."'>".str_repeat("&nbsp;&nbsp;",($v["lvl"]-1)).$v["title"]."</option>";
      }
      
      //Group active? Get checkbox value
      $grp_status="";
      if ($this->auth->groups->is_active($id)){
        $grp_status="CHECKED";
      }
      
      //Get list of members
      $members="";
      $x=$this->auth->group_memberships->get_memberships_for_group($id);
      foreach ($x as $v){
        $personal_record=$this->auth->personal_records->get_person_record($v);
        $members.=$personal_record["last_name"].", ".$personal_record["first_name"]." ".substr($personal_record["middle_names"],0,1)."<br/>";
      }
      
      
      $t=("
        $title
        <div style='width:100%;margin:0px auto;padding:2px;'>
          <div style='float:left;width:968px;padding:5px;border:1px solid #CCC;margin:1px;'>
            <h4>Group details</h4>
            <table style='width:100%;'>
              <tr>
                <td>Name: <input type='text' id='name' value='".$this->auth->groups->get_name($id)."' /></td>
                <td>Description: <input type='text' id='description' value='".$this->auth->groups->get_description($id)."' /></td>
                <td><input class='button' type='button' id='save_details' value='save details' /></td>
                <td style=' text-align:center;'>Group status: <input type='checkbox' id='grp_status' value='1' $grp_status />  active</td>
              </tr>
            </table>
          </div>
          
          <div style='float:left;width:100%;'>
            
            <div style='width:340px;height:460px;padding:5px;float:left;border:1px solid #CCC;margin:1px;'>
              <h4>Group permissions</h4>
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
                  <td><select id='direct_group_permissions' size='20' style='width:330px;height:298px;'><option>Loading...</option></select></td>
                </tr>
                <tr>
                  <td>
                    <input class='button' type='button' id='revoke_group_permission' value='revoke selected'/>
                    <input class='button' type='button' id='revoke_all_group_permissions' value='revoke all'/>
                  </td>
                </tr>
              </table>              
            </div>    
                                                                      
            <div style='width:340px;height:460px;padding:5px;float:left;border:1px solid #CCC;margin:1px;'>
              <h4>Effective privileges</h4>
              <div id='effective_privileges' style='height:440px;overflow-y:auto;'>Loading...</div>
            </div>
            
            <div style='width:260px;height:270px;padding:5px;float:left;border:1px solid #CCC;margin:1px;'>
              <h4>Group members (".sizeof($x).")</h4>
              <div id='members' style='height:200px;overflow-y:auto;'>$members</div>
            </div>
            
            <div style='width:254px;padding:5px;padding-left:11px;padding-top:135px;float:left;border:1px solid white;margin:1px;'>            
              <input class='button' type='button' id='delete_group' style='width:122px;height:40px;' value='delete group'/>              
              <input class='button' type='submit' id='exit' style='width:122px;height:40px;' value='exit'/>              
            </div>
          </div>
          
          $expl_div        
        </div>        
        <script type='text/javascript'>
          //JS for this form


          //Initial load of list of memberships, permissions, and effective privileges
          reload_direct_group_permissions();
          reload_effective_privileges();
          
          $('#exit').focus();
          
          ///////////////////////// Group details
          
          //Save details on click of button
          $('#save_details').click(function(){
            $.post('".CW_AJAX."ajax_manage_groups.php?action=save_details&group_id=$id', { name: $('#name').val() , description: $('#description').val(), active: $('#grp_status').attr('checked') },function(rtn){
              if (rtn=='OK'){
                alert('Group details saved successfully');
              } else {
                alert(rtn);              
              }
            });
          }); 
          
          //Change group status
          $('#grp_status').change(function(){
            $.post('".CW_AJAX."ajax_manage_groups.php?action=toggle_grp_status&group_id=$id', {},function(rtn){
              if (rtn=='1'){
                $('#grp_status').attr('checked',true);
              } else {
                $('#grp_status').attr('checked',false);              
              }
              reload_effective_privileges();
            });
          });
              
          //////////////////////// Group permissions
          
          //User selected a permission to add
          $('#services').change(function(){
            $('#services option:selected').each(function(){
              if ($(this).val()>0){
                $.post('".CW_AJAX."ajax_manage_groups.php?action=add_group_permission&group_id=$id&permission_type=' + $('input:radio[name=permission_type]:checked').val() + '&service_id='+ $(this).val(),{},
                  function(rtn){
                    if (rtn=='OK'){
                      //Group permission added succesfully. Reload group permissions select and effective privileges
                      reload_direct_group_permissions();
                      reload_effective_privileges();
                    }
                  } //end rtn                                            
                ); //end post              
              } //end if
            });
          });
          
          function reload_direct_group_permissions(){
            $.post('".CW_AJAX."ajax_manage_groups.php?action=get_direct_group_permissions&group_id=$id',{},function(rtn){
              var json = eval('(' + rtn + ')'); //Create JSON object from returned string   
              var sel_html='';
              $.each(json,function(id,r){
                var type_label='N/A';
                if (r.type==0){
                  type_label='".strtolower($this->auth->permissions->int_to_full_permission_type(0))."';                 
                }
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
              $('#direct_group_permissions').html(sel_html);      
            });            
          }
          
          //Revoke the selected permission(s)         
          $('#revoke_group_permission').click(function(){
            $('#direct_group_permissions option:selected').each(function(){
              $.post('".CW_AJAX."ajax_manage_groups.php?action=revoke_group_permission&group_id=$id&service_id='+ $(this).attr('id'),{},
                function(rtn){
                  if (rtn=='OK'){
                    reload_direct_group_permissions();                                
                    reload_effective_privileges();
                  } else {
                    alert(rtn);
                  }
                } //end rtn
              ); //end post
            }); //end each
          });         

          //Revoke all permissions 
          $('#revoke_all_group_permissions').click(function(){    
            $.post('".CW_AJAX."ajax_manage_groups.php?action=revoke_all_group_permissions&group_id=$id',{},
              function(rtn){
                if (rtn=='OK'){
                  reload_direct_group_permissions();                                
                  reload_effective_privileges();
                } else {
                  alert(rtn);
                }
              } //end rtn
            ); //end post
          });         
          
          /////////////////////////
          
          function reload_effective_privileges(){
            $('#effective_privileges').load('".CW_AJAX."ajax_manage_groups.php?action=get_effective_privileges&group_id=$id');
          }    
              
              
              
          $('#delete_group').click(function(){
            var f=confirm('Are you sure you want to delete this group?');
            if (f){
              $.post('".CW_AJAX."ajax_manage_groups.php?action=delete_group&group_id=$id',{},
                function(rtn){
                  if (rtn=='OK'){
                    $('#main_content').fadeTo(500,1);
                    $('#modal').hide(200);
                    init_dest_element();
                  } else {
                    alert(rtn);
                  }
                } //end rtn
              ); //end post
            }            
          });    
              
                            
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

    function display_new_group_dialogue(){
      $expl_div="<div class='expl' style='width:251px;position:absolute;left:981px;top:43px;'>
                  <p>Type at least a group name (something descriptive, like 'worship leaders') and hit 'create group'. The group will be created and you will be taken to a dialogue to edit the group's properties and privileges.</p>
                   
                </div>";
    
      $t="
        <div style='width:400px;padding:15px;border:1px solid gray;position:absolute;left:420px;top:100px;'>
          <h3>Create new group</h3>
          <div>
            <form id='create_group_form'>
              <table>
                <tr>
                  <td>Group name:</td>
                  <td><input type='text' id='name' /></td>
                </tr>
                <tr>
                  <td>Group description:</td>
                  <td><input type='text' id='description' /></td>
                </tr>
                <tr>
                  <td><br/><input type='submit' style='width:150px' id='create_group' value='create group' /></td>
                  <td></td>
                </tr>
              </table>
            </form>
          </div>
        </div>
        $expl_div
        
        <script type='text/javascript'>
        
          //Keep the form from actually submitting (since we do it through ajax)
          $('#create_group_form').bind('submit', function(e) {
              e.preventDefault();
          });
        
          $('#name').focus();
        
          $('#create_group').click(function(){
            $.post('".CW_AJAX."ajax_add_group.php?action=create_group', { name:$('#name').val(),description:$('#description').val() }, function(rtn){
              if (rtn=='OK'){
                //Redirect to manage groups (parent service), and send to edit window
                window.location='".CW_ROOT_WEB.$this->auth->services->get_parents_url($this->auth->csid)."?name='+$('#name').val();                
              } else {
                $('#name').focus();
                alert(rtn);
              }
            });
          });
        
        </script>
      
      ";
      return $t;
    }
  }

?>