<?php

  require_once "../lib/framework.php";

  $node_server="http://192.168.1.200:4001";
  
  $p->h("<script src=\"$node_server/socket.io/socket.io.js\"></script>");
  $p->h("<script src=\"".CW_ROOT_WEB."lib/cwp_utils.js\"></script>");
  
  $p->stylesheet(CW_ROOT_WEB."css/projector_feeds.css");
  
  if (empty($_GET["action"])){ 
	  
	  	$p->jquery("
	  		
	  		
	  			function send(socket,data){
	  				//$('#feedlist').append('<br>Sending: '+data);
	  				socket.emit('data',data);
				}
	  		
	  			function request_feedlist(){
	  				send(socket,JSON.stringify({'cmd':'get_feeds'}));
	  			}
	  		
	  			if (!(typeof io==='undefined')) {
	  				//node server has served socket.io and should be reachable
	  			 	var socket=io.connect('$node_server');  
	  			
	  				var names_cache={};

		  			socket.on('error',function(){
						$('#feedlist').html('Unable to connect to the presentation server');	
					});
		  			
					socket.on('connect',function(){
		  				$('#feedlist').html('Requesting feedlist...');
						send(socket, JSON.stringify({'cmd':'set_mode','mode':'display'}));
					});
		  		
	
		  		
					socket.on('data',function(content){
		  				var rcv=JSON.parse(content);
		  		
		  				if (rcv['code']=='2010'){
		  					//Mode set to display
		  					request_feedlist();
		  				}
		  		
		  				if (rcv['code']=='2500'){
		  					//Got feeds
		  					$('#feedlist').html('');
	  						$('#orphaned_feeds').html('');
	  						var feeds=JSON.parse(rcv['feeds']);
		  					if (Object.keys(feeds).length>0){	  			
			  					for (var feed in feeds){
		  							var title='';
			  						if (('title' in feeds[feed]) && (typeof feeds[feed]['title']!='undefined')){
			  							title=feeds[feed]['title'];
			  						}
			  						if (title==''){
			  							title=feeds[feed]['id'];
			  						}
	  								var feed_status_class='feed_ready';
	  								if ('feed_status' in feeds[feed]){
	  									if (feeds[feed]['feed_status']=='orphaned'){
	  										feed_status_class='feed_orphaned'
	  										$('#orphaned_feeds').append('<div class=\'feed_desc feed_orphaned\' id=\'o'+feeds[feed]['id']+'\'><div class=\'orphaned_feed_head\'>Resume orphaned feed:</div><div class=\'feed_title\'>'+title+'</div></div>');}
	  										$('#o'+feeds[feed]['id']).click(function(){
	  											window.location='?action=operate&feed='+$(this).attr('id').substr(1);
  											}).disableSelection();
	  			
	  			
	  								}
	  								if (feeds[feed]['operator_person_id']>0){
	  									//feed is not orphaned->subtract 2 viewers (pre/pgm)
	  									feeds[feed]['viewers']=feeds[feed]['viewers']-2;
	  								}
			  						$('#feedlist').append('<div class=\'feed_desc\' id=\'f'+feeds[feed]['id']+'\'><div class=\'feed_title\'>'+title+'</div><div class=\'feed_operator\'>Operator: <span id=\'fn'+feeds[feed]['id']+'\'>loading...</span></div><div class=\'feed_viewers\'>Viewers: '+feeds[feed]['viewers']+'</div></div>');	  		
	  								$('#f'+feeds[feed]['id']).click(function(){
	  									window.location='?action=display&feed='+$(this).attr('id').substr(1);
  									}).addClass(feed_status_class).disableSelection();
	  								
	  								
	  								if (feeds[feed]['operator_person_id'] in names_cache){
	  									$('#fn'+feeds[feed]['id']).html(names_cache[feeds[feed]['operator_person_id']]);
	  								} else {
	  									//This has to be synchronous because of the for loop
					  					$.ajax({
										  	url: '".CW_ROOT_WEB."ajax/ajax_projector_feeds.php?action=get_operator_name&person_id='+feeds[feed]['operator_person_id'],
  											success: function(res){
	  											names_cache[feeds[feed]['operator_person_id']]=res;
	  											$('#fn'+feeds[feed]['id']).html(names_cache[feeds[feed]['operator_person_id']]);
  											},
  											async: false
										});
	  			
	  								}
			  					}
		  					} else {
	  							$('#feedlist').load('".CW_ROOT_WEB."ajax/ajax_projector_feeds.php?action=get_autoconnect_button');
		  					}
		  					setTimeout(function(){request_feedlist();},15000);
		  				}
					});
	  				  			
	  			
  				} else {
					$('#feedlist').html('Unable to connect to the presentation server');	
	  			}
				
	  			
	  		
	  		
	  	");
	  	$p->p("
	  			<div class=\"list_container\">
	  				<h3>Display a projector feed</h3>
	  				<div class='list' id='feedlist'>Attempting to connect to presentation server...</div>
	  			</div>
	  			
	  			<div class=\"list_container\">
	  				<h3>Operate a projector feed</h3>
	  				<div class='list'>
	  					<div id='orphaned_feeds'></div>
	  					<div id='servicelist'>Retrieving upcoming services...</div>
	  				</div>
	  			</div>
	  	");
	  	
	  	$p->jquery("
	  		function get_upcoming_services(){
		  		$('#servicelist').load('".CW_AJAX."ajax_projector_feeds.php?action=get_services_list');
  			}
	  		
	  		get_upcoming_services();
	  	");
	  	
	} else {
		if ($_GET["action"]=="display"){
			//Display feed
			$p->nodisplay=true;
			if (empty($_GET["subfeed"])){
				$subfeed="program";
			} else {
				$subfeed=$_GET["subfeed"];
			}
			//If no feed id is provided, auto connect to first available feed
			$autoconnect="false";
			if (empty($_GET["feed"])||($_GET["feed"]=="auto")){
				$autoconnect="true";
			}
			$t="
			<!doctype html>
			<html>
				<head>
					<meta charset=\"utf-8\">
					<title>ChurchWeb Projector Feed</title>
					<link href=\"".CW_ROOT_WEB."css/cwp.css\" rel=\"stylesheet\">
					<script src=\"".CW_ROOT_WEB."lib/jquery/jquery-2.0.3.min\"></script>
					<script src=\"".CW_ROOT_WEB."lib/clock/jwclock.js\"></script>
					<script src=\"".CW_ROOT_WEB."lib/cwp_utils.js\"></script>
					<script src=\"$node_server/socket.io/socket.io.js\"></script>
					<script>

					
			        function keep_alive(){
			          $.get('".CW_AJAX."ajax_home.php',function(rtn){
			          });
			        }
			        setInterval('keep_alive()',".(CW_KEEP_ALIVE*1000).");
					
					$(document).ready(function(){
																
							function send(socket,data){
								//$('#feedlist').append('<br>Sending: '+data);
								socket.emit('data',data);
							}
			
							var subfeed='$subfeed';
										 
							var socket=io.connect('$node_server');

							var autoconnect=$autoconnect;
														
							function autoconnect_to_feed(){
								//Request feedlist
								send(socket,JSON.stringify({'cmd':'get_feeds'}));
							}
							
							socket.on('connect',function(){																			
								send(socket,JSON.stringify({'cmd':'set_mode','mode':'display','person_id':".$a->cuid."}));			
							});

							socket.on('disconnect',function(){
								//alert('Lost connection to CWP server');
							});
							
							socket.on('data',function(content){
								var rcv=JSON.parse(content);
								if (rcv['code']=='2010'){
									//Mode set to display
									if (autoconnect){
										autoconnect_to_feed();
									} else {
										send(socket,JSON.stringify({'cmd':'connect_to_feed','id':'".$_GET["feed"]."','subfeed':'$subfeed'}));
									}
								}
			
								if (rcv['code']=='2510'){
									//Connected to feed
									CLOCK.stopClock();
									init_to_default();
								}
				  	
				  				if (rcv['code']=='3000'){
				  					//Data
				  					var d=rcv['data'];
				  					if ('cmd' in d){
				  						if (d['cmd']=='display_slide'){
											if ( ((!('subfeed' in d)) && (subfeed=='program')) || (d['subfeed']==subfeed) ){
												//this data is for the subfeed we want; display												
					  							if ('data' in d){
				  									DISPLAY.display_slide(d['data']);
					  							}
											}
				  						} else if (d['cmd']=='setup_lyrics'){
										}
				  					}
				  				}
				  				
				  				if (rcv['code']=='3999'){
				  					//Feed terminated on server
				  					init_to_default();
				  					//check for available feeds and hook into first one available - check periodically
									autoconnect_to_feed();
				  				}
				  				
				  				if (rcv['code']=='2500'){
				  					var feeds=JSON.parse(rcv['feeds']);
				  					//Got feeds
				  					if (Object.keys(feeds).length>0){
				  						//Got at least one feed
				  						var feed;
				  						for (feed in feeds){
				  							break;
				  						}
				  						//Feed id is feed - connect
										send(socket,JSON.stringify({'cmd':'connect_to_feed','id':feed,'subfeed':'$subfeed'}));
				  					} else {
				  						//No feeds currentlay available. Try again later.
				  						init_to_default();
				  						setTimeout(function(){send(socket,JSON.stringify({'cmd':'get_feeds'}));},15000);
										CLOCK.startClock($('#cvclock'),'".CW_ROOT_WEB."lib/clock/');
				  					}
				  				}
							});
				 
							
							function init_to_default(resize_only){
								DISPLAY.init($('#bg1'),$('#bg2'),$('#cv1'),$('#cv2'),$('#nfcv'),$('#lgcv'),'".CW_ROOT_WEB."img/black.gif','16:9',resize_only);
							}	
																	
							$(window).resize(function(){
								init_to_default(true);
							});			
			
						});
			 
						
			
					</script>
				</head>
				<body>
				  	<div id='bg'>
				  		<img id='bg1' src='".CW_ROOT_WEB."img/black.gif' class='inactive' style='opacity:0;'>
						<img id='bg2' src='".CW_ROOT_WEB."img/black.gif' class='active'>
				  	</div>
					<canvas id='lgcv' class='canvases'>
					</canvas>										
					<canvas id='cvclock' class='canvases'>
					</canvas>
					<canvas id='nfcv' class='canvases'>
					</canvas>		
					<canvas id='cv1' class='canvases inactive'>
			  		</canvas>
					<canvas id='cv2' class='canvases active'>
					</canvas>
					<canvas id='cvcountdown' class='canvases'>
					</canvas>									
				</body>
			</html>
			";
			echo $t;
		} elseif ($_GET["action"]=="operate"){
			$p->nodisplay=true;
			
			$eh=new cw_Event_handling($a);		
			
			$resume="no";
			if (!empty($_GET["feed"])){
				$feed_id=$_GET["feed"];
				$feed_details=explode('-',$feed_id);
				//second element has service id
				$_GET["service_id"]=$feed_details[1];
				$resume="yes";
			} else {
				$feed_id=time()."-".$_GET["service_id"]."-".$a->cuid;
			}
			
			$service=new cw_Church_service($eh, $_GET["service_id"]);
				
			$feed_title=$service->service_name.": ".$service->title;
			
			$t="
			<!doctype html>
			<html>
				<head>
					<meta charset=\"utf-8\">
					<title>ChurchWeb Projector Operator - ".$a->personal_records->get_name_first_last($a->cuid)."</title>
					<link href=\"".CW_ROOT_WEB."css/cwp.css\" rel=\"stylesheet\">
					<script src=\"".CW_ROOT_WEB."lib/jquery/jquery-2.0.3.min.js\"></script>
					<script src=\"".CW_ROOT_WEB."lib/jquery/jquery-ui-1.8.20.custom.min.js\"></script>
					<script src=\"$node_server/socket.io/socket.io.js\"></script>
					<script>
						//Global
						var socket;
						var feed;
						var aspect_x; //feed aspect
						var aspect_y;
						
						var last_instant_message='';

						var show_on_hold=false; //is true when one of the nav_panel_items are active

						var service_elements=[]; //populated with ids by load_service_order
						var current_service_element=0; //Service element loaded in #current_service_element
						var slides={};
						var pgm={service_element:0,slide_no:0}; //pointer to current pgm data, has properties service_element, and slide_no
						var pre={service_element:0,slide_no:1}; //ibid
						
						
						var initial_load=true;
						var ui_ready=false; //gets set to true once everything is loaded
						
						
				        function keep_alive(){
				          $.get('".CW_AJAX."ajax_home.php',function(rtn){
				          });
				        }
				        setInterval('keep_alive()',".(CW_KEEP_ALIVE*1000).");						
						
					</script>
					<script src=\"".CW_ROOT_WEB."lib/cwp_operator.js\"></script>							
					<script>
					
						
						function send(socket,data){
							socket.emit('data',data);
						}


				    	function show_please_wait(message){
							//alert(window.innerWidth);
				      		$('#please_wait').remove();    
							$('body').append('<div id=\"please_wait\" style=\"left:'+(window.innerWidth/2-250)+'px;\">' + message + '</div>');
				    	}
				      
				    	function hide_please_wait(){
				      		$('#please_wait').remove();    
				    	}
							
						
				        init_service_plan_sync();
				      
				        //Reload the service plan if the ajax request sends the RELOAD signal      
				        function init_service_plan_sync(){
				          $.get('".CW_AJAX."ajax_projector_feeds.php?action=check_for_service_plan_update&service_id=".$_GET["service_id"]."',function(rtn){
				            if (ui_ready && (rtn=='RELOAD')){
				          	  ui_ready=false;
				          	  show_please_wait('Loading updated service data...');
				              load_service_order().done(function(){
				          	  	load_slide_data().done(function(){
				          		  markup_all();
				          		  ui_ready=true;
				          		  hide_please_wait();
								});
							  });
				            }
				          });
				          setTimeout(function(){init_service_plan_sync();},".CW_SERVICE_PLANNING_SYNC_INTERVAL."*1000);              
				        }
						
						function load_service_order(){
				          	r=$.Deferred();
				          	$('#service_order_container').load('".CW_ROOT_WEB."ajax/ajax_projector_feeds.php?action=get_service_order&service_id=".$_GET["service_id"]."',function(){
				          			r.resolve();
							});
				          	return r;
						}
				          							          			
				        function load_nav_panel(){
				          	r=$.Deferred();
				          	$('#nav_panel').load('".CW_ROOT_WEB."ajax/ajax_projector_feeds.php?action=get_nav_panel&service_id=".$_GET['service_id']."',function(){
				          		var nav_panel_width=$('#nav_panel').width();
				          		var thumb=nav_panel_width/15;
				          		$('#nav_panel .nav_panel_item').width(thumb*aspect_x/4).height(thumb*aspect_y/4);
				          		r.resolve();
							});
				          	return r;
				        }
						
				        function load_slide_data(){
				          	r=$.Deferred();
				          	$.get('".CW_ROOT_WEB."ajax/ajax_projector_feeds.php?action=load_all_slide_data&service_id=".$_GET["service_id"]."',function(res){
				          		eval(res);
				          		//console.log(slides);
				          		r.resolve();
							});
				            return r;
				        }  			
				        
						function send_slide(slide_data,subfeed){
							if (typeof subfeed==='undefined'){
								subfeed='program';
							}
							send(socket,JSON.stringify( 
								{
									'cmd':'data',
									'data': {
												'cmd':'display_slide',
												'subfeed':subfeed,
												'data': slide_data				
											} 		
								}
							));
						}

						function show_slide_thumbs(service_element,noscroll){
							var lslides='';
							var files='';
							if (typeof slides['$'+service_element]!=='undefined'){
								for (var i=0;i<slides['$'+service_element].files.length;i++){
									var this_file=slides['$'+service_element].files[i];
									files=files+'<div class=\"filethumb\" id=\"ft'+this_file.id+'\">Attached file: '+this_file.name+' ('+this_file.size+')</div>';
								}
								for (var i=0;i<slides['$'+service_element].slides.length;i++){
									var is_lyrics_slide=slides['$'+service_element].slides[i][0]['type']=='lyrics';
									if (is_lyrics_slide){
										var fragment_type=slides['$'+service_element].slides[i][0]['fragment'];
										var content=slides['$'+service_element].slides[i][0]['content'].replace(/\\n/g, ' / ');
										lslides=lslides+'<div class=\"slidethumb\"><div class=\"slide_fragment\">'+fragment_type+'</div>'+content+'<br style=\"clear:both;\"></div>';
									} else {
										//Not a lyrics slide
										var slide_desc='slide';
										if (slides['$'+service_element].slides[i][0]['type']=='background_image'){
											slide_desc='background image';
										}
										lslides=lslides+'<div class=\"slidethumb\">'+slide_desc+'<br style=\"clear:both;\"></div>';
									}
								}
								
								if (lslides==''){
									lslides='<div>This element has no slides</div>';
								}	
							} else {
									lslides='<div>This service element has no slides</div>';
							}
							current_service_element=service_element;
							if ((files!='') && (lslides!='')){
								$('#current_service_element').html(files+'<div class=\"thumbspacer\"></div>'+lslides);
							} else {
								$('#current_service_element').html(files+lslides);
							}
							markup_all(noscroll);
							$('#current_service_element .slidethumb').click(function(e){
								if (ui_ready){
									var offset=get_no_of_filethumb_divs();
									if (e.shiftKey){
										slide_to_pgm(service_element,$(this).index()-offset);
									} else {
										slide_to_pre(service_element,$(this).index()-offset);
									}
								}
							});
							$('#current_service_element .filethumb').click(function(e){
								if (ui_ready){
									var this_file=slides['$'+service_element].files[$(this).index()];
									//Get window object of invisible iframe, and use that for download
									var x=document.getElementById('invisible_iframe');
									var y=(x.contentWindow || x.contentDocument);
									if (y.window)
										y=y.window;
									y.location.href=this_file.url;
								}
							});
						}
						
						$(document).ready(function(){
							$('#current_service_element').disableSelection();
						
							function navigate(offset){
								if (ui_ready){
									if (offset==1){
										slide_to_pgm(pre.service_element,pre.slide_no);									
									} else if (offset==-1){
										var prev=get_previous_slide();
										slide_to_pgm(prev.service_element,prev.slide_no);																		
									}
								}
							}
						
							$(window).keydown(function(e){
								if (ui_ready){
									var offset=0;
									var nav=false;
									if ((e.keyCode==39) || (e.keyCode==32) || (e.keyCode==40)){
										offset=1;
										nav=true;
									}
									if ((e.keyCode==37) || (e.keyCode==38)){
										offset=-1;
										nav=true;
									}					
									if (nav){
										navigate(offset);
									} else if (e.keyCode==66){
										$('#np_blackout').click();
									} else if (e.keyCode==87){
										$('#np_welcome').click();
									} else if (e.keyCode==73){
										$('#np_im').click();
									}
								}			
							});
							
							
						 	show_please_wait('Connecting to presentation server...please wait');
							socket=io.connect('$node_server');							
										
							socket.on('connect',function(){
								send(socket,JSON.stringify({'cmd':'set_mode','mode':'operator','person_id':".$a->cuid."}));
							});
			
							socket.on('data',function(content){
								//$('body').append('<br>Received: '+content);
								var rcv=JSON.parse(content);
								
								if (rcv['code']=='2010'){
									//Mode set to operator
									if ('$resume'=='yes'){
										send(socket,JSON.stringify({'cmd':'get_feed','id':'$feed_id'}));
									} else {
										send(socket,JSON.stringify({'cmd':'init_feed','id':'$feed_id','title':".json_encode($feed_title)."}));
									}
								}
								
								if ((rcv['code']=='2100') || (rcv['code']=='2101')) {
									//Init feed or get feed successful
									feed=JSON.parse(rcv['feed']);
									//Got feed info - prep display
									var r=feed['aspect'].split(':');
									aspect_x=r[0];
									aspect_y=r[1];
									var mon_height=($('#program').width()/aspect_x)*aspect_y;
									$('#program').html('<iframe src=\"?action=display&feed=$feed_id\"></iframe>');
									$('#preview').html('<iframe src=\"?action=display&feed=$feed_id&subfeed=preview\"></iframe>');

									//Load service order
									show_please_wait('Loading interface and service data...please wait');
									load_service_order().done(function(){
										load_slide_data().done(function(){
											load_nav_panel().done(function(){
												//Connect to feed											
												send(socket,JSON.stringify({'cmd':'connect_to_feed','id':'$feed_id'}));
												show_please_wait('Connecting with new presentation feed...please wait');
											});										
										});									
									});
									
								}
								
								if (rcv['code']=='2120'){
									//Connected to feed
									hide_please_wait();
									pgm.service_element=0;
									pgm.slide_no=0;
									pre.service_element=0;
									pre.slide_no=0;
									//Unlock UI
									ui_ready=true;
									if ((!('$resume'=='yes')) && (initial_load)){
										//This is an initial load (not a reconnect) AND a new feed. Default to blackout/welcome slide.
										initial_load=false;
										nav_panel_click(true,0);
										nav_panel_click(false,1);
									}
								}
								
								if (rcv['code']=='2140'){
									//Terminate feed successful - leave
									//hide_please_wait();
									window.location='?action=';
								}
			
							});				 
			
							$('#invisible_next_button').click(function(e){
								navigate(1);
							});
							
							$('#terminate').click(function(e){
						 		show_please_wait('Cleaning up...');
								e.preventDefault();
								send(socket,JSON.stringify({'cmd':'terminate_feed'}));
							});
						});
						
					</script>
				</head>
				<body>
					<div id='interface_container'>
						<div id='monitor_container'>
							<div class='monitor_label' style='left:2%'>PGM</div>
							<div class='monitor_label' style='left:34%;background:rgba(0,255,0,.5);'>PRE</div>
						
							<div class='monitor' id='program'>
								loading...
							</div>
							<div class='monitor' id='preview'>
								loading...
							</div>
							<div class='control' id='nav_panel' style='width:34%;'>
								loading...
							</div>
							<div id='invisible_next_button'></div>
						</div>
						<div id='controls_container'>
							<div class='control' id='service_order_container' style='overflow-y:scroll;width:62%;background:white;'>
								loading...
							</div>
							<div class='control' id='current_service_element'>
								Select a service element from the service order to load slides or hit the space bar to start with the first element that has slides.
							</div>
						</div>
						<a id='terminate' href='' style='color:white;font-weight:bold;'>Terminate feed and return</a>
						<iframe id='invisible_iframe' style='display:none;'></iframe>
					</div>
				</body>
			</html>
			";
			echo $t;
		}
	}  
  
  
?>
