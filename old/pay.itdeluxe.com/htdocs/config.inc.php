<?

//$PATH_ROOT="/var/www/itdeluxe.com/pay";
//$PATH_ROOT="/var/www/itdeluxe.com/pay/www";
$PATH_ROOT=$HTTP_SERVER_VARS['DOCUMENT_ROOT']."";
$PATH_INWWW="";
$PATH_WWW=$PATH_ROOT.$PATH_INWWW;
define("PATH_API",$PATH_WWW."/req");
define("PATH_REPOSIT","/usr/local/www/reposit");

include_once PATH_API."/config.php";
//include_once PATH_API."/debug.class.php";
//include_once PATH_API."/util.php";
//include_once PATH_API."/sql.class.php";
require_once PATH_REPOSIT."/debug.class.php";
include_once PATH_REPOSIT."/util.php";
//include_once PATH_REPOSIT."/sql.class.php";

//include_once $PATH_API."/pattern.php";

if(TEST_MODE==1){
        $mysql=new mySQL();
        $mysql->sql_execute("select 2+2");
        $mysql->echo_result();
        $mysql->sql_close();
        debug_echo();
}
?>
