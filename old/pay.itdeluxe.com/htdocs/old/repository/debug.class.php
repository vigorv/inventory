<?
$debug=null;

/**
 * Класс дебага , сюда сгружаются вся инфа о дебагу
 * @version 1.01b
 * @last mod.date 14.11.2006 17:00:55
 * необходимые функции today_date(),notif_admin
 * функции вынесенные из класса сделанны так чтобы не создавать 
 * различные обьекты класа
 */
class debug
{
    /**
     * Строка дебага
     * @var string
     */
    var $debug='';
    /**
     * Время начала дебага
     * @var datetime
     */
    var $debugStartTime=0;
    /**
     * Время окончания дебага
     * @var datetime
     */
    var $debugStopTime=0;
    /**
     * Уровень дебага:
     * 0-отключен,1-по настройкам функций и модулей,2-полный
     * @var 0-2
     */
    var $debugLevel=2;

    /**
     * Выставляет начальные параметры
     * Запоминает время начала выставляет 
     * @return debug
     */
    function debug($debugLevel=2)
    {
        $this->debugStartTime=$this->setTime();
        $this->debugLevel=$debugLevel;
    }


    /**
     * вывод времени для начала дебага
     *
     * @param текущее время $DT
     * @return $time
     */
    function setTime($DT=null){
        if(!$DT)$DT=microtime();
        $i=strpos($DT," ");
        $MSec=substr($DT,0,$i);
        $Sec=substr($DT,$i+1);
        return (doubleval($Sec)+doubleval($MSec));
    }

    /**
     * Время дебага
     * @return diftime
     */
    function getTime(){
        return sprintf("%0.4f",$this->debugStopTime-$this->debugStartTime)."sec";
    }

    /**
     * Возвращает строку дебага
     */
    function getDebug()
    {
        $this->debugStopTime=$this->setTime();
        $body="\nDEBUG:\n".$this->debug;
        $body.="\nВремя выполнения - ".$this->getTime();
        $body.="\n".today_date();
        $body=nl2br($body);
        return $body;
    }
    /**
     * Добавление записи в дебаг
     * @param строка ошибки $str
     * @param уровень(0,1) $level
     */
    function Addstring($str,$level=1)
    {
        if($this->debugLevel==0)return;
        if(($this->debugLevel && $level) || $this->debugLevel==2){
            $str=str_replace("%1%","<font color='#008000'>",$str);//функции
            $str=str_replace("%2%","<font color='#000040'>",$str);//Подключения отключения
            $str=str_replace("%3%","<font color='#0000A0'>",$str);//Запросы
            $str=str_replace("%e%","<font color='#FF00FF'>",$str);// Ошибки

            $str.="</font>";
            $this->debug.=$str."\n";
        }
    }
    function isDebug(){if($this->debug!='')return $this->debug;else return false;}
}
//Дабавление информации в дебаг
function debug($str,$level=false){
    global $SETTINGS,$PARAMS,$debug;
    if(!$debug)$debug=new debug($SETTINGS["DEBUG"]);
    $debug->Addstring($str,$level);
}

//Вывод дебага
function debug_echo(){
    global $SETTINGS,$PARAMS,$ERRORS,$REQUEST_URI,$debug;
    if($debug->isDebug()){
        if($SETTINGS["DEBUG_NOTIF"]==0 || $SETTINGS["DEBUG_NOTIF"]==2){
            $body=$debug->getDebug();
            if($PARAMS["ERR"]){
                $body.="\nLAST_ERROR: ".$PARAMS["ERR"]." - ".$ERRORS[$PARAMS["ERR"]];
            }
            echo $body;
        }
        if($SETTINGS["DEBUG_NOTIF"]==1 || $SETTINGS["DEBUG_NOTIF"]==2){
            notif_admin($SETTINGS["SERVER_NAME"]." DEBUG","");
        }
    }
}
//Отправка сообщений администраторам
function notif_admin($subj, $body){
    global $SETTINGS,$PARAMS,$ERRORS,$REQUEST_URI,$debug;
    $from=$SETTINGS["SERVER_NAME"]."<".$SETTINGS["SERVER_EMAIL"].">";
    $from="From: $from\nReply-To: $from\nX-Priority: 1\nContent-Type: text/plain; charset=\"windows-1251\"\nContent-Transfer-Encoding: 8bit";
    if($debug->isDebug()){
        $body.=$debug->getDebug();
    }
    if($PARAMS["ERR"]){
        $body.="\n\nLAST_ERROR: ".$PARAMS["ERR"]." - ".$ERRORS[$PARAMS["ERR"]];
        $body.="\n\n".today_date();
    }
    for($i=0;$i<count($SETTINGS["EMAIL_ADMIN"]);$i++){
        @mail($SETTINGS["EMAIL_ADMIN"][$i],$subj,$body,$from);
    }
}
?>
