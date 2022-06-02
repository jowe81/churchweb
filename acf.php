<?php

  /* Login script must set $_SESSION vars upon successful login */
   
  require_once "lib/framework.php";
  $p->stylesheet(CW_ROOT_WEB."css/login.css");

  if (isset($_GET["a"])){
    
      $eh=new cw_Event_handling($a);
      
      if ($ac=$eh->auto_confirm->check_access_code($_GET["a"])){
        if ($ac["positions_to_services_id"]){
          //Confirm position
          if ($eh->confirm_position($ac)){
            if ($ac["val"]){
              $msg="Thank you for your confirmation to serve!";            
            } else {
              $msg="We regret that you cannot serve with us this time, but thanks for letting us know!";            
            }
          }
        } elseif ($ac["rehearsal_participants_id"]){
          //Confirm rehearsal
          if ($eh->confirm_rehearsal($ac)){
            if ($ac["val"]){
              $msg="Thank you for confirming your rehearsal participation.";
            } else {
              $msg="We regret that you cannot make the rehearsal(s), but thanks for letting us know!";            
            }
          }
        } else {
          //Confirm all          
          if ($eh->confirm_all($ac)){
            if ($ac["val"]){
              $msg="Thank you for your confirmation to serve!";            
            } else {
              $msg="We regret that you cannot serve with us this time, but thanks for letting us know!";            
            }
          } else {
            $msg="<span class='red'>An error occurred - your response could not be processed. It is possible that your position in the service got cancelled. If in doubt, please connect with us manually.</span>";
          }
        }
      } else {
        $msg="Invalid confirmation code";        
      }
              
        $p->p("
                <div style='position:relative;width:800px;height:300px;top:50px;left:200px;background:#FFF;border:1px solid gray;'>
                  <div style='padding:5px;'>
                    <h4>Confirmation/declination of ministry invitations</h4>
                    <div class='expl'>
                      <p>Note that you can change your decision on the 'My Commitments' page in ChurchWeb. Go to <a href='login.php'>login</a></p>
                    </div>
                    <div style='width:310px;position:relative;margin-left:50px;margin-top:30px;'>
                      $msg
                    </div>
                  </div>
                </div>
                ");
              
      
  }
    

?>