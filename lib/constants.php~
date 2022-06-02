<?php
//Date-time related constants
define ("WEEK",604800);
define ("DAY",86400);
define ("HOUR",3600);
define ("MINUTE",60);
define ("CW_BIGGEST_TIMESTAMP",2147483647); //Jan 19 2038

//Basic constants for CW
define("CW_TIME_ZONE","America/Vancouver");
define("CW_CHURCH_NAME","King Road MB Church");
define("CW_CCLI_NUMBER","225145");
define("CW_TITLE","ChurchWeb at ".CW_CHURCH_NAME);
define("CW_ROOT_UNIX","/home/johannes/public_html/dev/cw2/");
define("CW_ROOT_WEB","/~johannes/dev/cw2/");
define("CW_HELP_DIR","help/");
define("CW_CHURCH_LOGO_FULL_WEBSIZE","img/church_logo/full_church_logo_200_85.png");
define("CW_CHURCH_LOGO_BUTTON","img/church_logo/button_size_church_logo_100_40.png");
define("CW_PLATFORM_LOGO_FULL_WEBSIZE","img/cw_platform_logo_100.gif");
define("CW_FILEBASE","filebase/"); //where class cw_Files stores the files
define("CW_FULL_PUBLIC_URL","http://174.1.78.137/~johannes/dev/cw2/");
define("CW_SYSTEM_MAIL_FOOTER","\n--\nSent from ChurchWeb at ".CW_CHURCH_NAME);
define("CW_SYSTEM_MAIL_HEADER","From: ChurchWeb at ".CW_CHURCH_NAME." <johannes@kingroad.ca>");
define("CW_KEEP_ALIVE",180); //Seconds for keep-alive request (in framework.php) Must be lower than SESSION_DEFAULT_TTL
define("CW_ON_THE_FLY_FILE_TTL",DAY); //Time to live for an on-the-fly generated file (zip, or otherwise for download)
define("CW_CHURCH_TIME_OFFSET",0); //Offset in seconds between server time and displayed official church time (normally 0)

//MySQL
define("CW_DATABASE_NAME","cw2");
define("CW_MYSQL_HOST","localhost");
define("CW_MYSQL_USER","jowede");
define("CW_MYSQL_PWD","33189");

//SOME STATIC SERVICE PATHS for use in links (double check with services tree)
define("CW_LOGIN",CW_ROOT_WEB."login.php");
define("CW_LOGOUT",CW_ROOT_WEB."logout.php");
define("CW_HOME",CW_ROOT_WEB."home.php");
define("CW_DOWNLOAD_HANDLER",CW_ROOT_WEB."download.php");

//AJAX PATHS
define("CW_AJAX",CW_ROOT_WEB."ajax/"); //Directory for ajax scripts
define("CW_AJAX_DB",CW_AJAX."ajax_db.php"); //Script for ajax database queries
define("CW_AJAX_USER_PREFERENCES",CW_AJAX."ajax_user_preferences.php"); //Script to read and write user preferences through ajax

//AUTH
define("CW_HOME_SERVICE_ID",3); //id of home service - IMPORTANT! Check service->recreate_table()
define("SESSION_DEFAULT_TTL",1800); //Session time to live
define("CW_SESSION_VALIDATED_SUCCESSFULLY",999); //These are returned by cw_Auth::validate_session()
define("CW_SESSION_RECORD_IS_VALID",1);
define("CW_SESSION_RECORD_HAS_TIMED_OUT",-1);
define("CW_SESSION_RECORD_DOES_NOT_EXIST",-2);
define("CW_SESSION_RECORD_COULD_NOT_BE_UPDATED",-3);
define("CW_SESSION_OWNER_COULD_NOT_BE_IDENTIFIED",-4);
define("CW_SESSION_OWNER_NOT_AUTHORIZED_FOR_REQUESTED_SERVICE",-5);
define("CW_MAX_FAILED_LOGIN_ATTEMPTS",3); //After this many consecutive failed login attempts, a user account will be temporarily blocked
define("CW_TEMPORARY_ACCOUNT_BLOCK_LENGTH",HOUR); //Length of the auto-blockade in seconds

define("CW_AUTH_LEVEL_UNSPECIFIED",0); //Levels of permission
define("CW_AUTH_LEVEL_VIEWER",1);
define("CW_AUTH_LEVEL_EDITOR",2);
define("CW_AUTH_LEVEL_ADMIN",3);

define("CW_U",CW_AUTH_LEVEL_UNSPECIFIED); //Shorter Aliases
define("CW_V",CW_AUTH_LEVEL_VIEWER);
define("CW_E",CW_AUTH_LEVEL_EDITOR);
define("CW_A",CW_AUTH_LEVEL_ADMIN);

//CHURCH DIRECTORY
define ("CW_CD_IMAGE_DIR","imagebase/church_directory/"); //Must make sure at system init time that directory exists and can be written into
define ("CW_CD_DEFAULT_COUNTRY","Canada");
define ("CW_CD_DEFAULT_PROVINCE","British Columbia");
define ("CW_CD_DEFAULT_CITY","Abbotsford");
define ("CW_CD_DEFAULT_COUNTRY_CODE","1");
define ("CW_CD_DEFAULT_AREA_CODE","604");

define ("CW_CD_PERSONAL_EMAIL_CONTACT_OPTION_TYPE","personal email"); //These are provided here b/c they are independently used in cw_Church_directory and login.php
define ("CW_CD_WORK_EMAIL_CONTACT_OPTION_TYPE","work email");
define ("CW_CD_PERSONAL_CELL_CONTACT_OPTION_TYPE","personal cell");
define ("CW_CD_WORK_CELL_CONTACT_OPTION_TYPE","work cell");
define ("CW_CD_FAX_CONTACT_OPTION_TYPE","fax");
define ("CW_CD_WEBSITE_CONTACT_OPTION_TYPE","website");
define ("CW_CD_ICQ_CONTACT_OPTION_TYPE","icq number");

