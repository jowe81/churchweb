<?php

  require_once "../lib/framework.php";

  //Load styles
  $p->stylesheet(CW_ROOT_WEB."css/personal_records.css");

  $gd=new cw_Display_groups($d,$a);

  $p->p($gd->display_new_group_dialogue());


?>