<?php
  //This file called by keep_alive() in the framework
  
  require_once "../lib/framework.php";
  
  if ($_GET["action"]=="get_clock"){
    //Output js clock with server time by determining offset to local time upon loading, sync to second by releasing clock at the moment the unix timestamp changes
    //Got timezone name in $_GET["timezone"] - need to determine offset to current timezone
    $ts=time();
    
    while ($ts==time()){
      //do nothing, wait until ts changes
    }
    
    $dot_width=13;
    $t="
      <div id=\"church_clock_time\">".date("h:i",time()+CW_CHURCH_TIME_OFFSET)."</div>
      <div id=\"church_clock_dots\">      
        <img src=\"".CW_ROOT_WEB."img/clockdot_bg.gif\" style=\"width:".($dot_width*60)."px;height:2px;\">
      </div>
      <div id=\"church_clock_date\">".date("l, F j",time()+CW_CHURCH_TIME_OFFSET)."</div>
      <script type='text/javascript'>
            
      
      function startTime()
      {      
        syncedts=new Date((new Date()).getTime()+offset);
        var dd=syncedts.getDay();
        var dm=syncedts.getMonth();
        var dy=syncedts.getDate();
        var h=syncedts.getHours();
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

        var d_names = new Array('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday');
        var m_names = new Array('January','February','March','April','May','June','July','August','September','October','November','December');

        $('#church_clock').html(
          '<div id=\"church_clock_time\">'+h+':'+m+\"</div>\"
          +'<div id=\"church_clock_dots\">'
          +'<img src=\"".CW_ROOT_WEB."img/clockdot.gif\" style=\"width:'+($dot_width*(s+1))+'px;height:2px;\">'
          +'<img src=\"".CW_ROOT_WEB."img/clockdot_bg.gif\" style=\"width:'+($dot_width*(60-(s+1)))+'px;height:2px;\">'
          +'</div>'
          +'<div id=\"church_clock_date\">'+d_names[dd]+', '+m_names[dm]+' '+dy+'</div>'
        );
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
      setTimeout(\"startTime()\",6000); //Start after fadein
      
      
      </script>      
    ";
    echo $t;  
  } elseif ($_GET["action"]=="conversation_add_contribution"){
    //This processes all conversations. $_POST has "conversation_id", "note_no" and "content"
    $cv=new cw_Conversations($a);
    if ($cv->add_contribution($_POST["content"],$_POST["conversation_id"],$_POST["note_no"])){
      echo "OK";
    } else {
      echo "Error: could not add contribution.";
    }
  } elseif ($_GET["action"]=="conversation_full_reload"){
    //Have $_GET["conversation_id"]
    $cv=new cw_Conversations($a);
    $dcv=new cw_Display_conversations($cv);
    echo $dcv->display_conversation($_GET["conversation_id"],true); //omit wrapper this time!
  } elseif ($_GET["action"]=="get_new_contributions"){
    //Display new contributions in the specified note.
    //Have $_GET["latest_loaded_contribution_sql_id"], which means we can find out contribution_id and note_no from that record
    $cv=new cw_Conversations($a);
    $dcv=new cw_Display_conversations($cv);
    echo $_GET["n"]." ".$dcv->display_new_contributions($_GET["latest_loaded_contribution_sql_id"]);
  } elseif ($_GET["action"]=="check_for_conversation_update"){
    //Have $_GET["conversation_id"] and $_GET["latest_loaded_contribution_sql_ids"], comma separated string
    //These are the ids of the latest loaded contribs from each note - note_no and conversation_id are, of course, inherent    
    $cv=new cw_Conversations($a);
    if (($cv->check_sync_mark($_GET["conversation_id"]))===false){
      $result="REQUEST_RELOAD";
    } else {
      $sql_ids=explode(",",$_GET["latest_loaded_contribution_sql_ids"]);
      $result="";
      $cv=new cw_Conversations($a);
      foreach ($sql_ids as $v){
        if ($cv->check_for_new_contributions($v)){
          $result.=",".$cv->get_note_no_from_contribution($v);
        }    
      }
      if (!empty($result)){
        $result=substr($result,1); //cut first comma;
      }
      //Now $result has the note_nos of those notes that need updating
    }
    echo $result;
  } elseif ($_GET["action"]=="conversation_add_note"){
    $cv=new cw_Conversations($a);
    if ($cv->add_contribution($_POST["content"],$_POST["conversation_id"])){
      echo "OK";
    } else {
      echo "Error: could not add note";
    }   
  } elseif ($_GET["action"]=="conversation_delete_contribution"){
    //have $_GET["contribution_id"]
    $cv=new cw_Conversations($a);
    if ($cv->drop_contribution($_GET["contribution_id"])){
      echo "OK";
    } else {
      echo "Error: could not delete this contribution";
    }
  } elseif ($_GET["action"]=="dismiss_welcome_message"){
    $upref=new cw_User_preferences($d,$a->cuid);
    $upref->write_pref($_GET["service_id"],"WELCOME_MESSAGE_DISMISSED","1");    
  } else {
    //keepalive response
    echo "ALIVE";
  }
   
  $p->nodisplay=true;

?>