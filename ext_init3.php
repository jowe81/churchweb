<?php


        //Ensure automatic inclusion of classfiles
        function __autoload($name){
          require_once CW_ROOT_UNIX."lib/class_".$name.".php";
        }

  require_once "lib/constants.php";
  require_once "lib/utilities_misc.php";

  DEFINE("CW_BACKUP_DIR","/home/johannes/public_html/dev/cw2_auto_backup/");
  DEFINE("LOG_FAIL","<span style='color:red;'>FAIL</span>");
  DEFINE("LOG_OK","<span style='color:green;'>OK</span>");
  
  DEFINE("MUSICDB_TABLES_SPACED_LIST","music_pieces writers writers_to_music_pieces writer_capacities arrangements writers_to_arrangements lyrics fragment_types instruments files_to_instruments_and_arrangements instruments_to_positions copyright_holders copyright_holders_to_music_pieces themes themes_to_music_pieces style_tags style_tags_to_arrangements keychanges_to_arrangements scripture_refs scripture_refs_to_music_pieces");

  //Set timezone
  date_default_timezone_set(CW_TIME_ZONE);


  function out($t,$indent_level=0){
    if ($indent_level>0){
      $q=str_repeat("&nbsp;",$indent_level*8);    
    } else {
      $q="";
      //Wrap unindented lines in bold span
      $t="<span style='font-weight:bold;'>$t</span>";    
    }
    
    echo "$q$t<br>";
  }

  //Instantiate all the classes that have a recreate_tables() method and return as array
  function get_cw_objects(){  
    $d=new cw_Db();
    $a=new cw_Auth($d);    
    $cwobjects=array();
    $cwobjects[]=new cw_Users($d);
    $cwobjects[]=new cw_Permissions($d);
    $cwobjects[]=new cw_Groups($d);
    $cwobjects[]=new cw_Group_memberships($d);
    $cwobjects[]=new cw_Services($d);
    $cwobjects[]=new cw_Personal_records($d);
    $cwobjects[]=new cw_Church_directory($d);
    $cwobjects[]=new cw_Sessions($d);
    $cwobjects[]=new cw_User_preferences($d);
    $cwobjects[]=new cw_Rooms($d);
    $cwobjects[]=new cw_Room_bookings($d);
    $cwobjects[]=new cw_Event_handling($a);
    $cwobjects[]=new cw_Auto_confirm($d);
    $cwobjects[]=new cw_Scripture_handling($d);
    $cwobjects[]=new cw_Music_db($a);
    $cwobjects[]=new cw_Files($d);
    $cwobjects[]=new cw_Help($d);
    $cwobjects[]=new cw_Changelog($a);
    $cwobjects[]=new cw_System_preferences($a);
    $cwobjects[]=new cw_Conversations($a);
    $cwobjects[]=new cw_Coffee_orders($a,null);
    $cwobjects[]=new cw_Mediabase($a);
    return $cwobjects;  
  }

  //If param is true, this will create a clean new cw system, with no files in the filebase and all the default records generated.
  //If param is false, the tables will be dropped and only the table structure will be recreated. The files in the filebase won't be touched. 
  function recreate_all_tables($create_default_records=true){ //For cw_files this parameter means to remove prexisting files (end recreate an empty table)
    $create_default_records ? $t="(create default records)" : $t="(do not create default records)";
    out("executing table-reinit $t...");
    $cwobjects=get_cw_objects();
    if ($cwobjects!=null){
      out("attempting instantiation of cw classes...".LOG_OK,1);
      foreach ($cwobjects as $v){
        $res=$v->recreate_tables($create_default_records);
        $classname=get_class($v);
        if ($res){
          out("init $classname...".LOG_OK,1);
        } else {
          out("init $classname...".LOG_FAIL,1);      
        }
      }  
    } else {
      out("attempting instantiation of cw classes...".LOG_FAIL,1);    
    }
    out("table-reinit finished");          
  }


  /*
      $_GET["action"] may have these values for the following tasks:
      
      "reinit_clear"
          Everything will be dropped and removed with fresh tables and NO default records inserted.          
            
  */
  
  function get_backup_dir($timestamp,$type="full"){
    $backup_dir=CW_BACKUP_DIR."cw_".$type."_backup_".$timestamp."/";
    if (!is_dir($backup_dir)){
      //Dir doesn't exist - attempt to create
      mkdir($backup_dir);
    }
    if (is_dir($backup_dir)){
      return $backup_dir;
    }
    //Failed to create backup directory
    return false;
  }

  function get_latest_existing_backup_dir($type="full"){
    $files=scandir(CW_BACKUP_DIR);
    $dir_prefix="cw_".$type."_backup_";
    while ((substr($x,0,strlen($dir_prefix))!=$dir_prefix) && sizeof($files)>0){
      $x=array_pop($files);
    }
    if (substr($x,0,strlen($dir_prefix))==$dir_prefix){
      //Found result
      return CW_BACKUP_DIR.$x."/";
    }
    return false;      
  }
  
  //Test whether the sql file was generated and whether all files from the source directory (CW_ROOT_UNIX) exist in the destination $backup_dir 
  function check_new_backup_integrity($backup_dir){
    //to implement
    return true;  
  }
  
  function drop_tables($spaced_list){
    $tables=explode(" ",$spaced_list);
    $d=new cw_Db();        
    foreach ($tables as $v){
      if (!$d->drop_table($v)){
        out($v." ".LOG_FAIL,2);        
        return false;
      } else {
        out($v." ".LOG_OK,2);        
      }
    }
    return true;
  }
  
  if ($_GET["action"]=="reinit_clear") {
    recreate_all_tables(false);
  }  
  
  if (empty($_GET["action"])){
    $notice="
      <p>ChurchWeb Backup and restore tool</p>
      <p>
        Options (via GET):
        <ul>
          <li><a href='?action=reinit_clear'>action=reinit_clear</a>: clear database, recreate table structures, no default records</li>
        </ul>
      </p>
    ";
    out($notice);
  }


?>