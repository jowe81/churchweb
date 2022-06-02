<?php

  require_once "../lib/framework.php";

  $spref=new cw_System_preferences($a);
  
  $top="
    <table id='submenu_table'>
      <tr>
        <td>
          <a href=\"?action=\">
            Login status
          </a>
        </td>
        <td>
          <a href=\"?action=all_preferences\">
            All system preferences
          </a>
        </td>
        <td>
          <a href=\"?action=view_messages\">
            View system messages
          </a>
        </td>
        <td>
          <a href=\"?action=new_message\">
            New system message
          </a>
        </td>
        <td style='width:500px;'></td>
      </tr>
    </table>        
  ";
  
  $p->p($top);

  if (!isset($_GET["action"]) || ($_GET["action"]=="") || ($_GET["action"]=="toggle_login")){
    /* */
    if ($_GET["action"]=="toggle_login"){
      $spref->toggle_login_blockade();
    }
    ($spref->login_blocked()==1) ? $status="<span style='color:red;'>blocked</span>" : $status="<span style='color:green;'>available</span>";
    $p->p("<p>Login is currently $status.</p><p><a href=\"?action=toggle_login\">Toggle login status</a></p>");
    
  } elseif ($_GET["action"]=="all_preferences"){
    /* View / Edit Preferences */
    if ($_GET["save_preference"]=="yes"){
      $spref->write_pref($_POST["f_pref_name"],$_POST["f_pref_value"]);
    }
    $prefs=$spref->get_all_preferences();
    foreach ($prefs as $k=>$v){
      $t.="
        <tr style='background:#EEE;'>
          <form id='f".$v["id"]."' method=\"POST\" action=\"?action=all_preferences&save_preference=yes\">
            <td style='vertical-align:middle;width:200px;'>".$v["pref_name"]."<input type='hidden' name='f_pref_name' value=\"".$v["pref_name"]."\" /></td>
            <td style='vertical-align:middle;width:230px;'><input name='f_pref_value' style='width:200px;' value=\"".htmlspecialchars($v["pref_value"])."\"/></td>
            <td style='vertical-align:middle;'><input style='width:80px;' type='submit' value='save' class='b_save' id='p".$v["id"]."'/></td>
            <td style='vertical-align:middle;width:400px;'>".$v["description"]."</td>
            <td style='vertical-align:middle;width:200px;'>".$v["default_value"]."</td>
          </form>
        </tr>";
    }
    $t="
      <table>
        <tr>
          <th>
            preference name
          </th>
          <th>
            value
          </th>
          <th>
            
          </th>
          <th>
            description
          </th>
          <th>
            default value
          </th>
        </td>
        $t
      </table>      
    ";
    $p->p($t);  
  } elseif ($_GET["action"]=="view_messages"){
    //Cleanup
    $spref->delete_expired_messages();
    if ($_GET["submit_message"]=="yes"){
      //Message submitted
      if ($spref->add_message($_POST["f_subject"],$_POST["f_message"],$_POST["f_type"],(time()+($_POST["f_ttl_days"]*24*3600)))){
        /*$p->p("New message accepted");*/
      } else {
        $p->p("<span style='color:red;'>FAILED to save new message</span>");      
      }
    }
    //Delete message
    if ($_GET["delete_message"]>0){
      $spref->delete_message($_GET["delete_message"]);
    }
    
    $m=$spref->get_all_messages();
    if (is_array($m)){
      $t="";
      foreach ($m as $r){
        ($r["type"]==1) ? $type="<span style='color:red;'>warning</span>" : $type="<span style='color:green;'>notice</span>";
        
        $t.="
          <tr>
            <td style='width:80px;'>
              [<a href='?action=view_messages&delete_message=".$r["id"]."'>delete</a>]
            </td>
            <td>
              Author: ".$a->personal_records->get_name_first_last($r["noted_by"])."
            </td>
            <td>
              Type: $type
            </td>
            <td>
              Composed at: ".date("F j Y, h:ia",$r["noted_at"])."
            </td>
            <td>
              Expiry date: ".date("F j Y, h:ia",$r["expires"])."
            </td>
          </tr>
          <tr>
            <td>
              Subject:
            </td>
            <td colspan=\"4\">
              <span style='color:blue;font-weight:bold;'>".htmlspecialchars($r["subject"])."</span>
            </td>
          </tr>
          <tr style='border-bottom:1px solid gray;'>
            <td>
              Message:
            </td>
            <td colspan=\"4\">
              <span style='color:blue;'>".nl2br(htmlspecialchars($r["message"]))."</span>
            </td>
          </tr>
        ";
      }
      $p->p("<table style='font-size:80%;width:100%'>$t</table>");    
    } else {    
      $p->p("Error: could not load messages");
    }
  
  } elseif ($_GET["action"]=="new_message"){
    $p->p("
      <div style='margin:10px;padding:10px;background:#FFE;width:550px;'>
        <h4 style='padding-bottom:10px;'>Compose system message</h4>
        <form method='POST' action='?action=view_messages&submit_message=yes'>
          <table>
            <tr>
              <td style='padding-top:7px;'>Message subject:</td>
              <td><input name='f_subject' style='width:400px;' type='text' /></td>
            </tr>
            <tr>
              <td style='padding-top:7px;'>Message text:</td>
              <td><textarea name='f_message' style='width:400px;' ></textarea></td>
            </tr>
            <tr>
              <td style='padding-top:7px;'>Message type:</td>
              <td>
                <select name='f_type' >
                  <option value='0'>Notice</option>
                  <option value='1'>Warning</option>
                </select>
              </td>
            </tr>
            <tr>
              <td style='padding-top:7px;'>Expires after:</td>
              <td><input name='f_ttl_days' style='width:40px;' type='text' value='".$spref->read_pref("SYSTEM_MESSAGE_DEFAULT_TTL")."'/> days</td>
            </tr>
            <tr>
              <td>
              </td>
              <td>
                <input style='float:right;' type='submit' value='Submit'/><input style='float:right;' type='reset' value='Reset'/>
              </td>
            </tr>
          </table>
        </form>
      </div>    
    ");
  }


    
  
?>