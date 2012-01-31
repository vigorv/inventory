<?
debug("%1%Подключаем req/admn.class.php");
include_once(PATH_API.'/template.class.php');
/**
 * Класс защиты админской части
 * @var 
 * Используемые параметры:
 * id - идентификатор пользователя
 * login - логин пользователя
 * pass - пароль пользователя
 * Выходные парметры
 * $_SESSION["ADMN_USER_ID"] - идентификатор пользователя
 * $_SESSION["ADMN_USER_TYPE"] - тип пользователя
 *  $_SESSION["ADMN_ONLY_READ"]="Y" выставляется если по чтению

 *
 */
class admn extends template
{
    //var $ERRORS=array();
    var $login='';
    var $pass='';
    var $level=0;//[0-2]-0 админ 1-модер 2- просмотр


    function admn()
    {
        global $SETTINGS;
	    parent::template();
        $this->ERRORS["ADMN_USER_NOT_FOUND"]="Пользователь c таким логином не найден или пароль не верен";
        $this->ERRORS["ADMN_USER_NOT_ACTIVE"]="Доступ для этого пользователя закрыт, обратитесь к <a href=\"mailto:".$SETTINGS["SERVER_EMAIL"]."\">администратору</a>";
        $this->ERRORS["ADMN_ACCESS"]="Нет доступа";
        $this->ERRORS["ADMN_ONLY_READ"]="У вас только возможность просмотра";
        $this->PARAMS["ERR"]='';

        $vars=array();
	 		$var=array();
	 		$var["name"]="id";$var["string"]="id";
	 		$var["is_edit"]=0;
	 		$var["show"]["type"]="%id%";
	 		$var["is_save"]=0;
 		$vars[$var["name"]]=$var;

 		    $var=array();
	 		$var["name"]="login";$var["string"]="login";
	 	$vars[$var["name"]]=$var;

 		    $var=array();
	 		$var["name"]="pass";$var["string"]="Пароль";
	 		$var["is_show"]=0;
	 	$vars[$var["name"]]=$var;

 		    $var=array();
	 		$var["name"]="email";$var["string"]="e-mail";
	 	$vars[$var["name"]]=$var;

	 	$configs=array();
	 	$configs["table_name"]="admin_users";
	 	
	 	$this->init($vars,$configs);
    
    }

    /**вход пользователя*/
    function login($md5=1){

        $this->login=strip_tags(AddSlashes($this->login));
        if($md5==1)$this->pass=md5(strip_tags(AddSlashes($this->pass)));
        else $this->pass=strip_tags(AddSlashes($this->pass));
        $this->mysql=new mySQL();
        $this->mysql->sql_execute("select id,type,active from admin_users where login='".$this->login."' and pass='".$this->pass."'");
        if($this->mysql->sql_num_rows()==0){
            $this->PARAMS["ERR"]="ADMN_USER_NOT_FOUND";
            return false;
        }
        list($this->PARAMS["ADMN_USER_ID"],$this->PARAMS["ADMN_USER_TYPE"],$active)=$this->mysql->sql_fetch_row();
        if($active!="Y"){
            $this->PARAMS["ERR"]="ADMN_USER_NOT_ACTIVE";
            return false;
        }
        $this->mysql->sql_execute("update admin_users set date_last=now() where id='".$this->PARAMS["ADMN_USER_ID"]."'");
        return true;
    }


    /**если пользователь не админ, то проверяются его права*/
    function login_type(){
        $this->mysql->sql_execute("select id from admin_user_part where user_id='".$_SESSION["ADMN_USER_ID"]."' and part_id='".$_SESSION["ADMN_PART_ID"]."'");
        if($this->mysql->sql_num_rows==0)return false;
        return true;
    }