//EVENTS AND SERVICE PLANNING

define ("CW_SERVICE_PLANNING_SYNC_DISTANCE",0); //How many seconds must the latest service update be prior to the latest reload in the session?
define ("CW_SERVICE_PLANNING_SYNC_NOTICE","Could not perform this operation: the service plan has been modified after your latest reload. Try again."); //This shows in an alertbox
define ("CW_SERVICE_PLANNING_SYNC_NOTICE_WITH_F5","Could not perform this operation: the service plan has been modified after your latest reload. Please hit F5 to reload."); //This may show in the modal
define ("CW_DEFAULT_SERVICE_DURATION",5400); //Default length for new service event 
define ("CW_DEFAULT_PRACTICE_DURATION",3600); //Default length for new rehearsal event 
define ("CW_FAVORITE_PRACTICE_TIMES","8am,last thursday 7pm,last wednesday 7pm,last friday 7pm,last tuesday 7pm");
define ("CW_SERVICE_PLANNING_EMAIL_SUBJECT", CW_CHURCH_NAME." - ChurchWeb scheduling");
define ("CW_SERVICE_PLANNING_EMAIL_FOOTER", "\n\nRegards,\n".CW_CHURCH_NAME);
define ("CW_SERVICE_PLANNING_AUTO_CONFIRM_SCRIPT", "acf.php"); //CW relative path to auto confirm script
define ("CW_SERVICE_PLANNING_AUTO_CONFIRM_LINK_VALIDITY", 4*WEEK); //How long can you click an auto-confirm link? Used to clean up the auto_confirm table
define ("CW_SERVICE_PLANNING_AUTO_CONFIRM_ACCESS_CODE_LENGTH", 5); //Default length of access code
define ("CW_SERVICE_PLANNING_DEFAULT_PRACTICE_ROOM_NAME", "main auditorium");
define ("CW_SERVICE_PLANNING_SEGMENT_PRE","pre-service"); 
define ("CW_SERVICE_PLANNING_SEGMENT_MAIN","main service"); 
define ("CW_SERVICE_PLANNING_SEGMENT_POST","post-service");
define ("CW_SERVICE_PLANNING_DEFAULT_ELEMENT_DURATION",240); //seconds 
define ("CW_SERVICE_PLANNING_DEFAULT_GROUP_LABEL","(Group)");
define ("CW_SERVICE_PLANNING_SONG_DURATION_EXTRA",30); //seconds: How much to add to the duration of an arrangement when it's assigned to an element?
define ("CW_DEFAULT_SERVICE_START_TIME","11:00"); //24h format
define ("CW_SERVICE_PLANNING_SERVICE_LIST_RELOAD_INTERVAL",60); //Reload list of upcoming services every minute

define ("CW_POSITION_TYPE_SERVICE_LEADERSHIP","service leadership");
define ("CW_POSITION_TYPE_TECHNICAL_SUPPORT","technical support");
define ("CW_POSITION_TYPE_WORSHIP_TEAM","worship team");
define ("CW_POSITION_TYPE_DRAMA","drama");
define ("CW_POSITION_TYPE_OTHER","other");

define ("CW_SERVICE_PLANNING_DESCRIPTOR_PRODUCER","producer");
define ("CW_SERVICE_PLANNING_DESCRIPTOR_WORSHIP_LEADER","worship leader");
define ("CW_SERVICE_PLANNING_DESCRIPTOR_PREACHER","preacher");
define ("CW_SERVICE_PLANNING_DESCRIPTOR_HOST","service host");
define ("CW_SERVICE_PLANNING_DESCRIPTOR_DRAMA_DIRECTOR","drama director");
define ("CW_SERVICE_PLANNING_DESCRIPTOR_TECHNICAL_DIRECTOR","technical director");

define ("CW_SERVICE_PLANNING_SYNC_INTERVAL",6);

define ("CW_BLANK_PDF_FILENAME","_blank_.pdf");

define ("CW_PPT_FRONT_COVER",'ppt_front_cover.jpg');

//MUSIC DB
define ("CW_MUSICDB_WRITER_CAPACITY_ARRANGER","arranger");
define ("CW_MUSICDB_WRITER_CAPACITY_TRANSLATOR","translator");
define ("CW_MUSICDB_WRITER_CAPACITY_LYRICIST","lyricist");
define ("CW_MUSICDB_WRITER_CAPACITY_COMPOSER","composer");

define ("CW_GUITAR_FRIENDLY_KEYS","C,G,D,A,E,A-mi,E-mi,B-mi,F#-mi,C#-mi");
define ("CW_SONGLIST_ITEMS_PER_PAGE",15); //How many items should be displayed at the same time in when a user searches for a song/piece without using the autocomplete
define ("CW_LANGUAGES","English,German,Spanish,Portugese,Dutch,French,Swahili");
define ("CW_DEFAULT_LANGUAGE",0); //index for default language = 0=English, 1=German etc

define ("CW_NEW_ARRANGEMENT_DEFAULT_DURATION",210);

//CONVERSATIONS

define ("CW_CONVERSATIONS_DEFAULT_RESPONSE_TEXT","type a response");

//ERROR MESSAGES

define ("CW_ERR_INSUFFICIENT_PRIVILEGES","Insufficient privileges!");
define ("CW_ERR_PAST_SERVICE","Error: Cannot modify past service!");
?>