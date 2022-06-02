var DISPLAY={};

DISPLAY.actual_height=0;
DISPLAY.actual_width=0;
DISPLAY.pixel_factor=0;
DISPLAY.image_fade_time=1500;
DISPLAY.lyrics_fade_time=200;

DISPLAY.queued_bgimages=[];
DISPLAY.queued_slides=[];

DISPLAY.lyrics_settings={
	"font_face":"Arial,sans-serif",
	"font_size":50,
	"font_style":"bold",
	"color":"white",
	"stroke_color":"black",
	"stroke_width":2,
	"alpha":100,
	"align":"left",
	"fade_time":200,
	"left_margin":40,
	"line_spacing":1.1
};


DISPLAY.current_slide={
	"bg_src":""
};

DISPLAY.ll={};

	DISPLAY.ll.active_bg_index=0;
	DISPLAY.ll.active_cv_index=0;
	DISPLAY.ll.$bgimg1=undefined;
	DISPLAY.ll.$bgimg2=undefined;
	DISPLAY.ll.$cv1=undefined;
	DISPLAY.ll.$cv2=undefined;
	DISPLAY.ll.$nfcv=undefined; //non-fade canvas
	DISPLAY.ll.$lgcv=undefined; //legibility-enhancement canvas (semi-transparent grayshade b/w lyrics and bgimage)
	
	DISPLAY.ll.init=function($bgimg1,$bgimg2,$cv1,$cv2,$nfcv,$lgcv,width,height,path_to_black){
		var margin_y=Math.floor(($(window).height()-height)/2);
		var margin_x=Math.floor(($(window).width()-width)/2);

		this.actual_width=width;
		this.actual_height=height;
		
		this.active_bg_index=1;
		this.$bgimg1=$bgimg1;
		this.$bgimg2=$bgimg2;
		this.path_to_black=path_to_black;

		this.active_cv_index=1;
		this.$cv1=$cv1;
		this.$cv2=$cv2;		
		this.$nfcv=$nfcv;
		this.$lgcv=$lgcv;
		//This will clear the canvas on resize, but if we don't do it, later elements won't be rendered in the right place	
		this.$cv1.attr("height",height).attr("width",width).height(height).width(width).css('left',margin_x).css('top',margin_y);
		this.$cv2.attr("height",height).attr("width",width).height(height).width(width).css('left',margin_x).css('top',margin_y);
		this.$nfcv.attr("height",height).attr("width",width).height(height).width(width).css('left',margin_x).css('top',margin_y);
		this.$lgcv.attr("height",height).attr("width",width).height(height).width(width).css('left',margin_x).css('top',margin_y);
		this.clear_canvas($cv1);
		this.clear_canvas($cv2);
		this.clear_canvas($nfcv);
		//this.init_lgcv(0.5);
		
		this.$cv1.addClass('active').removeClass('inactive');
		this.$cv2.removeClass('active').addClass('inactive').css('opacity',0);
		
		//Div for background images
		this.$bgimg1.parent().height(height).width(width).css('left',margin_x).css('top',margin_y);
		//Background images
		this.$bgimg1.height(height).width(width);
		this.$bgimg2.height(height).width(width);
		this.$bgimg1.addClass('active').removeClass('inactive');
		this.$bgimg2.removeClass('active').addClass('inactive').css('opacity',0);
		
	};
	
	DISPLAY.ll.init_lgcv=function(opacity){
		this.clear_canvas(this.$lgcv);
		var context=this.$lgcv[0].getContext('2d');
		context.fillStyle="black";
		context.fillRect(0,0,this.actual_width,this.actual_height);
		this.$lgcv.css('opacity',opacity);
	};
	
	DISPLAY.ll.$get_active_bgimg=function(){
		if (this.active_bg_index==1){
			return this.$bgimg1;
		} else {
			return this.$bgimg2;
		}
	};
	
	DISPLAY.ll.$get_inactive_bgimg=function(){
		if (this.active_bg_index==2){
			return this.$bgimg1;
		} else {
			return this.$bgimg2;
		}
	};
	
	DISPLAY.ll.$get_active_cv=function(){
		if (this.active_cv_index==1){
			return this.$cv1;
		} else {
			return this.$cv2;
		}
	};
	
	DISPLAY.ll.$get_inactive_cv=function(){
		if (this.active_cv_index==2){
			return this.$cv1;
		} else {
			return this.$cv2;
		}
	};
	
	DISPLAY.ll.swap_canvases=function(fade_time,r){
		this.$get_inactive_cv().animate({ opacity:1 },fade_time).removeClass('inactive').addClass('active').css('z-index',10);
		var _this=this;
		this.$get_active_cv().animate({ opacity:0 },fade_time,function(){
			//Clear after canvas has become invisible/inactive
			_this.active_cv_index==1 ? _this.active_cv_index=2 : _this.active_cv_index=1;
			_this.clear_canvas(_this.$get_inactive_cv());
			r.resolve();
		}).removeClass('active').addClass('inactive').css('z-index',9);
	};
	
	DISPLAY.ll.swap_bgimgs=function(fade_time){
		var r=$.Deferred();
		this.$get_inactive_bgimg().animate({ opacity:1 },fade_time,function(){r.resolve();}).removeClass('inactive').addClass('active').css('z-index',10);
		this.$get_active_bgimg().animate({ opacity:0 },fade_time).removeClass('active').addClass('inactive').css('z-index',9);
		this.active_bg_index==1 ? this.active_bg_index=2 : this.active_bg_index=1;
		return r;
	};
	
	DISPLAY.ll.clear_bgimg=function($bgimg){
		$bgimg.src=this.path_to_black;
	};
	
	DISPLAY.ll.clear_canvas=function($canvas){
		var context=$canvas[0].getContext('2d');
		context.clearRect(0,0,$canvas[0].width,$canvas[0].height);		
	};
	


