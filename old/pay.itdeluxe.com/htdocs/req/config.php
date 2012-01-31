<?
//Каталог: req
//Файл: config.php
//Подключаемые модули: config.php
//Описание: API общие настройки

$API_CONFIG=1;

 $SETTINGS=array();

//НАЧАЛО ПАРМЕТРОВ

 //Параметры подключения к mysql
 $SETTINGS["SQL_LOGIN"]="pay";
 $SETTINGS["SQL_PASS"]="utu15dyv";
 $SETTINGS["SQL_DATABASE"]="pay";
 $SETTINGS["SQL_HOST"]="localhost";

 define("TEST_MODE","0");//провести тестирование Ж-)

  $SETTINGS["SERVER_NAME"]="pay.itdeluxe.com";//Название сервера
  $SETTINGS["SERVER_EMAIL"]="stell_hawk@ngs.ru";
  $SETTINGS["SERVER_STORE_URL"]=""; //для вещей требующих удаленного взаимодествия (рассылок и т.д.)
  $SETTINGS["SERVER_URL_"]=$PATH_WWW;
  $SETTINGS["SERVER_PATH"]="";
  $SETTINGS["SERVER_URL"]="http://pay.itdeluxe.com/";

  $SETTINGS["IMG_URL"]=$SETTINGS["SERVER_URL"]."/img";//путь дло папки с картинками
  $SETTINGS["SERVER_IMG_URL"]="/usr/local/www/pay.itdeluxe.com/htdocs/img";//путь дло папки с картинками

  //Путь к шаблонам
  $SETTINGS["PATH_INC"]=$SETTINGS["SERVER_URL_"]."/inc";

  //Путь к html
  $SETTINGS["PATH_HTML"]=$SETTINGS["SERVER_URL_"]."";
 
  //Путь к данным
  $SETTINGS["PATH_DATA"]=$SETTINGS["SERVER_URL_"]."/data";

  //рХФШ Л ДБООЩН
  $SETTINGS["PATH_POCKET"]=$SETTINGS["SERVER_URL_"]."/income";

  //E-mails для сообщений admin'ам
  $SETTINGS["EMAIL_ADMIN"]=array("stell_hawk@ngs.ru");
  $SETTINGS["EMAIL_ADMIN_SEND"]=0;// полысать письма администраторы(выше)
  $SETTINGS["EMAIL_SEND"]=0;// полысать письма администраторам из базы

  //Кол-во объектов на странице
  $SETTINGS["COUNT_OBJ_ON_PAGE"]=20;

  //DEBUG_LEVEL
  $SETTINGS["DEBUG"]=2; //2-отключен,1-по настройкам функций и модулей,2-полный
  $SETTINGS["DEBUG_NOTIF"]=0; //0-в браузер,1-на емайл,2-в браузер и на емайл
  if(ini_get("magic_quotes_gpc")=="1")
  $SETTINGS["AddSlashes"]=0; //0-не включать,1-Включать
  else $SETTINGS["AddSlashes"]=1;

  //Строка вывода ошибки
  $SETTINGS["ERROR_STRING"]="<center><font color=\"red\"><b>ЕСТЬ ОШИБКИ</b></font><br> По всем вопросам пишите <a href=\"mailto:".$SETTINGS["SERVER_EMAIL"]."\">".$SETTINGS["SERVER_EMAIL"]."</a></center><br>";

  //Расцветки
  $SETTINGS["COLORS"]=array(
     "TblHead"=>"#A6CEEE",
     "TblTD1"=>"#C9E9FC",
     "TblTD1"=>"#B9D9EC",
     "Frm1"=>"#C9E9FC",
     "Frm2"=>"#B9D9EC",
     "FrmBtn"=>"#A6CEEE"
                           );

//КОНЕЦ ПАРМЕТРОВ
 $PARAMS=array();
 $ERRORS=array();
 $PARAMS["DEBUG"]="";
 $PARAMS["ERR"]="";
 $ERR="";
 $notify="";
  

//обязательные функции
 header( "Cache-Control: max-age=0, must-revalidate" );
 header( "Last-Modified: " . gmdate("D, d M Y H:i:s", time()) . " GMT");
 header( "Expires: " . gmdate("D, d M Y H:i:s", time()) . " GMT");
 header ("Pragma: no-cache");
?>
