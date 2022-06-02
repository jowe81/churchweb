<?php

  require_once "../lib/framework.php";

  //Load styles
  $p->stylesheet(CW_ROOT_WEB."css/personal_records.css");

  $p->p("
    <div style='position:relative;left:300px;top:50px;height:250px;width:650px;border:1px solid #AAA;'>
      <div class='modal_head'>Add a person</div>
      <div style='padding:20px;'>
        <div class='expl'>
          <p>
            Type a last name and a first name for the new person.
            Hit 'create personal record' to add the person and to go the personal record edit dialogue.
          </p>
          <p>
            Note that a user account will be automatically generated for the new person. It will, however, be disabled by default.
          </p>     
        </div>
        <form id='f_new'>
          <table>
            <tr>
              <td>Last name</td><td><input id='last_name' type='text'/></td>
            </tr>
            <tr>
              <td>First name</td><td><input id='first_name' type='text'/></td>
            </tr>
          </table>
          <input type='submit' id='save' value='create personal record' style='margin-top:60px;'/>
        </form>
      </div> 
    </div>
  ");
  
  $p->jquery("
  
    
    $('#last_name').focus();

    $('#f_new').submit(function(e){
      e.preventDefault();
      $.post('".CW_AJAX."ajax_add_person.php?action=save',{
        last_name:  $('#last_name').val(),
        first_name: $('#first_name').val()
      },function(rtn){
        if (rtn>0){
          //Go to personal_records.php and edit new entry
          window.location='".CW_ROOT_WEB.$a->services->get_parents_url($a->csid)."?id='+rtn;                          
        } else {
          alert(rtn);
        }
      });
    }); 
     
  ");


?>