//Init with Jquery elements. aspect is string like '4:3'
DISPLAY.init=function($bgimg1,$bgimg2,$cv1,$cv2,$nfcv,$lgcv,path_to_black,aspect,resize_only){
	this.path_to_black=path_to_black;	
	if (typeof aspect==='undefined'){
		aspect='16:9';
	}
	var 
		av_height=$(window).height(),
		av_width=$(window).width();	
	var t=aspect.split(':'),
		aspect_x=t[0],
		aspect_y=t[1];
	
	var factor;
	
	if ((aspect_x/aspect_y)<(av_width/av_height)){
		//Screen wider than feed
		factor=av_height/aspect_y;
	} else {
		//Feed wider than screen
		factor=av_width/aspect_x;
	}
	
	this.actual_height=aspect_y*factor;
	this.actual_width=aspect_x*factor;
	this.pixel_factor=this.actual_width/1024;
		
	this.ll.init($bgimg1,$bgimg2,$cv1,$cv2,$nfcv,$lgcv,this.actual_width,this.actual_height,path_to_black);
	if (!resize_only){
		this.clear_background(500);
	}
	this.current_slide={};
};

//q_object has 'src' and 'fade_time'
DISPLAY.output_bgimage_=function(q_object){
	var r=$.Deferred();
	if (this.current_slide['bg_src']!=q_object['src']){
		this.current_slide['bg_src']=q_object['src'];
		var $inactive=this.ll.$get_inactive_bgimg();
		//Clear inactive image and reload with new source
		this.ll.clear_bgimg($inactive);
		var _this=this;
		var fade_time=0;
		if (typeof q_object['fade_time']!=='undefined'){
			//use specific fade time if given
			fade_time=q_object['fade_time'];
		} else {
			//use default fade time
			fade_time=this.image_fade_time;
		}
		if ($inactive.attr('src')!=q_object['src']){
			//The target img obj has a different source - load new one
			$inactive.attr('src',q_object['src']).one('load',function(){
				//Swap after image is loaded
				_this.ll.swap_bgimgs(fade_time).done(function(){r.resolve();});
			});							
		} else {
			//The targe img obj has wanted source already - swap immediately
			_this.ll.swap_bgimgs(fade_time).done(function(){r.resolve();});
		}
	} else {
		//New src is the same - resolve immediately
		r.resolve();
	}
	return r;
};

DISPLAY.output_bgimage=function(src,fade_time){
	if ((this.queued_bgimages.length==0) || (this.queued_bgimages[0]['src']!=src) && (src!=this.current_slide['bg_src'])){
		this.queued_bgimages.unshift({ 'src':src,'fade_time':fade_time});
		this.process_bgimage_queue();		
	}
};

