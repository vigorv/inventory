<?
include_once "config.inc.php";
include_once PATH_API."/wm.class.php";

$wm= new wm();
$wm->action();
debug_echo();
?>