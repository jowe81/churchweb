<?php

  require_once "lib/framework.php";

    
  
  if (!isset($_GET["action"]) || ($_GET["action"]!="get_help")){
    $p->p("<div style='' id='church_clock'></div>");
    $p->jquery("
                                  
      function clock_fade_in(){
        $('#church_clock').fadeIn(5500);
      }
      
      $('#church_clock').hide();
      $('#church_clock').load('".CW_AJAX."ajax_home.php?action=get_clock',function(){
        //setTimeout(clock_fade_in(),5000);
        setTimeout(function(){clock_fade_in()},3000);
      });
    
    ");
    if ($a->users->get_login_count($a->users->get_loginname($a->cuid))==1){
      $upref=new cw_User_preferences($d,$a->cuid);
      if (!$upref->read_pref($a->csid,"WELCOME_MESSAGE_DISMISSED"))
      $p->p("
          <div id='welcome_message' class='expl' style='position:absolute;left:210px;width:780px;top:320px;height:300px;font-size:100%;border:1px solid gray;'>
            <div>
              <p style='font-size:130;font-weight:bold;'>Welcome, ".$a->personal_records->get_first_name($a->cuid)."!</p>
              <p>
                Thank you for logging into your ChurchWeb account for the first time.
                We hope that you will quickly figure out how to use this platform and that it will be helpful to you in your ministry.
              </p>
              <p>
                Look for info-boxes like this one if you need help with something.
              </p>
              <p>
                In some places you will only find a little clickable square with a questionmark, like this: <img id='get_help' src='".CW_ROOT_WEB."img/help.gif'/>
              </p>
              <p>
                Please note that ChurchWeb works best in Firefox, but will perform in Internet Explorer, Chrome and Safari
              </p>
            </div>
            <div ><a class='' id='dismiss' href=''>dismiss this message</a></div>
            
          </div>                                            
        ");      
        
      $p->jquery("
        $('#dismiss').click(function(e){
          e.preventDefault();
          $('#welcome_message').remove();
          $.get('".CW_AJAX."ajax_home.php?action=dismiss_welcome_message&service_id=".$a->csid."');          
        });
        
        $('#get_help').click(function(){
          get_help(3);
        });
      ");
    } else {
      /* Applicable system messages (only from 2nd login) */
      $upref=new cw_User_preferences($d,$a->cuid);
      $spref=new cw_System_preferences($a);
      //
      if ($_GET["action"]=="dismiss_message"){
        $upref->write_pref($a->csid,"LAST_SYSTEM_MESSAGE_DISMISSED",$_GET["message_id"]);
      }
      
      $messages=$spref->get_messages($upref->read_pref($a->csid,"LAST_SYSTEM_MESSAGE_DISMISSED"));
      if (is_array($messages)){
        if (sizeof($messages)>0){
          $p->stylesheet(CW_ROOT_WEB."css/home.css");
          $r=$messages[0];
          ($r["type"]==1) ? $type="<span style='color:red;'>warning</span>" : $type="<span style='color:lime;'>notice</span>";
          $t="
            <div class='header'>
              ".$a->personal_records->get_first_name($r["noted_by"])." wrote a $type on ".date("F j, g:ia",$r["noted_at"])."
              <div style='float:right;'><a href='?action=dismiss_message&message_id=".$r["id"]."'>dismiss message</a></div>
            </div>
            <div class='message'>
              <div class='subject'>
                ".htmlspecialchars($r["subject"])."
              </div>
              ".nl2br(htmlspecialchars($r["message"]))."
            </div>          
          ";
          $p->p("<div id='system_message_container'>$t</div>");
        }
      } else {
        $p->p("cannot load messages");
      }   
    }
  } elseif ($_GET["action"]=="get_help") {
    //Invoke help system
    $p->stylesheet(CW_ROOT_WEB."css/help.css");
    
    $help=new cw_Help($d);
    if ($r=$help->get_note($_GET["ticket"])){
      $p->p("<div id='help_container'><div>$r</div></div>");
      $p->js("document.title='ChurchWeb Help';");    
    } else {
      $p->p("Help system error: could not load ticket #".$_GET["ticket"]);
    }
    $p->no_page_framework=true;
  
  }


    
  
?>