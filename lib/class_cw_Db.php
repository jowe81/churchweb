<?php
/*
    This class handles all database requests for the project
*/

class cw_Db{

  //Msqli Object
  public $db;
  
  //Store mysql errors
  public $error;
  
  //Is this instance connected to the db?
  public $connected=false;

  //Make msqli object  
  function __construct($mysql_database=CW_DATABASE_NAME,$mysql_host=CW_MYSQL_HOST,$mysql_user=CW_MYSQL_USER,$mysql_pass=CW_MYSQL_PWD){
    $this->db = new mysqli($mysql_host,$mysql_user,$mysql_pass,$mysql_database);
    //Check if an error occured    
    if (!mysqli_connect_errno()){
      $this->connected=true;
    } else {   //Debug
      echo $this->db->error;
      exit;
    }
   }  

  //Pass through (should not be used)
  function query($q){
    //echo "Query: $q <br>"; //Debug
    $res=$this->db->query($q);
    $this->error=$this->db->error;
    return $res;
  }
  
  function q($q){
    return $this->query($q);
  }
  
  //Execute query and return result as JSON
  function select_json($q){
    if ($res=$this->query($q)){
       $i=0;
       $t="{ ";
       while ($e=$res->fetch_assoc()){
          $i++;
          $t.=" \"R$i\": { ";
          foreach ($e as $k=>$v){
           $t.="\"$k\":\"$v\",";
          }
          if (substr($t,-1)==","){
            $t=substr($t,0,-1);
          }
          $t.=" },";
       }
       $t=substr($t,0,-1);
       $t.=" }";                  
       return $t;      
    }
    return false;  
  }

//Execute query and return result as JSON, without second level
  function select_flat_json($q){
    if ($res=$this->query($q)){
       $i=0;
       $t="";
       while ($e=$res->fetch_assoc()){
          $i++;
          $t.=", {";
          foreach ($e as $k=>$v){
           $t.="\"$k\":\"$v\",";
          }
          if (substr($t,-1)==","){
            $t=substr($t,0,-1);
          }
          $t.="}";
       }
       if (!empty($t)){
        $t=substr($t,2);
       }
       $t="[ $t ]";                  
       return $t;      
    }
    return false;  
  }
  
  function select($table,$cols="",$conditions="",$orderby=""){
    //If no columns are provided, select all
    if ($cols==""){
      $cols="*";
    }
    //If conditions are provided, add keyword WHERE
    if ($conditions!=""){
      $conditions="WHERE ".$conditions;
    }
    //If orderby is provided, add keyword ORDER BY 
    if ($orderby!=""){
      $orderby="ORDER BY ".$orderby;
    }
    return $this->query("SELECT $cols FROM $table $conditions $orderby");
  }  
  
  //Load entire table into array  
  function get_table($table,$orderby=""){
    $r=array();
    if ($res=$this->select("$table","","",$orderby)){
      while ($row=$res->fetch_assoc()){
        $r[]=$row;
      }
    }    
    return $r;
  }

  //Returns a record as array
  function get_record($table,$key_field,$key_value){
    if ($res=$this->select($table,"","$key_field='$key_value'")){
      if ($row=$res->fetch_assoc()){
        return $row;
      }
    }
    return false;        
  }
  
  function retrieve_all($select_query){
    if ($res=$this->q($select_query)){
      $t=array();
      while ($r=$res->fetch_assoc()){
        $t[]=$r;
      }
      return $t;
    }
    return false; 
  }
  
  //Return whether or not the record exists
  function record_exists($table,$key_field,$key_value){
    return is_array($this->get_record($table,$key_field,$key_value));
  }
  
  //Update a record. $data is associative array with keys=field names and values=data
  function update_record($table,$key_field,$key_value,$data){
    if (is_array($data)){
      $q="";
      //Go through each element in $data to build query
      foreach ($data as $k=>$v){
        $q.="$k='".mysql_real_escape_string($v)."',";
      }
      //Now the last comma must be taken off
      $q=substr($q,0,-1);
      //Put entire query together
      $q="UPDATE $table SET $q WHERE $key_field='$key_value'";
      //Execute     
      return $this->query($q);
    } else {
      //No data array specified
      return false;
    }  
  }

	//Build the query to insert new record $e in table $table 
	function build_insert_query($e,$table) {
		//We expect $e to be immaculate - build query from all fields 
		$keys="(";
		$values="(";
		foreach ($e as $key=>$value) {
			$keys.="$key,";
			$values.="'".mysql_real_escape_string($value)."',";
		}
		$keys=substr($keys,0,strlen($keys)-1).")"; //Cut the last comma
		$values=substr($values,0,strlen($values)-1).")"; //Cut the last comma
		$query="INSERT INTO $table $keys VALUES $values;"; 
		return $query;		
	}

  //Insert the record in assoc array $e in table $table
  function insert($e,$table){
    $query=$this->build_insert_query($e,$table);
    return ($this->query($query));
  }
  
  //Insert the record in assoc array $e in table $table, and return id (auto increment id field must exist)
  function insert_and_get_id($e,$table){
    $query=$this->build_insert_query($e,$table);
    $success=$this->query($query);
    if ($success){
      return $this->db->insert_id;    
    }
    return false;        
  }

  //Delete record
  function delete($value,$table,$field="id"){
    return ($this->query("DELETE from $table WHERE $field='$value'"));
  }

  //Does $table exist in cw database?
  function table_exists($table){
    return ( mysqli_num_rows( $this->query("SHOW TABLES LIKE '$table'")));
  }
  
  //Drop table
  function drop_table($table){
    $query="DROP table $table;";
    return $this->query($query);
  }

  //Passthrough
  function real_escape_string($s){
    return $this->db->real_escape_string($s);
  }
  
  
  //Joins
  
  function select_joined($table1,$table2,$link_field,$fields1="*",$fields2="*",$cond="",$orderby=""){
    //Make arrays of $fields1,$fields2 and then build selector string
    $f=explode(',',$fields1);
    $table1_sel="";
    foreach ($f as $v){
      $table1_sel.=$table1.".".$v.",";    
    }
    $table1_sel=substr($table1_sel,0,-1);    
    $f=explode(',',$fields2);
    
    $table2_sel="";
    foreach ($f as $v){
      $table2_sel.=$table2.".".$v.",";    
    }
    $table2_sel=substr($table2_sel,0,-1);
        
    //If conditions are given, append them
    if ($cond!=""){
      $cond="AND ".$cond;
    }
    //If order given, append 
    if ($orderby!=""){
      $orderby="ORDER BY ".$orderby;
    }
    //Put query together        
    $query="SELECT $table1_sel,$table2_sel FROM $table1,$table2 WHERE $table1.$link_field=$table2.$link_field $cond $orderby;";
    return $this->query($query);
  }
  
  //Take resource variable and return array of records
  function res_to_array($res){
    $t=array();
    while($r=$res->fetch_assoc()){
      $t[]=$r;
    }
    return $t;
  }
  
  ////////////////////////////////
  
  function array_to_query_conditions($filter=array()){
    $t="";
    foreach ($filter as $k=>$v){
      $t.="AND ".$k." LIKE'".$v."%' ";
    }
    $t=substr($t,4); //Cut off first AND
    return $t;    
  }
  
  ////////////////////////////////
  
  function get_size($table,$condition){
    if (!empty($condition)){
      $condition="WHERE $condition";
    }
    $query="SELECT count(*) FROM $table $condition;";
    if ($res=$this->q($query)){
      if ($r=$res->fetch_assoc()){
        return $r["count(*)"];
      }    
    }
    return false;
  }

}

?>