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
      
      "backup"
          A full backup will be performed to $_GET["backup_dir"], if present, or to the default backup directory CW_BACKUP_DIR
      "update_table_structure"
          A backup will be performed as above, then the tables will be dropped and recreated and re-filled (doesn't move filebase)
      "reinit"
          Everything will be dropped and removed with fresh tables and default records inserted.          
      "export_musicdb"
          Performs backup of musicdb (all musicdb tables and related files)      
      "import_musicdb"
          Overwrites the present music_db with the latest backup  
          
      "restore"
          Restores latest full backup: all sql-data and also the CW_FILEBASE subdirectory
          
      "restore_everything" CAREFUL!
          Restores latest full backup as above, but including the entire cw2 directory (i.e., including all scripts)
            
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
  
  if (($_GET["action"]=="backup") || ($_GET["action"]=="update_table_structure")){
    //Perform backup. SQL gets dumped $backup_dir, and the filebase gets copied in there as well
    $backup_dir=get_backup_dir(time(),"full");
    out("performing full backup to $backup_dir");
    if (is_dir($backup_dir)){
      //dir exists, now back up sql
      out("backing up sql data...",1);
      //did the user submit a table to ignore?
      $ignore_option="";
      if (!empty($_GET["ignore"])){
        $ignore_option="--ignore-table=cw2.".$_GET["ignore"];
      }
      $command="mysqldump -t -c $ignore_option --user=".CW_MYSQL_USER." --password=".CW_MYSQL_PWD." ".CW_DATABASE_NAME." > ".$backup_dir."mysql_backup_".CW_DATABASE_NAME.".sql";
      out($command,2);
      exec($command);
      out("shell execution for backing up sql data triggered ".LOG_OK,1);
      //copy entire cw directory with sourcecode and filebase into $backup_dir
      $src = CW_ROOT_UNIX."*";
      $dest = $backup_dir;      
      exec("cp -r $src $dest");
      out("shell execution for copying cw directory triggered ".LOG_OK,1);
      //
      $backup_result=check_new_backup_integrity($backup_dir);
      if ($backup_result){
        out("testing backup integrity...".LOG_OK,1);
      } else {
        out("testing backup integrity...".LOG_FAIL,1);      
      }
    } else {
      out("backing up sql data ".LOG_FAIL.": could not create backup destination directory",1);
    }
    out("backup script finished");
  }
  
  if ($_GET["action"]=="reinit") {
    recreate_all_tables();
  }  
  
  if ($_GET["action"]=="restore_everything") {
    out("attempting full restoration of backup, including scripts");
    //remove entire directory tree under CW_ROOT_UNIX
    out("removing existing directory tree under ".CW_ROOT_UNIX,1);
    $files=new cw_Files($d);
    $files->remove_directory_tree(CW_ROOT_UNIX,true);
    //get latest backup
    $backup_dir=get_latest_existing_backup_dir("full");
    //copy entire cw directory with sourcecode and filebase back to CW_ROOT_UNIX
    $dest = CW_ROOT_UNIX;
    $src = $backup_dir."*";      
    exec("cp -r $src $dest");
    out("shell execution for copying cw directory triggered [$src]->[$dest]".LOG_OK,1);
    out("finished");   
  }  

  if ($_GET["action"]=="restore_data") {
    out("attempting restoration of data backup, including filebase");
    //remove directory tree under CW_ROOT_UNIX.CW_FILEBASE
    out("removing existing directory tree under ".CW_ROOT_UNIX.CW_FILEBASE,1);
    $files=new cw_Files($d);
    $files->remove_directory_tree(CW_ROOT_UNIX.CW_FILEBASE,true);
    //get latest backup
    $backup_dir=get_latest_existing_backup_dir("full");
    //copy filebase back to CW_ROOT_UNIX.CW_FILEBASE
    $dest = CW_ROOT_UNIX.CW_FILEBASE;
    $src = $backup_dir.CW_FILEBASE."*";      
    exec("cp -r $src $dest");
    out("shell execution for copying cw filebase directory triggered [$src]->[$dest]".LOG_OK,1);
    out("finished");   
  }  
  
  if ((($_GET["action"]=="update_table_structure") && $backup_result) || ($_GET["action"]=="restore_everything") || ($_GET["action"]=="restore_data")){
    //recreate tables without default records (and do not empty filebase)
    recreate_all_tables(false);
    //Now try to apply sql backup (nothing needs to be done with the physical files as they won't get removed with recreate_all_tables(FALSE))
    out("back-copying sql data...");
    $command="mysql --user=".CW_MYSQL_USER." --password=".CW_MYSQL_PWD." ".CW_DATABASE_NAME." < ".$backup_dir."mysql_backup_".CW_DATABASE_NAME.".sql";
    out($command,1);
    exec($command);
    out("shell execution for back-copying sql data triggered ".LOG_OK,1);    
    out("back-copying sql finished");
  }  

  if ($_GET["action"]=="export_musicdb") {
    $backup_dir=get_backup_dir(time(),"musicdb");  
    out("performing musicdb export to $backup_dir");
    if (is_dir($backup_dir)){
      //dir exists, now copy files
      $d=new cw_Db();
      $a=new cw_Auth($d);    
      $files=new cw_Files($d);
      $mdb=new cw_Music_db($a);
      $all_musicdbfile_ids=$mdb->get_all_musicdb_file_ids();
      if (is_array($all_musicdbfile_ids)){
        //Copy the files into the backup directory
        out("copying ".sizeof($all_musicdbfile_ids)." files to $backup_dir",1);
        foreach ($all_musicdbfile_ids as $v){
          if (!$files->copy_file($v,$backup_dir,true)){
            out("file-id $v ".LOG_FAIL,1);            
          }
        }
        //now back up sql
        out("backing up sql data...",1);
        //All music_db tables
        $tables_to_dump=MUSICDB_TABLES_SPACED_LIST;
        $command="mysqldump -t -c --user=".CW_MYSQL_USER." --password=".CW_MYSQL_PWD." ".CW_DATABASE_NAME." $tables_to_dump > ".$backup_dir."mysql_musicdb_backup_".CW_DATABASE_NAME.".sql";
        out($command,2);
        exec($command);
        out("shell execution for backing up sql data (musicdb) triggered ".LOG_OK,1);      
      } else {
        out("obtaining file-ids for musicdb files ".LOG_FAIL,1);                
      }
      
    }
    out("musicdb export finished");
  }  


  if ($_GET["action"]=="import_musicdb") {
    out("performing musicdb import");
    if (!isset($_GET["timestamp"])){
      $files=scandir(CW_BACKUP_DIR);
      $dir_prefix="cw_musicdb_backup_";
      while ((substr($x,0,strlen($dir_prefix))!=$dir_prefix) && sizeof($files)>0){
        $x=array_pop($files);
      }
      if (substr($x,0,strlen($dir_prefix))==$dir_prefix){
        //Found musicdb backup
        out("locating latest musicdb backup... ".LOG_OK,1);
        $timestamp=substr($x,strlen($dir_prefix));            
        out("using $x - from ".date("l F j Y H:i:s",$timestamp),1);
        $backup_dir=CW_BACKUP_DIR.$x."/";
        out("full path: $backup_dir",1);
        //Get objects
        $d=new cw_Db();
        $a=new cw_Auth($d);    
        $files=new cw_Files($d);
        $mdb=new cw_Music_db($a);
        //Go through existing files_to_instruments_and_arrangements and delete files and files-records
        $all_musicdbfile_ids=$mdb->get_all_musicdb_file_ids();
        if (is_array($all_musicdbfile_ids)){
          out("deleting ".sizeof($all_musicdbfile_ids)." existing musicdb files...",1);
          foreach ($all_musicdbfile_ids as $v){
            if (!$files->remove_file($v)){
              out("file-id $v ".LOG_FAIL,1);            
            }
          }
          out("done deleting files",1);        
        }        
        //Recreate extant musicdb tables without default records
        $res=$mdb->recreate_tables(false);
        if ($res){
          out("recreating musicdb table structure... ".LOG_OK,1);            
          //Now copy back contents of musicdb tables
          $command="mysql --user=".CW_MYSQL_USER." --password=".CW_MYSQL_PWD." ".CW_DATABASE_NAME." < ".$backup_dir."mysql_musicdb_backup_".CW_DATABASE_NAME.".sql";
          out($command,1);
          exec($command);
          out("shell execution for back-copying sql data triggered ".LOG_OK,1);
          //
          out("copying and relinking files... ",1);
          $filelist=scandir($backup_dir);
          array_shift($filelist); //.          
          array_shift($filelist); //..
          foreach($filelist as $v){
            //$v has string with filename. Expecting "cwfile_ID local filename.local extension"
            $filename_without_cwfile_prefix=substr($v,7);
            $pos_of_first_dot=strpos($filename_without_cwfile_prefix,".");            
            $cw_file_id=substr($filename_without_cwfile_prefix,0,$pos_of_first_dot);
            $local_basename=substr($filename_without_cwfile_prefix,$pos_of_first_dot+4);
            $local_name=get_filename_without_extension($local_basename);
            $local_ext=get_extension($local_basename);
            if ($cw_file_id>0){
              out("found file with id [$cw_file_id]: [$local_name.$local_ext]",1);
              $new_id=$files->add_existing_file_to_db($backup_dir.$v,0,0,$local_name.".".$local_ext,false);
              if ($new_id>0){
                //out("obtained files record / new id: [$new_id]",2); 
                //Now adjust files_to_instruments_and_arrangements record(s) to $new_id
                if ($mdb->replace_file_id_in_files_to_instruments_and_arrangements_table($cw_file_id,$new_id)){
                  //Success!
                  //out("relinking file in files_to_instruments_and_arrangements [$cw_file_id]->[$new_id] ".LOG_OK,2); 
                } else {
                  out("relinking file in files_to_instruments_and_arrangements [$cw_file_id]->[$new_id] ".LOG_FAIL,2);                 
                }             
              } else {
                out("obtaining files record / new id... ".LOG_FAIL,2);               
              }                                    
            } else {
              out("ignoring file [$v] ".LOG_OK,1);
            }
          }                                            
        } else {
          out("recreating musicdb table structure... ".LOG_FAIL,1);                    
        }                        
      } else {
        out("locating latest musicdb backup... ".LOG_FAIL,1);            
      }
    }
    out("musicdb import finished");
  }
  
  if (empty($_GET["action"])){
    $notice="
      <p>ChurchWeb Backup and restore tool</p>
      <p>
        Options (via GET):
        <ul>
          <li>action=backup: full backup (scripts, files, sql); use ignore=table_name to skip a table</li>          
          <li>action=reinit: clear database, recreate table structures</li>
          <li>action=export_musicdb</li>
          <li>action=import_musicdb</li>
          <li>action=restore_data: restore latest sql data, and filebase</li>
          <li>action=restore_everything: restore latest sql data, and filebase, and all scripts</li>
        </ul>
      </p>
    ";
    out($notice);
  }


?>