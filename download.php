<?php
  /* Download handler does not use framework.php
  
    - expects in $_GET['a'] a SHA1 encoded service id for the service from which the download request comes
    - that service id needs to match with one of the permitted services for this person/session
    - expects in $_GET['b'] a file id  
  
  */

  //Load constants and utility libraries
  require_once "lib/constants.php";
  
        //Ensure automatic inclusion of classfiles
        function __autoload($name){
          require_once CW_ROOT_UNIX."lib/class_".$name.".php";
        }
    
  //Set timezone
  date_default_timezone_set(CW_TIME_ZONE);
  
  //Init session
  session_start();
      
  //Create database object
  $d=new cw_Db();
  
  //Create auth object
  $a=new cw_Auth($d);
                                    
  //Load the previously stored (upon Login) array of permissions back into $a->my_permitted_services
  $a->my_permitted_services=$_SESSION["my_permitted_services"];




	function download($file,$download_name)
	{ //This code from: http://ro2.php.net/readfile
		if (file_exists($file)) {
		    header('Content-Description: File Transfer');
		    header('Content-Type: application/octet-stream');
		    header('Content-Disposition: attachment; filename="'.$download_name.'"');
		    header('Content-Transfer-Encoding: binary');
		    header('Expires: 0');
		    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		    header('Pragma: public');
		    header('Content-Length: ' . filesize($file));
		    ob_clean();
		    flush();
		    readfile($file);
		}
		else
		{
			echo "File $file does not exist";
		}
	}


  //Loop through all permitted service_ids to see which one has been passed as SHA1 in $_GET["a"]
  $errmsg="Could not serve file.";
  $authorized=false;
  $no_permitted_services=sizeof($a->my_permitted_services);
  foreach ($a->my_permitted_services as $k=>$v){
    //$k has service_id, $v the permission level
    $current_service_sha=sha1($k);
    if ($current_service_sha==$_GET["a"]){
      if ($v>=CW_AUTH_LEVEL_UNSPECIFIED){
        //OK: the service id encoded in $_GET["a"] is one that we have access to.
        //Attempt to serve file.
        $file_id=$_GET["b"];
        if ($file_id>0){
          //File id was given
          $f=new cw_Files($d);
          //Retrieve record for this file
          $file_rec=$f->get_files_record($file_id);
          if (is_array($file_rec)){
            //Got record
            $file=CW_ROOT_UNIX.CW_FILEBASE.$file_rec["path"].$file_rec["physical_filename"];
            empty($file_rec["ext"]) ? $ext="" : $ext=".".$file_rec["ext"];
            //Replace spaces with underscore in the download name 
            //$download_name=str_replace(" ","",$file_rec["name"].$ext);
            $download_name=$file_rec["name"].$ext;
            download($file,$download_name);          
          } else {
            //Invalid file-id
            echo $errmsg." (invalid file-id)";
          }
        } else {
          //No file id
          echo $errmsg." (no file-id)";
        }
        $authorized=true;
        break;            
      }
    }  
  }  
  
  if (!$authorized){
    //Not served because not authorized
    echo $errmsg." (not authorized)";
  }
  
 

   
?>