<?
define("API_UTIL","1");
debug("%1%Подключаем req/util.php");

$SETTINGS["month"]=array("","январь","февраль","март","апрель","май","июнь","июль","август","сентябрь","октябрь","ноябрь","декабрь");
$SETTINGS["weeek_day_l"]=array("пн","вт","ср","чт","пт","сб","вс");
$SETTINGS["weeek_day_lr"]=array("вс","пн","вт","ср","чт","пт","сб");
$SETTINGS["calendar_color_nothing"]="ffffff";
$SETTINGS["calendar_color_use"]="B8B8B8";
$SETTINGS["calendar_color_nouse"]="e9e9e9";
$SETTINGS["calendar_color_cur"]="FB7E03";

$SETTINGS["BAN_IP"]=array("212.176.17.62_212.176.17.62","195.225.129.55_195.225.129.55");

if(in_array(get_ip(),$SETTINGS["BAN_IP"]))
exit();

//текущая дата время
function today_date($is_date=true,$is_time=true)
{
    $ret="";
    if($is_date)
    $ret.=date("d.m.Yг ",time());
    if($is_time)
    $ret.=date("G:i:s",time());
    return $ret;
}


//вывод массива
function echo_array($ar){
    if(!is_array($ar)){echo "NOT ARRAY";return;}
    echo "<table border=1>";
    while(list($key,$val)=each($ar)){
        echo "<tr valign=top><td>$key</td><td>";
        if(is_array($val))
        debug_array($val);
        else
        echo $val;
        echo "</td></tr>";
    }
    echo "</table>";
}
//получение картинки
function get_img_init(){
    global $SETTINGS;
    $images=array();
    $images["Save"]=array("save.gif","Сохранить");
    $images["Find"]=array("find.gif","Поиск");
    $images["Foto"]=array("foto.gif","Фото");
    $images["History"]=array("history.gif","История");
    $images["Add"]=array("add.gif","Добавить");
    $images["AddPocket"]=array("addpocket.gif","Добавить пакетом");
    $images["Price"]=array("price.gif","Установить цену");
    $images["Edit"]=array("edit.gif","Редактировать");
    $images["Quest"]=array("quest1.jpg","Вопросик",$width=12,$height=11);
    $images["Del"]=array("del.gif","Удалить");
    $images["Show"]=array("show.gif","Показать");
    $images["Show_in"]=array("show_in.gif","Показать содержимое");
    $images["Show_e"]=array("show_e.gif","Показать eng");
    $images["Clear"]=array("clear.gif","Очистить кэш");
    $images["Ful"]=array("ful.gif","Кратко/Детально");
    $images["Y"]=array("chek.gif","Изменить");
    $images["N"]=array("nochek.gif","Изменить");
    $images["DirectUp"]=array("arrow1u.gif","Пересортировать");
    $images["DirectDown"]=array("arrow1d.gif","Пересортировать");
    $images["Mail"]=array("mail.gif","Рассылка");
    $images["Stat"]=array("stat.gif","Статистика");
    $images["Descr"]=array("descr.gif","Описание");
    $images["Members"]=array("members.gif","Пользователи");
    $images["Banner"]=array("banner.gif","Баннеры");
    $images["First"]=array("first.gif","Начало");
    $images["Last"]=array("last.gif","Конец");
    $images["Prev"]=array("prev.gif","Назад");
    $images["Next"]=array("next.gif","Вперед");
    $images["Filter"]=array("filter.gif","Фильтр");
    $images["Plus"]=array("plus.gif","",$width=9,$height=9);
    $images["Minus"]=array("minus.gif","",$width=9,$height=9);
    $images["Node"]=array("node.gif","",$width=9,$height=9);
    $images["Up"]=array("up.gif","Вверх",$width=9,$height=5);
    $images["Down"]=array("down.gif","Вниз",$width=9,$height=5);
    $images["cortr"]=array("cortr.gif","",$width=17,$height=17);
    $images["metro"]=array("descr.gif","Метро");
    $images["Copy"]=array("Copy.gif","Скопировать");

    $SETTINGS["IMAGES"]=$images;
}

function get_img($img,$alt="",$get_path=false){
    global $SETTINGS;
    if(!isset($SETTINGS["IMAGES"]))
    get_img_init();
    $images=$SETTINGS["IMAGES"];
    if(isset($images[$img]))$image=$images[$img];else $image=$images["Quest"];
    if(!is_array($image)){
        $img="none.gif";
    }
    else{
        $img=$image[0];
        $malt=$image[1];
        if(isset($image[2]))$width=(int)$image[2];else $width=0;
        if(isset($image[3]))$height=(int)$image[3];else $height=0;
    }
    if($get_path)
    return $SETTINGS["SERVER_IMG_URL"]."/admin/$img";
    if(isset($malt) && $alt=="")$alt=$malt;
    if(!isset($width) || (int)$width==0)$width=16;
    if(!isset($height) || (int)$height==0)$height=16;
    return "<img src=\"".$SETTINGS["IMG_URL"]."/admin/$img\" border=0 width=$width height=$height alt=\"$alt\">";
}

