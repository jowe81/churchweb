<?php
  class cw_Page{

    public $head='',$body='',$title='';
    private $stylesheets=array(); //Use $this->add_stylesheet to add files
    
    /*
      Parts of page.
      If they are used:
      - $body will be considered the content for the
        main content div (main_content) at display time.
      - If they are unused, they won't be displayed at all and
        $body will be the main and only content
        
      Page hierarchy of divs:
      outer_container(top_container(logo_main_menu_and_quick_links(logo(),main_menu(),service_title(),quick_links()),sub_menu()),main_content())
      
      Note that main_menu() and title() are visible in an alternating fashion for services >l2 (jquery)
        
    */
    
    public $jquery='',$js=''; //Contents of $jquery end up in document.ready(); $js is global scope javascript
    
    public $logo='', $main_menu='',$sub_menu='',$quick_links, $service_title;
    
    public $error;
    
    public $nodisplay=false; //Setting this basically discards the page (for ajax)
    
    public $no_page_framework=false; //Setting this omits the menu and container structure and simply displays main_content
    
    function __set($attribute,$value){
      $this->$attribute=$value;
    }
    
    //Add html to body
    function p($html){
      $this->body.=$html;
    }
    
    function li($html){
      $this->body.="<li>$html</li>";
    }
    
    //Add html to head
    function h($html){
      $this->head.=$html;
    }
    
    //Add html to logo div
    function logo($html){
      $this->logo.=$html;    
    }
    
    //Add html to main_menu div
    function main_menu($html){
      $this->main_menu.=$html;    
    }
    
    //Add html to service_title div
    function service_title($html){
      $this->service_title.=$html;    
    }
    
    //Add html to sub_menu div
    function sub_menu($html){
      $this->sub_menu.=$html;    
    }

    //Add html to quick_links div
    function quick_links($html){
      $this->quick_links.=$html;    
    }
    
    //Add a stylesheet
    function stylesheet($file){
      $this->stylesheets[]=$file;
    }
    
    //Add javascript to $jquery
    function jquery($js){
      $this->jquery.="\n\n".$js;    
    }
    
    //Add javascript to $js
    function js($js){
      $this->js.="\n\n".$js;    
    }
    
    function fatal_error($t){
      echo ($t);
      die;
    }
    
    //Output error message
    function error($msg,$buttons=array()){
      //If the constants for authorization levels are passed in, substitute $msg for predefined error messages
      if ($msg==CW_E){
        $msg="Insufficient privileges. You need at least Editor rights for the requested operation.";
      } elseif ($msg==CW_A){
        $msg="Insufficient privileges. You need at Admin rights for the requested operation.";      
      }
      $this->message($msg,$buttons,true);
    }
        
    //Output message or error message. This relies on the css in framework.css
    //$buttons must have link in key, title in value
    function message($msg,$buttons=array(),$error=false){
      $div_id="message"; //Assume message only
      $title="ChurchWeb System Message";
      if ($error){
        $div_id="error_message"; //Okay, is error message
        $title="ChurchWeb Error Message";
      }
      $buttons_code="";
      foreach ($buttons as $k=>$v){
        $buttons_code.="<div id=\"message_button\"><a href=\"".$k."\">$v</a></div>";      
      }
      $this->p("<div id=\"$div_id\">
                  <div id=\"message_title\">$title</div>
                  <div id=\"message_top\">$msg</div>
                  <div id=\"message_bottom\">
                    $buttons_code
                  </div>
                </div>");    
    }
    
    function display(){
      //If jquery is used, add jquery and code to head
      if (!($this->jquery=='')){
        $this->head.="\n<script type='text/javascript' src='".CW_ROOT_WEB."lib/jquery/jquery-1.7.1.min.js'></script>
                      \n<script type='text/javascript' src='".CW_ROOT_WEB."lib/jquery/jquery-ui-1.8.20.custom.min.js'></script>
                      \n<script type='text/javascript' src='".CW_ROOT_WEB."lib/jquery/jquery.simpletip-1.3.1.min.js'></script>
                      \n<script type='text/javascript' src='".CW_ROOT_WEB."lib/crypto_js_3_0_2/rollups/sha1.js'></script>
                      \n<script type='text/javascript' src='".CW_ROOT_WEB."lib/timezones/jstz.min.js'></script>
                      \n<script type='text/javascript'>
                      \n//JQuery functions in document.ready context
                      \n $(document).ready(function(){
                      \n   $this->jquery
                      \n
                      \n }); //end ready
                      \n</script>";
      
      }
      
      //If global js is used, add also to head
      if (!($this->js=='')){
        $this->head.="\n<script type='text/javascript'>
                      \n//Global Javascript
                      \n  $this->js
                      \n
                      \n//End of Global Javascript
                      \n</script>";
      
      }
      
      if (!$this->no_page_framework){
        //If page parts are used, add the appropriate wrappers to body
        if (!(($this->logo=='') && ($this->main_menu=='') && ($this->sub_menu==''))){
          $this->body="
            <div id=\"outer_container\">
              \n\n<div id=\"top_container\">
                  \n<div id=\"logo\">$this->logo</div>
                  \n<div id=\"menus\">
                     \n<div id=\"main_menu\">$this->main_menu</div>
                     \n<div id=\"service_title\">$this->service_title</div>
                     \n<div id=\"sub_menu\">$this->sub_menu</div>
                  \n</div>
                  \n<div id=\"quick_links\">$this->quick_links</div>
              </div>
              \n\n<div id=\"main_content\">
                  $this->body
              </div>
              \n\n<div id=\"modal\" style='display:none;'>Loading... 
              </div>
              <script type='text/javascript'>
                $('#modal').hide(); //Hide until needed
              </script>
            </div>
              ";
        }
        $no_bg="";
      } else {
        //the no_page_framework flag is set - eliminate the body background
        $no_bg="style=\"background-image:url('');\"";      
      }
      
      echo "
            <!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01//EN\"
              \"http://www.w3.org/TR/html4/strict.dtd\">
            \n\n  
            <html>
              \n  <head>
              \n    <title>$this->title</title>
            <meta http-equiv=\"Content-type\" content=\"text/html; charset=utf-8\"/>              
      ";
      foreach ($this->stylesheets as $v){
        echo "\n    <link rel=\"stylesheet\" type=\"text/css\" href=\"$v?ts=".getBeginningOfDay(time())."\" />";      
      }
      echo "  \n    <link rel=\"shortcut icon\" href=\"".CW_ROOT_WEB."img/cwpl.ico\" />";      
      echo "  \n    $this->head                          
              \n  </head>
              \n  <body $no_bg>
                    $this->body
              \n  </body>
              \n</html>";
    }
    
    //Destructor calls display()
    function __destruct(){
      if (!$this->nodisplay){
        $this->display();
      }
    }   
    
  }

?>