<?php

  require_once "../lib/framework.php";

  $st=new cw_Services($d);

  //Was a form submitted?
  if (isset($_POST["action"])){
    if (($_POST["action"]=="Save service parameters") || ($_POST["action"]=="Save and add child service")){
      //Save service in either case
      $e=array();
      $e["title"]=$_POST["title"];
      $e["title_long"]=$_POST["title_long"];
      $e["type"]=$_POST["type"];
      $e["file"]=$_POST["file"];
      $e["show_in_menu"]=$_POST["show_in_menu"];
      $d->update_record("services","id",$_POST["id"],$e);
      //Create child service?
      if ($_POST["action"]=="Save and add child service"){
        $st->add_service($_POST["id"],"NEW SERVICE");
      }
    } elseif ($_POST["action"]=="Delete this service"){
      //Delete service
      $st->delete_service($_POST["id"]);  
    }
    //At any rate: back to display of tree
    $_GET["a"]="";     
  }
  
  //Reset the entire table to default
  if ($_GET["a"]=="reset"){
    $st->recreate_tables();  
  }

  //Move service horizontally   
  if ($_GET["a"]=="move_left"){
    $st->move_left($_GET["id"]);
  }
  if ($_GET["a"]=="move_right"){
    $st->move_right($_GET["id"]);
  }
  
  //Show form to edit service
  if ($_GET["a"]=="edit"){
    //Load preselected record
    $e=$d->get_record("services","id",$_GET["id"]);
    if ($e["show_in_menu"]!=0) {
      $sim_checked="CHECKED";
    }
    if ($e["type"]=='script'){
      $type_script_checked="CHECKED";      
    }
    if ($e["type"]=='node'){
      $type_node_checked="CHECKED";      
    }
    $p->p("<div>
            <h3>Service record</h3>
            <form action='' method='POST'>
              <table>
                <tr>
                  <td>Service ID (internal):</td>
                  <td>".$e["id"]."<input type='hidden' name='id' value='".$e["id"]."'/></td>
                </tr>
                <tr>
                  <td>Service title (short):</td>
                  <td><input type='text' name='title' value=\"".$e["title"]."\"></td>
                </tr>
                <tr>
                  <td>Full service title (description):</td>
                  <td><input type='text' name='title_long' value=\"".$e["title_long"]."\"></td>
                </tr>
                <tr>
                  <td>Service type:</td>
                  <td>
                    <input type='radio' name='type' value='node' $type_node_checked>node
                    <input type='radio' name='type' value='script' $type_script_checked>script
                  </td>
                </tr>
                <tr>
                  <td>Associated script (file):</td>
                  <td><input type='text' name='file' value=\"".$e["file"]."\"></td>
                </tr>
                <tr>
                  <td>Visibility:</td>
                  <td>
                    <input type='checkbox' name='show_in_menu' value='1' $sim_checked> Show in menu
                  </td>
                </tr>
              </table>
              <h4>Actions</h4>
              <input type='submit' name='action' value='Go back (do not save)'/>
              <input type='submit' name='action' value='Save service parameters'/>
              <input type='submit' name='action' value='Delete this service'/>                                          
              <input type='submit' name='action' value='Save and add child service'/>                                          
            </form>
           </div>");
  } else {
    //No specific action -> display tree
    $p->p("<h3>Service tree</h3>");
    $p->p($st->display_service_tree(1));
    $p->p("<a href='?a=reset'>Recreate table and reset to default</a>");
  }
?>