DISPLAY.process_bgimage_queue=function(self_call){
	if (this.queued_bgimages.length>0){
		//There is at least one image in the queue
		if ((!this.busy_bgimage) || (self_call)){
			//The queue is not currently being processed
			this.busy_bgimage=true;
			var _this=this;
			this.output_bgimage_(this.queued_bgimages.pop()).done(function(){
				_this.process_bgimage_queue(true);
			});
		}		
	} else {
		if (self_call){
			this.busy_bgimage=false;
		}
	}
};

DISPLAY.clear_background=function(fade_time){
	this.output_bgimage(this.path_to_black,fade_time);
};

//Get the max font size for the string content (single line)
DISPLAY.get_maximum_font_size=function(content,context,font_face,max_width){
	var res=0,l=0;
	var backup=context.font;
	do {
		l++;
		res=l*this.pixel_factor; //font size to test
		context.font=res.toString()+'px '+this.lyrics_settings['font_face'];
	} while ((context.measureText(content).width<max_width) && (l<100)); //Safety: about after 100 tries
	context.font=backup;
	return res;
};

//Break the string content into multiple lines such that each line fits on the within max_width
DISPLAY.get_multiple_lines=function(content,context,max_width){
	var lines=[];
	var this_line='';
	var q;
	do {
		this_line=content;
		for (q=content.length;context.measureText(this_line).width>max_width;q--){
			this_line=content.substr(0,q);
		}
		//If pos q is not a space, find last space before q
		q=this_line.lastIndexOf(' ');
		this_line=this_line.substr(0,q);
		
		content=content.substr(q+1); //cut off the part that was in this line
		lines.push(this_line);
	} while (context.measureText(content).width>max_width);
	lines.push(content); //rest of string
	return lines;
};

DISPLAY.clear_nonfade_canvas=function(){
	this.ll.clear_canvas(this.ll.$nfcv);
};

DISPLAY.write_on_nonfade_canvas=function(type,content){
	var context=this.ll.$nfcv[0].getContext('2d');
	if (type=='source_info'){
		context.lineWidth=Math.round(this.lyrics_settings['stroke_width']*this.pixel_factor).toString();
		context.strokeStyle=this.lyrics_settings['stroke_color'];
		context.fillStyle=this.lyrics_settings['color'];
		var x=this.lyrics_settings['left_margin']*this.pixel_factor;
		var actual_font_size=this.lyrics_settings['font_size']*this.pixel_factor;
		var offset=100*this.pixel_factor+actual_font_size;
		context.font='italic '+actual_font_size.toString()+'px '+this.lyrics_settings['font_face'];
		context.fillText(content,x,offset);
		context.strokeText(content,x,offset);
	} else if (type=='credits'){
		context.lineWidth=Math.round(this.lyrics_settings['stroke_width']*this.pixel_factor).toString();
		context.strokeStyle=this.lyrics_settings['stroke_color'];
		context.fillStyle=this.lyrics_settings['color'];
		var x=this.actual_width/2;
		context.textAlign='center';
		var maximum_font_size_for_credits=26*this.pixel_factor;
		var minimum_font_size_for_credits=20*this.pixel_factor;
		var max_credits_width=this.actual_width*0.92;		
		var text_top=25*this.pixel_factor;//top margin
		var actual_font_size;		
		actual_font_size=this.get_maximum_font_size(content,context,this.lyrics_settings['font_face'],max_credits_width);
		
		if (actual_font_size>=minimum_font_size_for_credits){
			//One line fits
			if (actual_font_size>maximum_font_size_for_credits){
				//Limit maximum font size
				actual_font_size=maximum_font_size_for_credits;				
			}
			context.font=actual_font_size+'px '+this.lyrics_settings['font_face'];
			context.fillText(content,x,text_top+actual_font_size);			
		} else if (actual_font_size<minimum_font_size_for_credits){			
			//Need to break into multiple lines
			actual_font_size=minimum_font_size_for_credits;
			context.font=actual_font_size+'px '+this.lyrics_settings['font_face'];
			var lines=this.get_multiple_lines(content,context,max_credits_width);
			var line_spacing=1.3;
			var this_line='';
			for(var i=0;i<lines.length;i++){
				this_line=lines[i]; 
				var offset=text_top+i*actual_font_size*line_spacing;
				context.fillText(this_line,x,offset);
			}													
		}		
		context.textAlign='start';
	}
};

