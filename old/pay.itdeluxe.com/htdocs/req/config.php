<?
//�������: req
//����: config.php
//������������ ������: config.php
//��������: API ����� ���������

$API_CONFIG=1;

 $SETTINGS=array();

//������ ���������

 //��������� ����������� � mysql
 $SETTINGS["SQL_LOGIN"]="pay";
 $SETTINGS["SQL_PASS"]="utu15dyv";
 $SETTINGS["SQL_DATABASE"]="pay";
 $SETTINGS["SQL_HOST"]="localhost";

 define("TEST_MODE","0");//�������� ������������ �-)

  $SETTINGS["SERVER_NAME"]="pay.itdeluxe.com";//�������� �������
  $SETTINGS["SERVER_EMAIL"]="stell_hawk@ngs.ru";
  $SETTINGS["SERVER_STORE_URL"]=""; //��� ����� ��������� ���������� ������������� (�������� � �.�.)
  $SETTINGS["SERVER_URL_"]=$PATH_WWW;
  $SETTINGS["SERVER_PATH"]="";
  $SETTINGS["SERVER_URL"]="http://pay.itdeluxe.com/";

  $SETTINGS["IMG_URL"]=$SETTINGS["SERVER_URL"]."/img";//���� ��� ����� � ����������
  $SETTINGS["SERVER_IMG_URL"]="/usr/local/www/pay.itdeluxe.com/htdocs/img";//���� ��� ����� � ����������

  //���� � ��������
  $SETTINGS["PATH_INC"]=$SETTINGS["SERVER_URL_"]."/inc";

  //���� � html
  $SETTINGS["PATH_HTML"]=$SETTINGS["SERVER_URL_"]."";
 
  //���� � ������
  $SETTINGS["PATH_DATA"]=$SETTINGS["SERVER_URL_"]."/data";

  //���� � ������
  $SETTINGS["PATH_POCKET"]=$SETTINGS["SERVER_URL_"]."/income";

  //E-mails ��� ��������� admin'��
  $SETTINGS["EMAIL_ADMIN"]=array("stell_hawk@ngs.ru");
  $SETTINGS["EMAIL_ADMIN_SEND"]=0;// �������� ������ ��������������(����)
  $SETTINGS["EMAIL_SEND"]=0;// �������� ������ ��������������� �� ����

  //���-�� �������� �� ��������
  $SETTINGS["COUNT_OBJ_ON_PAGE"]=20;

  //DEBUG_LEVEL
  $SETTINGS["DEBUG"]=2; //2-��������,1-�� ���������� ������� � �������,2-������
  $SETTINGS["DEBUG_NOTIF"]=0; //0-� �������,1-�� �����,2-� ������� � �� �����
  if(ini_get("magic_quotes_gpc")=="1")
  $SETTINGS["AddSlashes"]=0; //0-�� ��������,1-��������
  else $SETTINGS["AddSlashes"]=1;

  //������ ������ ������
  $SETTINGS["ERROR_STRING"]="<center><font color=\"red\"><b>���� ������</b></font><br> �� ���� �������� ������ <a href=\"mailto:".$SETTINGS["SERVER_EMAIL"]."\">".$SETTINGS["SERVER_EMAIL"]."</a></center><br>";

  //���������
  $SETTINGS["COLORS"]=array(
     "TblHead"=>"#A6CEEE",
     "TblTD1"=>"#C9E9FC",
     "TblTD1"=>"#B9D9EC",
     "Frm1"=>"#C9E9FC",
     "Frm2"=>"#B9D9EC",
     "FrmBtn"=>"#A6CEEE"
                           );

//����� ���������
 $PARAMS=array();
 $ERRORS=array();
 $PARAMS["DEBUG"]="";
 $PARAMS["ERR"]="";
 $ERR="";
 $notify="";
  

//������������ �������
 header( "Cache-Control: max-age=0, must-revalidate" );
 header( "Last-Modified: " . gmdate("D, d M Y H:i:s", time()) . " GMT");
 header( "Expires: " . gmdate("D, d M Y H:i:s", time()) . " GMT");
 header ("Pragma: no-cache");
?>
