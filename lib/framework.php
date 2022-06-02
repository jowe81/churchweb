<?php
  /* CW-Framework must be included at the top of ALL project files */

  //Load constants and utility libraries
  require_once "constants.php";
  require_once "utilities_dates.php";
  require_once "utilities_misc.php";
  require_once "utilities_jquery.php";
  
        //Ensure automatic inclusion of classfiles
        function __autoload($name){
          if (substr($name,0,3)!="PHP"){
            require_once CW_ROOT_UNIX."lib/class_".$name.".php";          
          }
        }

  //Define authorization-free pages
  $no_auth_pages=array(CW_ROOT_UNIX."login.php",CW_ROOT_UNIX.CW_SERVICE_PLANNING_AUTO_CONFIRM_SCRIPT);
    
  //Set timezone
  date_default_timezone_set(CW_TIME_ZONE);
   
  //Init session
  session_start();
      
  /*
    Three global objects exist in CW:
    $p (page), $d (database access), $a (authentication)
    In short: P-D-A
  */

  //Create page object with defaults
  $p=new cw_Page(); 
  $p->title=CW_TITLE;
  //Embed CSS scripts
  $p->stylesheet(CW_ROOT_WEB."css/jquery-ui.css");
  $p->stylesheet(CW_ROOT_WEB."css/framework.css");
  $p->stylesheet(CW_ROOT_WEB."css/menu.css");
  $p->stylesheet(CW_ROOT_WEB."css/styles.css");
  //Adjust content container height with js
  $p->jquery("
    $(window).resize(function(){
      $('#main_content').height((window.innerHeight-43).toString());
    });
    $(window).resize();
  ");  

  //Create database object
  $d=new cw_Db();
  
  //Create auth object
  $a=new cw_Auth($d);
  
  //Create personal records object
  $pr=new cw_Personal_records($d);
                                  
  //Skip authorization and menu generation if this is the login script
  if (!in_array($_SERVER["SCRIPT_FILENAME"],$no_auth_pages)){      
    //Produce the home logo and link on top left
    $p->logo("<a href='".CW_HOME."'><img id=\"cw_logo\" src=\"".CW_ROOT_WEB.CW_CHURCH_LOGO_BUTTON."\"/></a>");
    //-----Process authorization-------------------------
    $a->csid=$a->services->get_service_id_for_file($_SERVER["SCRIPT_FILENAME"]);
    //Load the previously stored (upon Login) array of permissions back into $a->my_permitted_services
    $a->my_permitted_services=$_SESSION["my_permitted_services"];
    //Attempt to validate the current request
    $auth_result=$a->validate_request($_SESSION["session_id"],$a->csid);
    //Auth result must contain constant CW_SESSION_VALIDATED_SUCCESSFULLY 
    if ($auth_result!=CW_SESSION_VALIDATED_SUCCESSFULLY){
      //Authorization failed
      //In case of an ajax file, display text only
      if ($a->services->is_ajax_service($a->csid)){
        $p->nodisplay=true;
        echo "You do not have permission to do this";    
      } else {
        $p->error("Authorization problem. Error code [$auth_result]",array(CW_LOGIN=>"Go to login"));
      }
      die; //Terminate here after unsuccessful authorization attempt  
    } else {
      //Authorization successful
      //Store permission status in $a
      $a->csps=$a->my_permitted_services[$a->csid];
      //Store user id in $a
      $a->cuid=$_SESSION["person_id"]; //Put here by login.php
      //Produce logout link, clock
      $p->quick_links("<div id=\"clock_logout\" style=\"cursor:pointer;\">
                        <div id=\"logout\" style=\"display:none;\"><a href=\"".CW_LOGOUT."\">Logout</a></div>
                        <div id=\"menuclock\">".date("h:i A",time()+CW_CHURCH_TIME_OFFSET)."</div>
                      </div>");
      $p->quick_links("<div id=\"current_user_info\">
                        ".$pr->get_name_first_last($_SESSION["person_id"])."<br/>
                        Status: ".$a->permissions->int_to_full_permission_type($a->csps)."
                       </div>");
      //Create menu object
      $m=new cw_Menus($a); 
      //Produce #main_menu and #service_title(one of them might stay hidden but both are always produced)     
      $p->main_menu($m->display_menus_for_first_level_children(1,$_SESSION["person_id"],"nav",2));
      $p->service_title($a->services->display_path_to_service($a->csid));  
      if ($a->csid!=CW_HOME_SERVICE_ID){
        //If we're not on home, we have #service_title along the top (#main_menu upon mouse over) 
        $p->jquery("
            $('#main_menu').hide();
            $('#service_title').mouseover(function() {
              $('#main_menu').show();
              $('#service_title').hide();      
            });            
            $('#main_content').mouseover(function() {
              $('#service_title').show();
              $('#main_menu').hide();
            });      
        ");
        //We also want the logout link only on hovering over the clock
        $p->jquery("
            $('#clock_logout').mouseenter(function(){
              $('#logout').show();
              $('#menuclock').hide();
            }).mouseleave(function(){
              $('#logout').hide();
              $('#menuclock').show();
            });
        ");
        //Submenu
        $sub_menu_root=$a->csid; //Assume that we'll display sub menu from here on down
        if (!$a->services->service_is_parent($a->csid)){
          //But this is a leaf - so display submenu for current parent (i.e. the sub menu items are neighboring services, not children)
          $sub_menu_root=$a->services->get_parent($a->csid);
        }
        $p->sub_menu($m->display_menus_for_first_level_children($sub_menu_root,$_SESSION["person_id"],"nav_sub",0,true)); //Last parameter avoids display of parent nodes in the submenu       
      } else {
        $p->jquery("
          $('#service_title').hide();
          $('#menuclock').hide();
          $('#logout').show();
        ");      
      }
      //Set keep-alive, and start clock
      $p->js("
        function keep_alive(){
          $.get('".CW_AJAX."ajax_home.php',function(rtn){
          });
        }
        setInterval('keep_alive()',".(CW_KEEP_ALIVE*1000).");
        
        //Clock
        
        var offset;
        var syncedts;
        var ms_reference=(new Date()).getMilliseconds();

        function initTime(){
          var server_ts=".(time()-$_SESSION["server_time_offset"]+CW_CHURCH_TIME_OFFSET)."*1000;
          var local_ts=(new Date()).getTime();
          offset=server_ts-local_ts;
        }
        
        function checkTime(i)
        {
          if (i<10){
            i='0' + i;
          }
          return i;
        }

        
        function startTime_menuclock(){
          syncedts=new Date((new Date()).getTime()+offset);
          var h=syncedts.getHours();
          var ampm=' AM';
          if (h==0){
            h=12;  
          }
          if (h>12){
            h=h-12;
            ampm=' PM';
          }
          var m=syncedts.getMinutes();
          var s=syncedts.getSeconds();
          // add a zero in front of numbers<10
          m=checkTime(m);
          h=checkTime(h);
          $('#menuclock').html(h+':'+m+ampm);
          //Want the next repeat to occur just after the trailing amount of seconds (on server)
          var next_interval=(60-s)*1000; //next full minute on server          
          t=setTimeout(\"startTime_menuclock()\",next_interval+5);        
        }
        
        initTime(); //This call for both the menu clock as well as the big one on home.php
        startTime_menuclock();  
        
        //horizontal offset for simpletip tooltips
        
        var tip_offset;
        
        function get_tip_offset(){
          tip_offset=0;
          if ($(window).width()>1250){
            tip_offset=-(($(window).width()-1250)/2);
          }
          tip_offset=tip_offset-20;        
        }
        
        get_tip_offset();
        
        //help system
        var help_window;
        
        function get_help(ticket,width,height){
          var w=300;
          var h=400;
          if (width){
            w=width;
          }
          if (height){
            h=height;
          }
          var loc='".CW_ROOT_WEB."home.php?action=get_help&ticket='+ticket;
          if ((help_window) && (!(help_window.closed))){
            help_window.location.replace(loc);
            help_window.focus(); 
          } else {
            help_window=window.open(loc,'','left=50,top=50,width='+w+',height='+h+',location=no,menubar=no,status=no,toolbar=no');
          }  
        }        
      ");
    }
  } else {
    //On Login page show welcome message up top
    //Empty top left
    $p->logo(" ");
    $p->jquery("$('#main_menu').hide();");      
    $p->service_title("<div style='text-align:center;color:#CCC;'>Welcome to ChurchWeb at ".CW_CHURCH_NAME."</div>");  
  }  
  
  
 

   
?>