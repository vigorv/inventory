<?
debug("%1%Подключаем req/index.class.php");

include_once PATH_API."/template.class.php";
class index extends template
{
    function index()
    {
        global $SETTINGS;
        parent::template();
        include $SETTINGS["PATH_INC"]."/admin/top.html";
        include $SETTINGS["PATH_INC"]."/admin/bottom.html";

    }
}
?>