function error_echo(){
    global $SETTINGS,$ERRORS,$PARAMS,$obj;
    //echo str_replace("%ERR%",$obj->ERRORS[$PARAMS["ERR"]],$SETTINGS["ERROR_STRING"]);
    echo $SETTINGS["ERROR_STRING"];
}

//Отправка емайла по шаблону
function send_pattern_email($to,$subj,$pattern,$params=""){
    global $SETTINGS,$PARAMS;
    if(file_exists($SETTINGS["PATH_INC"]."/$pattern")){
        if(is_array($params)){
            while(list($key,$val)=each($params)){
                $PARAMS["$key"]=$val;
            }
        }
        ob_start();
        include $SETTINGS["PATH_INC"]."/$pattern";
        $str=ob_get_contents();
        ob_end_clean();
        $str=strip_tags($str);
        $from=$SETTINGS["SERVER_NAME"]."<".$SETTINGS["SERVER_EMAIL"].">";
        $from="From: $from\nReply-To: $from\nX-Priority: 3\nContent-Type: text/plain; charset=\"windows-1251\"\nContent-Transfer-Encoding: 8bit";
        @mail($to,$subj,$str,$from);
    }
}

//Отправка емайла по шаблону
function send_pattern_email_mass($to,$from,$subj,$pattern,$type){
    global $SETTINGS,$PARAMS;
    $fd = @fopen ($pattern, "r");
    if($fd){
        while(!feof($fd)) {
            $body.=fgets($fd,1024);
        }
        fclose ($fd);
    }
    else
    $body=$pattern;
    $header = "From: ".$from."\n";
    $header .= "Reply-To: ".$rom."\n";
    if($type=="Y"){
        $header .= "Content-Type: text/plain; charset=\"windows-1251\"\n";
        $header .= "Content-Transfer-Encoding: 8bit";
    }
    else{
        $header.= "Content-Type: text/html; charset=\"windows-1251\"\n";
        $header.= "Content-Transfer-Encoding: 8bit";
    }
    for($i=0;$i<count($to);$i++){
        @mail($to[$i],$subj,$body,$header);
    }
}

function get_admin_emails($id=0){
    global $PARAMS;
    $emails=array();
    $res=sql_execute("select u.email from admin_users as u, admin_user_part as up where up.part_id=".$id." and u.id=up.user_id and u.type=1 and u.active='Y'");
    if(mysql_num_rows($res)>0){
        while($out=mysql_fetch_row($res))
        $emails[]=$out[0];
    }
    else{
        $res=sql_execute("select u.email from admin_users as u where u.type=0 and u.active='Y'");
        while($out=mysql_fetch_row($res))
        $emails[]=$out[0];
    }
    return $emails;
}

function normalize_date($date){
    if($date==""){
        $date=date("Y-m-d",time());
    }
    else{
        list($y,$m,$d)=explode("-",$date);
        if($m==0)$m=1;
        if($d==0)$d=1;
        $date=date("Y-m-d",mktime(0,0,0,$m,$d,$y));
    }
    return $date;
}

//календарь
function calendar($date,$sql){
    global $PARAMS,$SETTINGS;
    list($y,$m,$d)=explode("-",$date);
    $PARAMS["calendar_cur_month"]=$SETTINGS["month"][(int)$m];
    $PARAMS["calendar_cur_year"]=$y;
    $PARAMS["calendar_prev_month"]=date("Y-m-d",mktime(0,0,0,$m-1,1,$y));
    $PARAMS["calendar_last_date"]=date("Y-m-d");
    //if(mktime(0,0,0,$m+1,1,$y)<=time())
    $PARAMS["calendar_next_month"]=date("Y-m-d",mktime(0,0,0,$m+1,1,$y));
    $ok_par=array();
    if($sql!=""){
        $res=sql_execute($sql);
        while($out=mysql_fetch_row($res)){
            $ok_par[]=$out[1];
        }
    }
    $nowtime=mktime(0,0,0,$m,$d,$y);
    $outar=array();
    $curar=array();
    for($i=1;$i<=31;$i++){
        $t=mktime(0,0,0,$m,$i,$y);
        $w=date("w",$t);
        if($w==0)$w=7;
        //начало
        if($i==1){
            for($j=1;$j<$w;$j++)
            $curar[]=array("nothing"=>"Y");
        }
        //конец
        if(date("m",$t)!=$m){
            for($j=$w;$j<=6;$j++)
            $curar[]=array("nothing"=>"Y");
            break;
        }
        if($w==1 && $i!=0){
            $outar[]=$curar;
            $curar=array();
        }
        $set_ar=array();
        $curtime=mktime(0,0,0,$m,$i,$y);
        $date=date("Y-m-d",$curtime);
        if($sql!="" && in_array($date,$ok_par)){
            $set_ar["use"]="Y";
        }
        $day=$i;
        if($curtime==$nowtime)
        $set_ar["cur"]="Y";
        $set_ar["day"]=$day;
        $set_ar["date"]=$date;
        $curar[]=$set_ar;
    }
    $outar[]=$curar;
    $PARAMS["calendar"]=$outar;
}

