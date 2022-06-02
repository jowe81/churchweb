<?php

  require_once "../lib/framework.php";
  
   
  if (isset($_POST["query"])){
      //Execute query and return result as JSON
      if ($res=$d->query($_POST["query"])){
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
      }      
  }

  echo($t);
  $p->nodisplay=true;
  die;

?>