DISPLAY.write_on_next_canvas=function(type,content){
	var context=this.ll.$get_inactive_cv()[0].getContext('2d');
	var flip_canvases=true;
	if (type=='source_info'){
		context.lineWidth=Math.round(this.lyrics_settings['stroke_width']*pixel_factor).toString();
		context.strokeStyle=this.lyrics_settings['stroke_color'];
		context.fillStyle=this.lyrics_settings['color'];
		var x=this.lyrics_settings['left_margin']*this.pixel_factor;
		var actual_font_size=this.lyrics_settings['font_size']*this.pixel_factor;
		var offset=100*this.pixel_factor+actual_font_size;
		context.font='italic '+actual_font_size.toString()+'px '+this.lyrics_settings['font_face'];
		context.fillText(content,x,offset);
		context.strokeText(content,x,offset);
	} else if (type=='lyrics'){
		if ((this.current_slide['lyrics']!=content) || (content=='')){
			this.current_slide['lyrics']=content;
			var actual_font_size=this.lyrics_settings['font_size']*this.pixel_factor; //default value
			var line_spacing=this.lyrics_settings['line_spacing'];		
			context.font=this.lyrics_settings['font_style']+' '+actual_font_size.toString()+'px '+this.lyrics_settings['font_face'];
			context.lineWidth=Math.round(this.lyrics_settings['stroke_width']*this.pixel_factor).toString();
			context.strokeStyle=this.lyrics_settings['stroke_color'];
			context.fillStyle=this.lyrics_settings['color'];		
			//Deal with multiple lines in sequence		
			var lines=content.split('\n');
			var text_top=this.actual_height-(lines.length*actual_font_size*line_spacing)-(20*this.pixel_factor);
			var text_left=this.lyrics_settings['left_margin']*this.pixel_factor;
			for(var i=0;i<lines.length;i++){
				var offset=text_top+i*actual_font_size*line_spacing;
				context.save();
			    context.shadowColor = '#000';
			    context.shadowBlur = 3*this.pixel_factor;
			    context.shadowOffsetX = 4*this.pixel_factor;
			    context.shadowOffsetY = 4*this.pixel_factor;
				context.fillText(lines[i],text_left,offset);
				context.restore();
				context.strokeText(lines[i],text_left,offset);				
			}
		} else {
			flip_canvases=false;
		}
	} else if (type=='instant_message'){
		this.current_slide['lyrics']='';
		context.lineWidth=Math.round(this.lyrics_settings['stroke_width']*this.pixel_factor).toString();
		context.strokeStyle=this.lyrics_settings['stroke_color'];
		context.fillStyle="yellow";
		var x=this.actual_width/2;
		context.textAlign='center';
		var maximum_font_size_for_im=60*this.pixel_factor;
		var minimum_font_size_for_im=40*this.pixel_factor;
		var max_credits_width=this.actual_width*0.92;		
		var text_top;
		var actual_font_size;		
		actual_font_size=this.get_maximum_font_size(content,context,this.lyrics_settings['font_face'],max_credits_width);
		
		if (actual_font_size>=minimum_font_size_for_im){
			//One line fits
			if (actual_font_size>maximum_font_size_for_im){
				//Limit maximum font size
				actual_font_size=maximum_font_size_for_im;				
			}
			text_top=this.actual_height/2-actual_font_size/2;
			context.font=actual_font_size+'px '+this.lyrics_settings['font_face'];
			context.fillText(content,x,text_top+actual_font_size);			
		} else if (actual_font_size<minimum_font_size_for_im){			
			//Need to break into multiple lines
			actual_font_size=minimum_font_size_for_im;
			context.font=actual_font_size+'px '+this.lyrics_settings['font_face'];
			var lines=this.get_multiple_lines(content,context,max_credits_width);
			var line_spacing=1.3;
			var this_line='';
			text_top=this.actual_height/2-(actual_font_size*line_spacing*lines.length)/2;
			for(var i=0;i<lines.length;i++){
				this_line=lines[i]; 
				var offset=text_top+i*actual_font_size*line_spacing;
				context.fillText(this_line,x,offset);
			}													
		}		
		context.textAlign='start';
	} else if (type=='cover_text_service_name'){
		context.textAlign='center';
		context.fillStyle='white';
		context.font='bold '+(40*this.pixel_factor)+'px Arial,sans-serif';
		context.fillText(content,this.actual_width/2,530*this.pixel_factor);
		context.textAlign='start';		
	} else if (type=='cover_text_service_title'){
		context.textAlign='center';
		context.fillStyle='white';
		context.font='bold '+(40*this.pixel_factor)+'px Arial,sans-serif';
		context.fillText(content,this.actual_width/2,580*this.pixel_factor);
		context.textAlign='start';		
	}
	return flip_canvases;
};

