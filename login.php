<?php

  /* Login script must set $_SESSION vars upon successful login */
   
  require_once "lib/framework.php";
  $p->stylesheet(CW_ROOT_WEB."css/login.css");
  
  $spref=new cw_System_preferences($a);

  if (!$spref->login_blocked()){
    //Login is available (not blocked)
    if (isset($_POST["action"])){
      if($_POST["action"]=="Login"){
        //Process login: $a->login() returns session_id on success
        $session_id=$a->login($_POST["loginname"],$_POST["password"],$_SERVER["REMOTE_ADDR"]);
        
        //At this time perform some database maintenance (this might go to a cronjob at some point)
        $maintenance=new cw_Maintenance($a);
        $maintenance->music_db_clear_unused_records();
        $maintenance->service_planning_clear_unused_records();
        $maintenance->clear_old_sync_marks($a->cuid);
        
        if ($session_id!=""){
          //Success. Set session vars.
          $_SESSION["session_id"]=$session_id;
          $_SESSION["person_id"]=$a->sessions->get_session_owner($session_id);
          $_SESSION["my_permitted_services"]=$a->my_permitted_services;
          //Client Time offset
          $ts=time();
          date_default_timezone_set($_POST["timezone"]);    
          $client_offset=date('Z',$ts);
          date_default_timezone_set(CW_TIME_ZONE); 
          $server_offset=date('Z',$ts);
          $_SESSION["server_time_offset"]=$client_offset-$server_offset;      
          //Redirect to home page.
          header("Location: ".CW_ROOT_WEB."home.php");
        } else {
          $p->error("Login attempt failed.",array(CW_LOGIN=>"Try again"));
        }
      } else {
        //Attempt Account activation
        if (($_POST["last_name"]!="") && ($_POST["first_name"]!="") && (!(strpos($_POST["email"],'@')===false)) && (strpos($_POST["email"],' ')===false)){
          if ($id=$a->activate_account_by_lastname_firstname($_POST["last_name"],$_POST["first_name"])){
            //Success.
            //Update / store email address.
            $email_type=CW_CD_PERSONAL_EMAIL_CONTACT_OPTION_TYPE;
            if (isset($_POST["is_business_email"])){
              $email_type=CW_CD_WORK_EMAIL_CONTACT_OPTION_TYPE;          
            }
            //Store the provided email address either as personal or work email (depending on what was selected)
            $a->church_directory->add_contact_option($id,$email_type,$_POST["email"]);
            //Set the selected email type as default email field for cw communication
            $a->users->set_contact_option_type_for_cw_comm($id,$email_type);
            if ($a->email_login_credentials($id)){
              $email="<p>We have sent you an email with your login credentials.</p>";
            } else {
              $email="<p>Unfortunately we were unable to email you your login credentials. Please contact the office to obtain them.</p>";          
            }        
            $p->p("
                    <div style='position:relative;width:800px;height:300px;top:50px;left:200px;background:#FFF;border:1px solid gray;'>
                      <div style='padding:5px;'>
                        <h4>Request account activation</h4>
                        <div style='width:600px;position:relative;margin-left:50px;margin-top:30px;'>
                          <p>Congratulations, ".$a->personal_records->get_first_name($id).". Your account activated successfully!</p>
                          $email
                          <p>We hope that ChurchWeb will help you stay connected at ".CW_CHURCH_NAME." and that it will prove a useful tool in your ministry.</p>
                          <p>
                            <a href='".CW_ROOT_WEB."login.php' class='button blink'>Proceed to login</a>
                          </p>
                        </div>
                      </div>
                    </div>
                    ");      
          } else {
            //Error
            if ($id=$a->personal_records->get_personid_by_lastname_firstname($_POST["last_name"],$_POST["first_name"])){
              if ($a->users->account_is_active($a->users->get_loginname($id))){
                $p->error("Could not activate your account, ".$a->personal_records->get_first_name($id)." - it is active already!", array(CW_ROOT_WEB."login.php"=>"Proceed to login"));                
              } else {
                $p->error("You have an existing account that has been blocked. Contact the church office for assistance.", array(CW_ROOT_WEB."login.php"=>"Proceed to login"));                          
              }
            } else {
              $p->error("Unfortunately we could not activate your account. Please contact the church office for assistance.", array(CW_ROOT_WEB."login.php?a=request_account_activation"=>"Try again"));            
            }
          }              
        } else {
          //Required fields missing
          $p->error("You must provide last name, first name and a valid email address.", array(CW_ROOT_WEB."login.php?a=request_account_activation"=>"Try again"));            
        }    
      }
    } else {
      if ($_GET["a"]==""){
        /* Show login form */
        $cl=new cw_Changelog($a);//for version info      
        $p->p("
                <div style='position:relative;width:800px;height:300px;top:50px;left:230px;background:#DDD;border:1px solid gray;'>
                  <div style='position:absolute;left:5px;top:200px;'>
                    <img src='".CW_ROOT_WEB.CW_CHURCH_LOGO_FULL_WEBSIZE."'/>
                  </div>
                  <div style='position:absolute;left:700px;top:200px;'>
                    <img src='".CW_ROOT_WEB.CW_PLATFORM_LOGO_FULL_WEBSIZE."' style='width:90px;height:90px;'/>
                  </div>
                  <div style='width:310px;position:relative;margin:50px auto;'>
                    <form id=\"loginform\" action='' method='POST'>
                      <table>
                        <tr>
                          <td>Login name:</td>
                          <td><input type='text' id='loginname' name='loginname'/></td>
                        </tr>
                        <tr>
                          <td>Password:</td>
                          <td><input type='password' id='password' name='password'/></td>
                        </tr>
                        <tr>
                          <td><input type='submit' name='action' value='Login'></td>
                          <td style='text-align:right;' class='small_note'>
                            Need credentials?<br/>Go to <a href='?a=request_account_activation'>account activation</a>
                            <input type=\"hidden\" id=\"timezone\" name=\"timezone\"/>
                          </td>
                        </tr>
                      </table>
                    </form>
                  </div>
                </div>
                <div style='position:absolute;left:280px;top:390px;'>
                  <span class='small_note gray'>ChurchWeb is a project of <a href='http://www.jowe.de'>jowe.de</a>. Copyright (C) 2012-".date("Y",time())." by Johannes Weber. All rights reserved. | Version ".$cl->current_version_record["version_name"]." from ".date("F j, Y, g:ia",$cl->current_version_record["deployed_at"])."</span>
                </div>
                ");
        $p->jquery("
          $('#loginname').focus();
          
          $('#loginform').submit(function(){
            //Send the client timezone descriptor in a hidden input
            $('#timezone').val(jstz.determine().name());
          });
          
        ");
      } elseif ($_GET["a"]=="request_account_activation") {
        //Account activation request form
        $p->p("
                <div style='position:relative;width:800px;height:300px;top:50px;left:230px;background:#FFF;border:1px solid gray;'>
                  <div style='padding:5px;'>
                    <h4>Request account activation</h4>
                    <div class='expl'>
                      <p>If the church office has asked you to visit this page to activate your ChurchWeb account, please fill in this form.</p>
                      <p>We will assume that you are providing your personal email address. However, if you're a church staff member and prefer to receive ChurchWeb communication at work, give us your business email instead and check off the respective option.</p>
                    
                    </div>
                    <div style='width:310px;position:relative;margin-left:50px;margin-top:30px;'>
                      <form action='' method='POST'>
                        <table>
                          <tr>
                            <td>Your last name:</td>
                            <td><input type='text' id='last_name' name='last_name'/></td>
                          </tr>
                          <tr>
                            <td>Your first name:</td>
                            <td><input type='text' id='first_name' name='first_name'/></td>
                          </tr>
                          <tr>
                            <td>Your email address:</td>
                            <td><input type='text' id='email' name='email'/></td>
                          </tr>
                          <tr>
                            <td colspan='2'>
                              <input type='checkbox' name='is_business_email'/> This is a business email address
                            </td>
                          </tr>
                          <tr>
                            <td><input type='submit' name='action' value='Request account activation' style='margin-top:20px;'></td>
                            <td></td>
                          </tr>
                        </table>
                      </form>
                    </div>
                  </div>
                </div>
                ");
        $p->jquery("$('#last_name').focus();");      
      }
    }  
  } else {
    $p->error("ChurchWeb login is currently blocked for system maintenance. We apologize for any inconvenience. Please check back soon.");  
  } 

    

?>