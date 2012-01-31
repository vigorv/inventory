<?
define("THIS_PATH","/admin");//относительный путь , относительно корня данного раздела(ВМ)
define("THIS_FULLPATH","/admin");//относительный путь на сервере
define("THIS_RETURNPATH","../");//возвратный путь до корня
define("THIS_RETURNFULLPATH","../");//возвратный путь до корня

include_once(THIS_RETURNPATH."config.inc.php"); 
include_once(PATH_REPOSIT.'/admn.class.php');
$admn=new admn();
?>