//r is $.Deferred object, pass on to animation method
DISPLAY.next_canvas=function(custom_fade_time,r){
	if (!(typeof custom_fade_time==='undefined')){
		this.ll.swap_canvases(custom_fade_time,r);		
	} else {
		this.ll.swap_canvases(this.lyrics_fade_time,r);		
	}
};

function cwp_display_countdown(canvas,countdown_timer_target){
	//countdown_timer_target is actual timestamp in milliseconds
	var d=new Date();
	var duration=Math.round((countdown_timer_target-d.getTime())/1000);
	var duration_string;
	var minutes=Math.floor(duration/60);
	var seconds=(duration%60);
	if (seconds.toString().length==1){
		seconds='0'+seconds;
	}
	duration_string=minutes+':'+seconds;
	var context=canvas.getContext('2d');
	var actual_fontsize=60*pixel_factor;
	context.font='bold '+actual_fontsize+'px Arial,sans-serif';
	context.lineWidth='1';
	context.strokeStyle='black';
	context.fillStyle='rgb(200,200,200)';
	var x=1000*pixel_factor;
	var y=70*pixel_factor;
	var textwidth=context.measureText("00:00").width;
	context.clearRect(x-textwidth,y-actual_fontsize+5*pixel_factor,textwidth,actual_fontsize);
	context.textAlign='right';
	context.fillText(duration_string,x,y);
	context.textAlign='start';
	duration--;
	if (duration>=0){
		countdown_timer=setTimeout(function(){cwp_display_countdown(canvas,countdown_timer_target);},1000);
	}
	if (duration<4)
	{
		$('#cvcountdown').fadeOut(5000);
	}
}

function cwp_init_countdown(duration){
	var canvas=document.getElementById('cvcountdown');
	var context=canvas.getContext('2d');
	context.clearRect(0,0,canvas.width,canvas.height);
	$('#cvcountdown').show();
	//duration is seconds
	var d=new Date();
	countdown_timer_target=d.getTime()+(duration*1000);
	countdown_timer=setTimeout(function(){cwp_display_countdown(canvas,countdown_timer_target);},1000);	
}

function cwp_clear_countdown(){
	if (countdown_timer>0){
		$('#cvcountdown').fadeOut(3000,function(){
			clearTimeout(countdown_timer);			
		});			
	}
}



DISPLAY.display_slide_=function(data){
	var r=$.Deferred();
	var flip_canvases=true;
	var custom_fade_time=this.lyrics_fade_time; //gets overwritten by cover_text for welcome slide
	this.clear_nonfade_canvas();
	for (var k=0;k<data.length;k++){
		obj=data[k];
		if ('type' in obj){
			if (obj['type']=='background_image'){
				this.output_bgimage(obj['src']);
			} else if (obj['type']=='cover_text'){
				this.write_on_next_canvas('cover_text_service_name',obj['service_name']);
				this.write_on_next_canvas('cover_text_service_title',obj['service_title']);
				custom_fade_time=this.image_fade_time;
			} else if ((obj['type']=='source_info') || (obj['type']=='credits')){
				this.write_on_nonfade_canvas(obj['type'], obj['content']);
			} else {
				flip_canvases=this.write_on_next_canvas(obj['type'],obj['content']);
			}
		}
	}
	if (flip_canvases){
		this.next_canvas(custom_fade_time,r);		
	} else {
		r.resolve();
	}		
	if (typeof do_not_clear_countdown==='undefined'){
		//Clear countdown unless this is a slide using the countdown
		//cwp_clear_countdown(countdown_timer);		
	}
	return r;
};

DISPLAY.display_slide=function(data){
	this.queued_slides.unshift(data);
	this.process_slide_queue();
};

DISPLAY.process_slide_queue=function(self_call){
	if (this.queued_slides.length>0){
		//There is at least one slide in the queue
		if ((!this.busy_slide) || (self_call)){
			//The queue is not currently being processed
			this.busy_slide=true;
			var _this=this;
			this.display_slide_(this.queued_slides.pop()).done(function(){
				_this.process_slide_queue(true);
			});
		}		
	} else {
		if (self_call){
			this.busy_slide=false;
		}
	}
};


// OPERATOR INTERFACE UTILS

