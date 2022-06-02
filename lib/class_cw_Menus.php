<?php

  class cw_Menus {
  
    private $auth; //Passed in cw_Auth object
    
    function __construct($auth){
      $this->auth=$auth;
    }
    
    //Return html for personalized menu
    //Return a <ul> with <li>'s of $service_id's children
    
    function display_menu($service_id,$person_id,$wrapper_id="",$depth=999){
      //We need to wrap the whole thing in a <ul> tag
      return "<ul id=\"$wrapper_id\">".$this->display_menu_recursion($service_id,$person_id,$depth)."</ul>";
    }

    //Return separate <ul>'s for each of the children of $service_id
    function display_menus_for_first_level_children($service_id,$person_id,$wrapper_id="",$depth=999,$leaves_only=false){
      $t="";
      //Only do anything if service is parent
      if ($this->auth->services->service_is_parent($service_id)){
        $children=$this->auth->services->get_all_direct_children($service_id,$leaves_only); //if 2nd parameter is set it makes sure that only leaves are returned
        foreach ($children as $v){
          $t.="<ul id=\"$wrapper_id\">".$this->display_menu_recursion($v,$person_id,$depth)."</ul>";
        }
      }
      return $t;      
    }
    
    private function display_menu_recursion($service_id,$person_id,$depth){
      /*
        How to do this?
        -We can traverse the tree
        -We know which services are authorized, i.e. to be displayed
        
        Pseudo-Code:
        if ($service_id is authorized)
          <li><service_title>
          if ($service_id has children) and ($depth>0)
            <ul>
            while (children)
              $t.=display_menu($child_service_id,$person_id,$depth-1)
            </ul>
          else
            </li>
        
      */
      $t="";
      if ($this->auth->user_is_authorized($person_id,$service_id)){
        if (($service_record=$this->auth->services->get_service_record($service_id)) && ($service_record["show_in_menu"]>0)){
          $t.="\n<li><a href=\"".CW_ROOT_WEB.$service_record["file"]."\">".$service_record["title"]."</a>";
          if (($this->auth->services->service_is_parent($service_id)) && ($depth>0)){
            $t.="\n<ul>";
            $children=$this->auth->services->get_all_direct_children($service_id);
            foreach ($children as $v){
              $t.=$this->display_menu_recursion($v,$person_id,$depth-1);
            }
            $t.="</ul>";
          }
          $t.="</li>";
          return $t;
        }
      }            
    }
      
  
  }

?>