<?php

class cw_Files {
  
    public $d; //Database access
        
    function __construct($d){
      $this->d = $d;
    }
    
    function check_for_table($table="files"){
      return $this->d->table_exists($table);
    }
    
    function create_tables(){
      return (($this->d->q("CREATE TABLE files (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          path varchar(150),
                          name varchar(70),
                          ext varchar(10),
                          size INT,
                          added_at INT,
                          added_by INT,
                          expires_at INT,
                          physical_filename varchar(150),
                          INDEX (path,name,ext,size,added_at,added_by)
                        )")));                                
    }

    /*
      
    */

    private function check_for_and_drop_tables($tables=array()){
      foreach ($tables as $v){
        if ($this->check_for_table($v)){
          $this->d->drop_table($v);
        }        
      }
    }
    
    
    public function remove_directory_tree($dir,$leave_last=true){
      foreach (scandir($dir) as $item) {
          if ($item == '.' || $item == '..') continue;
          if (is_dir($dir."/".$item)){
            //Dive into subdir
            (substr($dir,-1)=="/") ? $s="" : $s="/";
            $this->remove_directory_tree($dir.$s.$item,false);
          } else {
            (substr($dir,-1)=="/") ? $s="" : $s="/";
            unlink($dir.$s.$item);
          }
      }
      if (!$leave_last){
        //Don't delete if this is the root of the originally given tree
        rmdir($dir);    
      }
    }     
    
    private function remove_physical_file($file){
      if ((file_exists($file)) && (!is_dir($file))){
        return unlink($file);
      }
      return false;
    }         
    
    //Delete tables (if extant) and re-create. $delete_filebase in a sense is like "Add default records" for the other classes - i.e. start with blank slate (no records, no files).
    function recreate_tables($delete_filebase=true){
      $tables=array("files");
      $this->check_for_and_drop_tables($tables);
      if ($this->create_tables()){
        if (is_dir(CW_ROOT_UNIX.CW_FILEBASE)){
          if ($delete_filebase){
            //Delete files from directory CW_FILEBASE
            $this->remove_directory_tree(CW_ROOT_UNIX.CW_FILEBASE);
          }
        } else {
          return false; //CW_FILEBASE directory doesn't exist
        }
        return true;
      }
      return false;
    }

    /* files */
    
    private function add_files_record($path,$name,$ext,$size,$physical_filename="",$expires_at=0,$person_id=0){
      $e=array();
      $e["path"]=$path;
      $e["name"]=$name;
      $e["ext"]=$ext;
      $e["size"]=$size;
      $e["added_at"]=time();
      $e["added_by"]=$person_id;
      $e["expires_at"]=$expires_at;
      $e["physical_filename"]=$physical_filename;
      return $this->d->insert_and_get_id($e,"files");
    }
    
    private function delete_files_record($id){
      return $this->d->delete($id,"files");
    }
            
    private function update_files_record($id,$e){
      return $this->d->update_record("files","id",$id,$e);
    }
            
    function make_blank_pdf(){
      $pdf=new cw_pdf('P','mm','Letter');
      $pdf->set_custom_footer("This blank page has been inserted so that you have the least necessary amount of pageturns with the next piece");
      $pdf->AliasNbPages(); //nessesary for page numbers
      $pdf->AddPage();
      $pdf->Output(CW_ROOT_UNIX.CW_FILEBASE.CW_BLANK_PDF_FILENAME,"F");            
    }
    
    function get_full_path_to_blank_pdf(){
      return CW_ROOT_UNIX.CW_FILEBASE.CW_BLANK_PDF_FILENAME;
    }
    function make_sure_blank_pdf_exists(){
      if (!file_exists($this->get_full_path_to_blank_pdf())){
        $this->make_blank_pdf();
      }
      return file_exists($this->get_full_path_to_blank_pdf());
    }
            
    /* public functions */ 

    //Slides is simply array of strings
    function make_lyrics_ppt($slides=array(),$cover_text,$name,$target_ppt_local_name){
      $target_ppt_physical_path=CW_FILEBASE.CW_TMP_SUBFOLDER.$name;
      $ppt=new cw_PowerPoint();
      $ppt->add_front_cover($cover_text);
      foreach ($slides as $v){
        //After the %/ comes the copyright notice in music slides
        $x=explode('%/',$v);
        //Now $x[0] is the rest of the slide, while the (C) is in $x[1]
        //In $x[0] may be a @/ sequence, after which would come source info
        $y=explode('@/',$x[0]);
        //Now $y[0] has the actual text, $y[1] may have source info
        $ppt->add_slide($y[0],$x[1],$y[1]);      
      }
      $ppt->save_file(CW_ROOT_UNIX.$target_ppt_physical_path);
      if (file_exists(CW_ROOT_UNIX.$target_ppt_physical_path)){
        //Success - file exists
        $new_id=$this->add_existing_file_to_db(CW_ROOT_UNIX.$target_ppt_physical_path,time()+CW_ON_THE_FLY_FILE_TTL,0,$target_ppt_local_name);
        if ($new_id>0){
          //Success. Return ID.
          return $new_id;            
        }
      }        
      return false;
    }
    
    function make_combined_pdf_file($ids=array(),$name,$target_pdf_local_name,$minimize_pageturns=true,$no_blank_page_at_beginning=true){
      //Get all the records for ids
      $file_recs=array();
      if (is_array($ids)){  
        foreach ($ids as $v){
          $file_recs[]=$this->get_files_record($v);
        }    
        if (sizeOf($file_recs>0)){
          //Got a bunch of file records.
          //Determine paths and local names (filenames in archive)
          $array_of_source_files=array();
          foreach ($file_recs as $v){
            $physical_path=CW_ROOT_UNIX.CW_FILEBASE.$v["path"].$v["physical_filename"];
            $array_of_source_files[]=$physical_path;                  
          }
          
          if (($minimize_pageturns) && ($this->make_sure_blank_pdf_exists())){
            //Go through all source files and see if blank pages need to be inserted
            $spaced_list_of_source_files="";
            $current_page_nr=1;
            foreach ($array_of_source_files as $v){
              $gs_command='gs -q -dNODISPLAY -c "('.$v.') (r) file runpdfbegin pdfpagecount = quit"';
              $no_of_pages_this_file=shell_exec($gs_command);
              //Check if we might need to insert a blank page
              if (($no_of_pages_this_file%2==0) && ($current_page_nr%2==1)){
                //This file has an even no of pages and we're about to write onto an uneven page, therefore insert blank
                //UNLESS it is the FIRST file and $no_blank_page_at_beginning==true (applies when there's a multi-page service order)
                if (($current_page_nr>1) && ($no_blank_page_at_beginning)){
                  $spaced_list_of_source_files.=$this->get_full_path_to_blank_pdf()." ";
                  $current_page_nr++;
                }                
              }
              $spaced_list_of_source_files.=$v." ";
              $current_page_nr=$current_page_nr+$no_of_pages_this_file;
            }          
          } else {
            //No minimizing of pageturns required
            $spaced_list_of_source_files=implode(" ",$array_of_source_files);
          }
          $target_pdf_physical_path=CW_FILEBASE.CW_TMP_SUBFOLDER.$name;
          //Delete file if it exists already
          if (file_exists(CW_ROOT_UNIX.$target_pdf_physical_path)){
            unlink(CW_ROOT_UNIX.$target_pdf_physical_path);
          }
          //Perform joining
          $gs_command="gs -q -sPAPERSIZE=letter -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=".CW_ROOT_UNIX.$target_pdf_physical_path." $spaced_list_of_source_files";
          //echo $gs_command;
          $output = shell_exec($gs_command);
          if (file_exists(CW_ROOT_UNIX.$target_pdf_physical_path)){
            //Combined file exists
            $new_id=$this->add_existing_file_to_db(CW_ROOT_UNIX.$target_pdf_physical_path,time()+CW_ON_THE_FLY_FILE_TTL,0,$target_pdf_local_name);
            if ($new_id>0){
              //Success. Return ID.
              return $new_id;            
            }
          }        
        }
      }
      return false;
    }
        
    //Make a temporary zip-file of the files given in $ids, and full web path (used to download collections of files)
    //DOES NOT CREATE AN ENTRY IN THE files TABLE!
    function make_zip_file($ids=array(),$name,$archive_local_name){
      //Get all the records for ids
      $file_recs=array();
      if (is_array($ids)){  
        foreach ($ids as $v){
          $file_recs[]=$this->get_files_record($v);
        }    
        if (sizeOf($file_recs>0)){
          //Got a bunch of file records.
          //Determine paths and local names (filenames in archive)
          $files_to_zip=array();
          $local_names=array(); //This array just to keep track of what local names we have given already
          foreach ($file_recs as $v){
            $physical_path=CW_ROOT_UNIX.CW_FILEBASE.$v["path"].$v["physical_filename"];
            //Determine local name (filename inside archive)
            empty($v["ext"]) ? $ext="" : $ext=".".$v["ext"];
            $local_name=$v["name"].$ext;
            //Ensure uniqueness of filenames in archive
            while (in_array($local_name,$local_names)){
              $local_name.="_".$local_name; //Prepend underscore if $local_name exists already
            }
            $local_names[]=$local_name; //New unique local name found
            $files_to_zip[]=array("physical_path"=>$physical_path,"local_name"=>$local_name);                  
          }
          //Now $files_to_zip is array with the paths and destination filenames to zip 
          //Make zipfile.
          $zip_path=CW_FILEBASE.CW_TMP_SUBFOLDER.$name;
          $zip = new ZipArchive;
          //Delete file if it exists already
          if (file_exists(CW_ROOT_UNIX.$zip_path)){
            unlink(CW_ROOT_UNIX.$zip_path);
          }          
          $zipmode=ZIPARCHIVE::CREATE;
          if ($zip->open(CW_ROOT_UNIX.$zip_path,$zipmode) === true){
            foreach ($files_to_zip as $v){
              $zip->addFile($v["physical_path"],$v["local_name"]);
            }
            $zip->close();
            //Success. Add to files-table
            $new_id=$this->add_existing_file_to_db(CW_ROOT_UNIX.$zip_path,time()+CW_ON_THE_FLY_FILE_TTL,0,$archive_local_name);
            if ($new_id>0){
              //Success. Return ID.
              return $new_id;            
            }
          }        
        }
      }
      return false;
    }        

    //Move an uploaded file ($tmp_file should come from $_FILES["file"]["tmp_name"]), add a table entry and return id
    function add_uploaded_file($tmp_file,$name,$ext,$person_id=0){
      //
      if (file_exists($tmp_file)){
        //Found the file
        $size=filesize($tmp_file);
        $new_id=$this->add_files_record("",$name,$ext,$size,"",0,$person_id);
        if ($new_id>0){
          $physical_file_name="cwfile_".$new_id.".cw";
          $full_dest_path=CW_ROOT_UNIX.CW_FILEBASE.$physical_file_name;
          if (move_uploaded_file($tmp_file,$full_dest_path)){
            //Success - update physical filename col in table
            $e=array();
            $e["physical_filename"]=$physical_file_name;
            $this->update_files_record($new_id,$e);
            return $new_id;
          } else {
            //Placing the file failed, so delete record, too, and return false (in this case !==true)
            $this->delete_files_record($new_id);
            return "attempted destination: ".$full_dest_path;
          }           
        }
      }      
    }

    function add_existing_file_to_db($full_unix_path,$expires_at=0,$person_id=0,$local_name="",$delete_source_file=true){
      //Remove expired tmp files (maintenance)
      $this->remove_expired_files();
      //
      if (file_exists($full_unix_path)){
        //Found the file
        empty($local_name) ? $local_name=basename($full_unix_path) : null;
        $size=filesize($full_unix_path);
        $name=get_filename_without_extension($local_name);
        $ext=get_extension($local_name);
        $new_id=$this->add_files_record("",$name,$ext,$size,"",0,$person_id);
        if ($new_id>0){
          $physical_file_name="cwfile_".$new_id.".cw";
          $full_dest_path=CW_ROOT_UNIX.CW_FILEBASE.$physical_file_name;
          if (copy($full_unix_path,$full_dest_path)){
            //Success - delete input file if requested
            if ($delete_source_file){
              unlink($full_unix_path);
            }
            //update physical filename col in table
            $e=array();
            $e["physical_filename"]=$physical_file_name;
            $e["expires_at"]=$expires_at;
            $e["added_by"]=$person_id;
            $this->update_files_record($new_id,$e);
            return $new_id;
          } else {
            //Placing the file failed, so delete record, too, and return false (in this case !==true)
            $this->delete_files_record($new_id);
            return "attempted destination: ".$full_dest_path;
          }           
        }
      }      
    }

/*
    //Add an existing file to the files table and return id. Expiry date can be given here, too.
    //Will move/rename the file to the filebase
    function add_existing_file_to_db($full_unix_path,$expires_at=0,$person_id=0,$local_name=""){
      //Remove expired tmp files (maintenance)
      $this->remove_expired_files();
      //
      if (file_exists($full_unix_path)){
        //Found the file
        //See if this file has already a table entry - and if so, delete that one before creating a new one
        $existing_record=$this->get_files_record_by_physical_path($full_unix_path);
        if (is_array($existing_record)){
          $this->delete_files_record($existing_record["id"]);
        }
        $size=filesize($full_unix_path);
        $name=get_filename_without_extension(basename($full_unix_path));
        $ext=get_extension($full_unix_path);
        //Local name if given, or else derive from full unix path
        (empty($local_name)) ? $source_for_local_name=$full_unix_path : $source_for_local_name=$local_name;
        $local_name=get_filename_without_extension(basename($source_for_local_name));
        $local_name_ext=get_extension($source_for_local_name);
        $dest_path=substr(dirname($full_unix_path)."/",strlen(CW_ROOT_UNIX.CW_FILEBASE));
        empty($ext) ? $physical_filename=$name : $physical_filename=$name.".".$ext;
        $new_id=$this->add_files_record($dest_path,$local_name,$local_name_ext,$size,$physical_filename,$expires_at,$person_id);
        if ($new_id>0){
          return $new_id;
        }
      }      
    }
*/    
    function get_files_record_by_physical_path($full_unix_path){
      $filename=basename($full_unix_path);     
      $cw_filebase_relative_path=substr(dirname($full_unix_path)."/",strlen(CW_ROOT_UNIX.CW_FILEBASE));
      $query="SELECT * FROM files WHERE path='$cw_filebase_relative_path' AND physical_filename='$filename';";
      if ($res=$this->d->q($query)){
        if ($r=$res->fetch_assoc()){
          return $r;
        }
      }
      return false;    
    }
    
    function get_files_record($id){
      return $this->d->get_record("files","id",$id);                      
    }
    
    function get_full_physical_path($id){
    	$full_physical_path=false;
    	if ($r=$this->get_files_record($id)){
    		$full_physical_path=CW_ROOT_UNIX.CW_FILEBASE.$r["path"].$r["physical_filename"];
    	}
    	return $full_physical_path;
    }
    
    //Remove record and physical file
    function remove_file($id){
      $r=$this->get_files_record($id);
      if (is_array($r)){
        //Got the record
        $full_physical_path=CW_ROOT_UNIX.CW_FILEBASE.$r["path"].$r["physical_filename"];
        if ($this->remove_physical_file($full_physical_path)){
          return $this->delete_files_record($id);
        }
        return false; //Problem removing physical phile         
      }
      return true; //if record never existed, we're successful
    }
    
    function remove_expired_files(){
      $query="SELECT id FROM files WHERE expires_at>0 AND expires_at<".time();
      if ($res=$this->d->q($query)){
        while ($r=$res->fetch_assoc()){
          $this->remove_file($r["id"]);
        }
      }
    }
    
    //Take the physical file linked to $id and make a copy to the target directory
    function copy_file($id,$full_dest_dir,$use_local_name=false){
      $r=$this->get_files_record($id);
      if (is_array($r)){
        if ($use_local_name){
          $dest_file=$full_dest_dir.$r["physical_filename"]." ".$r["name"].".".$r["ext"];
        } else {
          $dest_file=$full_dest_dir.$r["physical_filename"];        
        }
        return copy(CW_ROOT_UNIX.CW_FILEBASE.$r["physical_filename"],$dest_file);        
      } 
      return false;   
    }
    
}

?>