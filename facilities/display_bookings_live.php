<?php

  require_once "../lib/framework.php";

  //Load styles
  $p->stylesheet(CW_ROOT_WEB."css/display_bookings_live.css");

  if ($_GET["action"]==""){  
    $p->p("            
      <div>
        <div style='padding:5px;'>
          <div id='topline_container'>

            <div class='topline' id='booking_clock_container'>
              <div id='booking_clock'>
              </div>
              <div id='booking_clock_date'>
                loading...
              </div>
            </div>

            <div class='topline'>
              Latest booking today ends:
              <div id='latest_data'>loading...</div>
            </div>

            <div class='topline'>
              Earliest booking tomorrow starts:
              <div id='earliest_data'>loading...</div>
            </div>

            <div class='topline'>
              No longer used today:
              <div id='no_longer_used'>loading...</div>
            </div>

            <div class='topline'>
              Currently in use:
              <div id='currently_used'>loading...</div>
            </div>

            <div class='topline'>
              Used later today:
              <div id='used_later'>loading...</div>
            </div>


          </div>
          <div id='display_container'>
            <div id='left_display_container'>
              <div class='display_header'>
                Over (today)
              </div>
              <div class='display_data' id='over'>
                loading...
              </div>
            </div>
            <div id='center_display_container'>
              <div class='display_header'>
                In Progress
              </div>
              <div class='display_data' id='in_progress'>
              </div>
            </div>
            <div id='right_display_container'>
              <div class='display_header'>
                Next up (today)
              </div>
              <div class='display_data' id='next_up'>              
              <div>
            </div>
          </div>
          <div id='bookings_list'>
          </div>
        </div>
      </div>      
    ");
    //Ajax in the list of booking
    $p->jquery("
            
      reload_all();
            
      function reload_all(){
        reload_over();
        reload_in_progress();
        reload_next_up();
        reload_latest_today();
        reload_earliest_tomorrow();
        reload_no_longer_used();
        reload_currently_used();
        reload_used_later();
        setTimeout(reload_all,".(60)."*1000);            
      }
      
      function reload_over(){
        $('#over').load('".CW_AJAX."ajax_display_bookings_live.php?action=get_over');
      }
      
      function reload_in_progress(){
        $('#in_progress').load('".CW_AJAX."ajax_display_bookings_live.php?action=get_in_progress');      
      }
      
      function reload_next_up(){
        $('#next_up').load('".CW_AJAX."ajax_display_bookings_live.php?action=get_next_up');      
      }
            
      function reload_latest_today(){
        $('#latest_data').load('".CW_AJAX."ajax_display_bookings_live.php?action=get_latest_today');
      }
      
      function reload_earliest_tomorrow(){
        $('#earliest_data').load('".CW_AJAX."ajax_display_bookings_live.php?action=get_earliest_tomorrow');      
      }
      
      function reload_no_longer_used(){
        $('#no_longer_used').load('".CW_AJAX."ajax_display_bookings_live.php?action=get_no_longer_used');            
      }
      
      function reload_currently_used(){
        $('#currently_used').load('".CW_AJAX."ajax_display_bookings_live.php?action=get_currently_used');                  
      }

      function reload_used_later(){
        $('#used_later').load('".CW_AJAX."ajax_display_bookings_live.php?action=get_used_later');                  
      }
      
    ");
    
    $p->js("

      function startTime()
      {      
        syncedts=new Date((new Date()).getTime()+offset);
        var dd=syncedts.getDay();
        var dm=syncedts.getMonth();
        var dy=syncedts.getDate();
        var h=syncedts.getHours();
        var ampm='am';
        if (h>11){
          ampm='pm';
        }
        if (h==0){
          h=12; 
        }
        if (h>12){
          h=h-12;
        }
        var m=syncedts.getMinutes();
        var s=syncedts.getSeconds();
        // add a zero in front of numbers<10
        m=checkTime(m);
        h=checkTime(h);
        s=checkTime(s);

        var d_names = new Array('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday');
        var m_names = new Array('January','February','March','April','May','June','July','August','September','October','November','December');

        $('#booking_clock').html( h+':'+m+':'+s+' '+ampm );
        $('#booking_clock_date').html(d_names[dd]+', '+m_names[dm]+' '+dy);
        //Want the next repeat to occur just after the trailing amount of milliseconds (on local clock)
        ms_current=(new Date()).getMilliseconds();
        var next_interval;
        if (ms_current>ms_reference){
          next_interval=ms_reference+1000-ms_current;
        } else {
          next_interval=ms_reference-ms_current;
        }        
        t=setTimeout(\"startTime()\",next_interval+5);
      }
      
      
      
      //initTime(); no need to call initTime from here - it's defined and called in framework.php      
      startTime();
      
    
    
    ");
  }



?>