function generate_password(){
    $n=7;
    $str="0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
    $str_len=strlen($str);
    mt_srand ((double) microtime() * 1000000);
    for($i=0;$i<$n;$i++){
        $res.=$str[mt_rand(0,$str_len-1)];
    }
    return $res;
}

function get_ip(){
    $ip=$_SERVER["REMOTE_ADDR"];
    if(isset($_SERVER["HTTP_X_FORWARDED_FOR"])&&$_SERVER["HTTP_X_FORWARDED_FOR"]!="")
    $ip.="_".$_SERVER["HTTP_X_FORWARDED_FOR"];
    return $ip;
}

function get_ip_debug(){
    return in_array(get_ip(),array("80.89.128.214_192.168.0.44"));
}

function file_path($key){
    return "".(int)substr($key,-1)."/".(int)substr($key,-3,2);
}


function file_get($ext,$key){
    global $SETTINGS;
    $ret="";
    $dir=file_path($key);
    $imgDir =@opendir($SETTINGS["PATH_HTML"]."/img/saved/".$dir);
    if($imgDir){
        while($fn = readdir($imgDir)){
            if(is_int(strpos($fn,$ext."_".$key))){
                $ret=$dir."/".$fn;
                break;
            }
        }
        closedir($imgDir);
    }
    if($ret==""){
        $ret=$dir."/".$ext."_".$key;
    }
    return $ret;
}

function file_get_ar($ext,$key){
    global $SETTINGS;
    $ret=array();
    $dir=file_path($key);
    $imgDir =@opendir($SETTINGS["PATH_HTML"]."/img/saved/".$dir);
    if($imgDir){
        while($fn = readdir($imgDir)){
            if(is_int(strpos($fn,$ext."_".$key))){
                $ret[]=$dir."/".$fn;
            }
        }
        closedir($imgDir);
    }
    return $ret;
}

function params_get($params_name){
    //@description берет входные параметры и помещает их в $PARAMS
    //@params $params_name_GET массив имен для GET
    //@params $params_name_POST массив имен для POST
    //@params $ispost=false брать ли POST параметры только из POST
    //@varchange $PARAMS заполняет парметры
    global $_REQUEST,$PARAMS;
    for($i=0;$i<count($params_name);$i++){
        $name=$params_name[$i];
        if(isset($_REQUEST[$name]))
        $PARAMS[$name]=$_REQUEST[$name];
    }
}

function array_spush($in,$def=""){
    //@description Сохранение значений из PARAMS
    global $PARAMS;
    foreach($in as $s){
        $PARAMS["array_spop"][$s]=$PARAMS[$s];
        $PARAMS[$s]=$def[$s];
    }
}

function array_spop($in){
    //@description Восстановление значений в PARAMS
    global $PARAMS;
    foreach($in as $s){
        $PARAMS[$s]=$PARAMS["array_spop"][$s];
    }
}

function header_no_cache(){
    //вывод заголовка защищающего от кэширования
    $now = gmdate("D, d M Y H:i:s")." GMT";
    header("Expires: 0");
    header("Last-Modified: ".$now);
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Cache-Control: pre-check=0, post-check=0, max-age=0");
    header("Pragma: no-cache");
    header("Content-Type: text/html; charset=windows-1251");
}

function echourl($str,$full=false,$echo=true){
    global $SETTINGS;
    //Вывод строки с подстановкой строки по умолчанию
    $str=htmlentities($str);
    if($full){
        if(!is_int(strpos($str, "://"))){
            $purl = "http".(($_SERVER["SSL_CIPHER"]!="")?"s":"")."://".$_SERVER["HTTP_HOST"];
            if($str[0]!="/")
            $str="/".$str;
            $str=$purl.$str;
        }
    }
    if($echo)
    echo $str;
    else
    return $str;
}