    function action_admn()
    {
        global $SETTINGS;
        $cook_path=THIS_FULLPATH."/";
        $this->mysql = new mySQl();
        //выход
        if(isset($_REQUEST["act"]))$act=$_REQUEST["act"]; else $act="";
        if(isset($_REQUEST["login"]))$login=$_REQUEST["login"]; else $login="";
        if(isset($_REQUEST["pass"]))$pass=$_REQUEST["pass"]; else $pass="";
        if(isset($_REQUEST["save_login"]))$save_login=$_REQUEST["save_login"]; else $save_login="";
        if(isset($_REQUEST["URL"]))$URL=$_REQUEST["URL"]; else $URL="";

        if($act=="logout")
        {
            //устанавливаем кукисы
            $ctime=time();
            $ctime=mktime(0,0,0,date("m",$ctime),date("d",$ctime),date("Y",$ctime)+10);
            setcookie("admn_login_auto","",$ctime,$cook_path);
            setcookie("admn_login","",$ctime,$cook_path);
            setcookie("admn_password","",$ctime,$cook_path);
            @session_start();
            session_destroy();
            Header("Location: login.php");
            exit();
        }
        //вход
        $islogin=false;
        if($act=="login"){
            $this->login=$login;
            $this->pass=$pass;
            if($this->login()){
                $islogin=true;
                if($save_login=="Y"){
                    //устанавливаем кукисы
                    $ctime=time();
                    $ctime=mktime(0,0,0,date("m",$ctime),date("d",$ctime),date("Y",$ctime)+10);
                    $url=str_replace("http://","",$SETTINGS["SERVER_URL"]);
                    $url=str_replace("www","",$url);
                    setcookie("admn_login_auto","Y",$ctime,$cook_path);
                    setcookie("admn_login",$login,$ctime,$cook_path);
                    setcookie("admn_password",md5($pass),$ctime,$cook_path);
                }
            }
        }
        if(isset($_COOKIE["admn_login_auto"]) && $_COOKIE["admn_login_auto"]=="Y"){
            $this->login=$_COOKIE["admn_login"];
            $this->pass=$_COOKIE["admn_password"];
            if($this->login(0))
            $islogin=true;
        }
        if($islogin){
            @session_start();
            $ADMN_USER_ID=$this->PARAMS["ADMN_USER_ID"];
            $ADMN_USER_TYPE=$this->PARAMS["ADMN_USER_TYPE"];
            $_SESSION["ADMN_USER_ID"]=$ADMN_USER_ID;
            $_SESSION["ADMN_USER_TYPE"]=$ADMN_USER_TYPE;
            if(trim($URL)!="")Header("Location: ".rawurldecode($URL));
            else Header("Location: index.php");
            exit();
        }

        $this->PARAMS["part_name"]="Вход в систему админстрирования";//Имя раздела
        include $SETTINGS["PATH_INC"]."/admin/top.html";
        if($this->PARAMS["ERR"]!="")$this->error_echo();
           include $SETTINGS["PATH_INC"]."/admin/login.html";
           include $SETTINGS["PATH_INC"]."/admin/bottom.html";



    }
    function setlevel($level){$this->level=$level;}
    
    function get_last_part()
    {
        $mysql=new mySQL();
        $mysql->sql_execute('SELECT (max(id)+1) as lastid  from admin_parts');
        $id=$mysql->sql_result();
        $mysql->sql_close();
        return $id;
    }
    
    function register_part($id=0,$name='')
    {
        $mysql=new mySQL();
        $mysql->sql_execute('SELECT * admin_parts where id='.$id);
        if($mysql->sql_num_rows()==0)
        {
            $mysql->sql_execute("INSERT INTO admin_parts(".$id.",'".$name.')');
            debug('Зарегистрирован раздел админки №'.$id."(".$name.")");
        }
        else debug("Такой раздел уже имеется");
        $mysql->sql_close();
        
    }
}

//API вход в систему админстрирования сервера
//ADMN_USER_ID - идентификатор пользователя
//ADMN_USER_TYPE - тип пользователя
//include_once $PATH_API."/admin_login.php";
//unset($ADMN_USER_ID);
//unset($ADMN_USER_TYPE);

session_start();
if(isset($_SESSION["ADMN_USER_ID"]))$_SESSION["ADMN_USER_ID"]=$_SESSION["ADMN_USER_ID"];else $_SESSION["ADMN_USER_ID"]=0;
if(isset($_SESSION["ADMN_USER_TYPE"]))$_SESSION["ADMN_USER_TYPE"]=$_SESSION["ADMN_USER_TYPE"]; else  $_SESSION["ADMN_USER_TYPE"]=0;

if($_SESSION["ADMN_USER_ID"]==0){
    if(isset($_SERVER["REQUEST_URI"]))$URL=rawurlencode($_SERVER["REQUEST_URI"]);else $URL="";
    if(strpos($_SERVER["REQUEST_URI"], 'login.php')===false)
    {
        Header("Location: login.php?URL=$URL");
        exit();
    }
}
else{
    if(isset($_SESSION["ADMN_PART_ID"])&&$_SESSION["ADMN_PART_ID"]!=0){
        if($_SESSION["ADMN_USER_TYPE"]!=0){
            if(!$admn->login_type()){
                Header("Location: index.php?ERR=ADMN_ACCESS");
                exit();
            }
            elseif($_SESSION["ADMN_USER_TYPE"]==2)
            $admn->setlevel(2);
        }
    }
}
?>