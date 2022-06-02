<?php

  class cw_PowerPoint {
  
    /*
        Wrapper around PHPPowerPoint
    
    */
    
    private $objPHPPowerPoint;
    
    function __construct(){        
      /** Include path **/
      set_include_path(get_include_path() . PATH_SEPARATOR . CW_ROOT_UNIX.'/lib/phppowerpoint/Classes/');
      
      /** PHPPowerPoint */
      include 'PHPPowerPoint.php';
      
      /** PHPPowerPoint_IOFactory */
      include 'PHPPowerPoint/IOFactory.php';
      
      // Create new PHPPowerPoint object
      $objPHPPowerPoint = new PHPPowerPoint();
      
      // Set properties
      $objPHPPowerPoint->getProperties()->setCreator("ChurchWeb");
      $objPHPPowerPoint->getProperties()->setLastModifiedBy("ChurchWeb");
      $objPHPPowerPoint->getProperties()->setTitle("Lyrics for church service");
      $objPHPPowerPoint->getProperties()->setSubject("-");
      $objPHPPowerPoint->getProperties()->setDescription("-");
      $objPHPPowerPoint->getProperties()->setKeywords("-");
      $objPHPPowerPoint->getProperties()->setCategory("-");
      
      // Remove first slide
      $objPHPPowerPoint->removeSlideByIndex(0);
      
      $this->objPHPPowerPoint=$objPHPPowerPoint;    
    }
    
    private function createTemplatedSlide(PHPPowerPoint $objPHPPowerPoint,$background_image)
    {
    	// Create slide
    	$slide = $objPHPPowerPoint->createSlide();
    	
    	// Add background image
      if (!empty($background_image)){
        $shape = $slide->createDrawingShape();
        $shape->setName('Background');
        $shape->setDescription('Background');
        $shape->setPath(CW_ROOT_UNIX.'img/ppt/'.$background_image);
        $shape->setWidth(951);
        $shape->setHeight(720);
        $shape->setOffsetX(0);
        $shape->setOffsetY(0);
      }
      
      /*
      // Add logo
      $shape = $slide->createDrawingShape();
      $shape->setName('PHPPowerPoint logo');
      $shape->setDescription('PHPPowerPoint logo');
      $shape->setPath(CW_ROOT_UNIX.'img/ppt/phppowerpoint_logo.gif');
      $shape->setHeight(40);
      $shape->setOffsetX(10);
      $shape->setOffsetY(720 - 10 - 40);
      */
      
      // Return slide
      return $slide;
    }
    
    function add_front_cover($text){
      $currentSlide = $this->createTemplatedSlide($this->objPHPPowerPoint,CW_PPT_FRONT_COVER);      

      // Create a shape (text)
      $shape = $currentSlide->createRichTextShape();
      $shape->setHeight(200);
      $shape->setWidth(950);
      $shape->setOffsetX(12);
      $shape->setOffsetY(500);
      $shape->getAlignment()->setHorizontal( PHPPowerPoint_Style_Alignment::HORIZONTAL_CENTER );

      $lines=explode("\n",$text);
      foreach ($lines as $v){
        $textRun = $shape->createTextRun($v);
        $textRun->getFont()->setBold(true);
        $textRun->getFont()->setSize(30);
        $textRun->getFont()->setName('Arial');
        $textRun->getFont()->setColor( new PHPPowerPoint_Style_Color( 'FFFFFFFF' ) );
        $shape->createBreak();              
      }      
    }
    
    function add_slide($text,$smalltext="",$source_info="",$background_image='ppt_bg_black.jpg'){
      
      //Get lines into array and get their number
      $lines=explode("\n",$text);
      $no_lines=sizeof($lines);

      //$text_top=250;
      $text_top=100;
      $text_left=12;
      $max_lines=8;
      $font_size=34;
      $blank_lines=0;
      //$blank_lines=$max_lines-$no_lines;

      // Create templated slide
      $currentSlide = $this->createTemplatedSlide($this->objPHPPowerPoint,$background_image); // local function
      
      
      // Create a shape (text) for main text
      $shape = $currentSlide->createRichTextShape();
      $shape->setHeight(200);
      $shape->setWidth(950);
      $shape->setOffsetX($text_left);
      $shape->setOffsetY($text_top);
      $shape->getAlignment()->setHorizontal( PHPPowerPoint_Style_Alignment::HORIZONTAL_LEFT );

      for ($i=0;$i<$blank_lines;$i++){
        $textRun = $shape->createTextRun(" ");
        $textRun->getFont()->setBold(true);
        $textRun->getFont()->setSize($font_size);
        $textRun->getFont()->setName('Arial');
        $textRun->getFont()->setColor( new PHPPowerPoint_Style_Color( 'FFFFFFFF' ) );      
        $shape->createBreak();            
      }
            
      foreach ($lines as $v){
        $textRun = $shape->createTextRun($v);
        $textRun->getFont()->setBold(true);
        $textRun->getFont()->setSize($font_size);
        $textRun->getFont()->setName('Arial');
        $textRun->getFont()->setColor( new PHPPowerPoint_Style_Color( 'FFFFFFFF' ) );      
        $shape->createBreak();      
      }     

      if (!empty($smalltext)){
        // Create a shape (text) for small text
        $shape = $currentSlide->createRichTextShape();
        $shape->setHeight(50);
        $shape->setWidth(950);
        $shape->setOffsetX(0);
        $shape->setOffsetY(12);
        $shape->getAlignment()->setHorizontal( PHPPowerPoint_Style_Alignment::HORIZONTAL_CENTER );
        $textRun = $shape->createTextRun($smalltext);
        $textRun->getFont()->setBold(false);
        $textRun->getFont()->setSize(15);
        $textRun->getFont()->setName('Arial');
        $textRun->getFont()->setColor( new PHPPowerPoint_Style_Color( 'FFFFFFFF' ) );      
        $shape->createBreak();            
      }
      
      if (!empty($source_info)){
        // Create a shape (text) for source info
        $shape = $currentSlide->createRichTextShape();
        $shape->setHeight(50);
        $shape->setWidth(920);
        $shape->setOffsetX(13);
        $shape->setOffsetY(40);
        $shape->getAlignment()->setHorizontal( PHPPowerPoint_Style_Alignment::HORIZONTAL_LEFT );
        $textRun = $shape->createTextRun($source_info);
        $textRun->getFont()->setBold(true);
        $textRun->getFont()->setItalic(true);
        $textRun->getFont()->setSize(36);
        $textRun->getFont()->setName('Arial');
        $textRun->getFont()->setColor( new PHPPowerPoint_Style_Color( 'FFDDDDDD' ) );      
        $shape->createBreak();            
      }
    }   
    
    function save_file($full_path){
      // Save PowerPoint 2007 file
      $objWriter = PHPPowerPoint_IOFactory::createWriter($this->objPHPPowerPoint, 'PowerPoint2007');
      $objWriter->save($full_path);    
    }     
    
  }
?>