function fletter($str,$toupper=true){
    $fc=$str[0];
    if($toupper)
    $fc=strtoupper($fc);
    else
    $fc=strtolower($fc);
    return $fc.substr($str,1);
}

// Возвращает
function out_url($url,$_REQUEST,$serverName="",$ServAddFlag=0,$add=array()){

    $Op=array("f","p","t","s","d","part");
    $Op=array_merge($Op,$add);
    $out_url=$url;
    $qf=0;
    foreach($Op as $val){
        if(isset($_REQUEST[$val])){if($qf==0){$razd="?";$qf=1;}else{$razd="&";}
        $out_url.=$razd.$val."=".$_REQUEST[$val];}
        if($ServAddFlag==1)$PARAMS["out_url"]=$serverName.$out_url;
    }

    return $out_url;
}

//перевод строки в transliterate
function transliterate( $text ){
    $cyrlet = 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ 0123456789_-.'.
    'абвгдеёжзийклмнопрстуфхцчшщъыьэюя ';
    $englet = 'ABVGD   ZIJKLMNOPRSTUFHC   `Y`E  _0123456789_-.'.
    'abvgd   zijklmnoprstufhc   `y`e  _';
    $doplet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-.';

    $result = '';
    for ( $i=0; $i<strlen($text); $i++ ) {
        $c1 = $text[ $i ];
        if(is_int(strpos( $doplet, $c1 ))){ $result .= $c1; continue; }
        $p1 = strpos( $cyrlet, $c1 );
        if ( $p1 === FALSE ) {continue; }
        $ct = $englet[ $p1 ];
        if ( $ct != ' ' ) { $result .= $ct; continue; }
        switch ( $c1 )
        {
            case 'Е':
                $ct = 'Je';
                break;
            case 'е':
                $ct = 'e';
                break;
            case 'Ё':
                $ct = 'Jo';
                break;
            case 'ё':
                $ct = 'jo';
                break;
            case 'Ж':
                $ct = 'Zh';
                break;
            case 'ж':
                $ct = 'zh';
                break;
            case 'Ч':
                $ct = 'Ch';
                break;
            case 'ч':
                $ct = 'ch';
                break;
            case 'Ш':
                $ct = 'Sh';
                break;
            case 'ш':
                $ct = 'sh';
                break;
            case 'Щ':
                $ct = 'Sch';
                break;
            case 'щ':
                $ct = 'sch';
                break;
            case 'Ю':
                $ct = 'Ju';
                break;
            case 'ю':
                $ct = 'ju';
                break;
            case 'Я':
                $ct = 'Ja';
                break;
            case 'я':
                $ct = 'ja';
                break;
            default:
                $ct = '?';
        }
        $result .= $ct;
    }
    return $result;
}
//адаптация информации
function data_adapt(&$str,$strip_tags="")
{
    if($strip_tags!="null")
    $str=strip_tags($str,$strip_tags);
    if(!ini_get("magic_quotes_gpc"))
    $str=addslashes($str);
    return trim($str);
}

//изменение размеров картинки
function image_resize($path,$widthto,$to="")
{
    if(file_exists($path) && !is_dir($path))
    {
        list($width,$height,$type,$attr)=getimagesize($path);
        $heightto=$height/($width/$widthto);
        switch($type)
        {
            case 1:
                {
                    if(function_exists('imagecreatefromgif'))$img1=@imagecreatefromgif($path);
                    if($img1)
                    {
                        $img2=imagecreatetruecolor($widthto,$heightto);
                        imagecopyresized($img2,$img1,0,0,0,0,$widthto,$heightto,$width,$height);
                        if(empty($to)) imagegif($img2,$path);
                        else imagegif($img2,$to);
                    }
                    break;
                }
            case 2:
                {
                    if(function_exists('imagecreatefromjpeg'))$img1=@imagecreatefromjpeg($path);
                    if($img1)
                    {

                        $img2=imagecreatetruecolor($widthto,$heightto);
                        imagecopyresized($img2,$img1,0,0,0,0,$widthto,$heightto,$width,$height);
                        if(empty($to)) imagejpeg($img2,$path);
                        else imagejpeg($img2,$to);
                    }
                    break;
                }
            case 3:
                {
                    $img1=imagecreatefrompng($path);
                    $img2=imagecreatetruecolor($widthto,$heightto);
                    imagecopyresized($img2,$img1,0,0,0,0,$widthto,$heightto,$width,$height);
                    if(empty($to)) imagepng($img2,$path);
                    else imagepng($img2,$to);
                    break;
                }
        }
    }
}

setlocale(LC_CTYPE,"ru_RU.CP1251");

?>
