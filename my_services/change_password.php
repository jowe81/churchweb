<?php

  require_once "../lib/framework.php";

  //Load styles
  //$p->stylesheet(CW_ROOT_WEB."css/display_bookings_list.css");


  if ($_GET["action"]==""){
    $p->p("
            <div style='position:relative;width:800px;height:300px;top:50px;left:230px;background:#DDD;border:1px solid gray;'>
              <div style='position:absolute;left:700px;top:200px;'>
                <img src='".CW_ROOT_WEB.CW_PLATFORM_LOGO_FULL_WEBSIZE."' style='width:90px;height:90px;'/>
              </div>
              <div style='width:510px;position:relative;margin:50px auto;'>
                <form id=\"loginform\" action='' method='POST'>
                  <table>
                    <tr>
                      <td>Enter current password:</td>
                      <td><input type='password' id='old_pw' name='loginname'/></td>
                    </tr>
                    <tr>
                      <td>Enter new password:</td>
                      <td><input type='password' id='new_pw' name='password'/></td>
                    </tr>
                    <tr>
                      <td>Repeat new password:</td>
                      <td><input type='password' id='new_pw2' name='password'/></td>
                    </tr>
                    <tr>
                      <td><input class='button' type='submit' name='submit_request' id='submit_request' value='Change Password'></td>
                      <td></td>
                    </tr>
                  </table>
                </form>
              </div>
            </div>
            <div class='expl' style='position:absolute;left:795px;top:100px;'>
              <p>For your security, please use a 'strong' password. Strong passwords observe several of the following criteria:</p>
              <p>
                <ul>
                  <li>no dictionary words</li>
                  <li>at least 8 characters in length</li>
                  <li>use capital and non-capital letters</li>
                  <li>use digits</li>
                  <li>use special characters</li>
                </ul>
              </p>
            </div>
            
    ");
    $p->jquery("
      
      $('#old_pw').focus();
      
      $('#submit_request').click(function(e){
        e.preventDefault();
        var old_pw=$('#old_pw').val();
        var new_pw=$('#new_pw').val();
        var new_pw2=$('#new_pw2').val();
        $.post('".CW_AJAX."ajax_change_password.php?action=change_password', { old_pw:old_pw, new_pw:new_pw, new_pw2:new_pw2 }, function(rtn){
          if (rtn!='OK') {
            alert(rtn);
          } else {
            alert('You have successfully changed your password');
            window.location.replace('".CW_ROOT_WEB."home.php');
          }
        });
      });
      
    ");
      
  }



?>