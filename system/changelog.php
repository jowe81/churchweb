<?php

  require_once "../lib/framework.php";

  $p->stylesheet(CW_ROOT_WEB."css/changelog.css");

  $cl=new cw_Changelog($a);
  $dcl=new cw_Display_changelog($cl);
  
  /* Show top links regardless of sub context */

  $add_version="";
  if ($a->csps>=CW_A){
    $add_version="
    <td>
      <a id='add_version'>
        Add system version
      </a>
    </td>
    <script type='text/javascript'>
      $('#add_version').click(function(){
        if ((version_name = prompt('Type new version name (current is ".$cl->get_current_version_name()."):', ''))){                
          $.post('".CW_AJAX."ajax_changelog.php?action=add_version',{ version_name:version_name },function(rtn){
            if (rtn!='OK'){
              alert(rtn);
            } else {
              alert('New version created. Hit F5 to reload.');
            }                                  
          });
        }
      });                      
    </script>                    
    ";
  }
  
  $top="
    <table id='submenu_table'>
      <tr>
        <td>
          <a href=\"?action=\">
            Changelog Home
          </a>
        </td>
        <td>
          <a href=\"?action=new_ticket_form&show_ticket_form=true\">
            Submit a report
          </a>
        </td>
        <td>
          <a href=\"?action=go_to_version&version_id=\">
            View open reports
          </a>
        </td>
        <td>
          <a href=\"?action=my_tickets\">
            View my reports
          </a>
        </td>
        <td style='width:450px;'></td>
        $add_version
      </tr>
    </table>        
  ";
  
  $p->p($top);
  
  if (!isset($_GET["action"]) || ($_GET["action"]=="")){
    $p->p("
      <h3>Changelog home / development history</h3>  
    ");
    $p->p("
      <div class='expl'>
        <p>The changelog service does two things.</p>
        <p>1. It informs you about changes that have been made to the system with each upgrade (use the links on the left to view these)</p>
        <p>2. It gives you an opportunity to help with the development of ChurchWeb. If you have noted a bug or have an idea for a future feature, please let us know by submitting a bug report or feature request</p>
      </div>    
    ");
    $p->p("<div style='float:left;width:750px;'><ul>");
    $versions=$d->get_table("system_versions","id DESC");
    if (is_array($versions)){
      foreach ($versions as $v){
        if ($v["id"]>0){
          $p->li("<a href=\"?action=go_to_version&version_id=".$v["id"]."\">View changelog for version ".$v["version_name"].", deployed ".date("l, F j, Y",$v["deployed_at"])."</a>");                          
        } else {
          $p->li("ChurchWeb with the Changelog service was installed on this machine on ".date("l, F j, Y",$v["deployed_at"])." (Changelog was added in version 0.2)");                        
        }
      }
    } else {
      $p->li("<span style='color:red;'>Error: could not retrieve version history</span>");    
    }
    if ($a->csps>=CW_A) {
        $p->li("<a href=\"?action=recreate_tables\">Recreate tables</a>");
    }
    $p->p("</ul></div>");
  } elseif ($_GET["action"]=="new_ticket_form") {
    $p->p("
      <h3>Compose a new bug report or feature request</h3>  
    ");
    $p->p($dcl->get_new_ticket_interface());
  } elseif ($_GET["action"]=="edit_ticket") {
    $p->p("
      <h3>Edit a bug report or feature request</h3>  
    ");
    $p->p($dcl->get_edit_ticket_interface($_GET["ticket_id"],"?action=go_to_version&version_id=".$_GET["version_id"]."&submit=update"));
  } elseif ($_GET["action"]=="go_to_version") {
    //version_id given? If not, show current reports
    $v=$cl->get_version_name($_GET["version_id"]);
    if ($v>0){
      $p->p("<h3>Changelog for version $v</h3>");
    } else {
      $p->p("<h3>Currently open bug reports and feature requests</h3>");        
    }
    //Was a form submitted? If so, process it
    if ($_GET["submit"]=="yes"){
      if ($ticket_nr=$cl->add_ticket($_POST["f_ticket_type"],$_POST["f_ticket_title"],$_POST["f_ticket_description"],$_POST["f_service"],$_POST["f_suggested_priority"])){
        $p->js("alert('Thank you for submitting report #$ticket_nr.');");    
      } else {
        $p->js("alert('Error: report submission failed! Make sure you fill in all fields.');");    
      }    
    }
    if ($_GET["submit"]=="update"){
      $e=array();
      $e["type"]=$_POST["f_ticket_type"];
      $e["title"]=$_POST["f_ticket_title"];
      $e["description"]=$_POST["f_ticket_description"];
      $e["service"]=$_POST["f_service"];
      $e["suggested_priority"]=$_POST["f_suggested_priority"];
      $e["accepted_priority"]=$_POST["f_accepted_priority"];
      if ($cl->update_ticket_record($_POST["ticket_id"],$e)){
        $p->js("alert('Thank you for updating report #".$_POST["ticket_id"].".');");    
      } else {
        $p->js("alert('Error: report update failed!');");    
      }    
    }
    //Load tickets by AJAX
    $p->p("
      <div id='tickets'>loading...</div>
      <script type='text/javascript'>
        $('#tickets').load('".CW_AJAX."ajax_changelog.php?action=get_tickets_for_version&version_id=".$_GET["version_id"]."');
      </script>
    ");    
  } elseif ($_GET["action"]=="my_tickets") {
    $p->p("<h3>Reports I have submitted</h3>");
    //Load tickets by AJAX
    $p->p("
      <div id='tickets'>loading...</div>
      <script type='text/javascript'>
        $('#tickets').load('".CW_AJAX."ajax_changelog.php?action=get_my_tickets');
      </script>
    ");
    /*    
    $tickets=$cl->get_my_tickets();
    if ($tickets!==false){
      $p->p("Found ".sizeof($tickets)." ticket(s)");                                                  
      foreach ($tickets as $ticket){
        $p->p($dcl->display_ticket_record($ticket));
      }                                             
    } else {
      $p->p("Could not load tickets. <a href='?action='>Return</a>.");                  
    }  
    */        
  } elseif ($_GET["action"]=="recreate_tables") {
    if ($a->csps>=CW_A){
      if ($cl->recreate_tables(true)){
        $p->p("Success. <a href='?action='>Return</a>.");
        $p->p("<br/>Current version ID: ".$cl->current_version_record["id"]." (".$cl->get_current_version_name().")");
      } else {
        $p->p("Failed. <a href='?action='>Return</a>.");    
      }
    } else {
      $p->p(CW_ERR_INSUFFICIENT_PRIVILEGES);
    }
  } elseif ($_GET["action"]=="") {
  }


    
  
?>