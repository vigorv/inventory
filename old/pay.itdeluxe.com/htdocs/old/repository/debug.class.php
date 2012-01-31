<?
$debug=null;

/**
 * ����� ������ , ���� ���������� ��� ���� � ������
 * @version 1.01b
 * @last mod.date 14.11.2006 17:00:55
 * ����������� ������� today_date(),notif_admin
 * ������� ���������� �� ������ �������� ��� ����� �� ��������� 
 * ��������� ������� �����
 */
class debug
{
    /**
     * ������ ������
     * @var string
     */
    var $debug='';
    /**
     * ����� ������ ������
     * @var datetime
     */
    var $debugStartTime=0;
    /**
     * ����� ��������� ������
     * @var datetime
     */
    var $debugStopTime=0;
    /**
     * ������� ������:
     * 0-��������,1-�� ���������� ������� � �������,2-������
     * @var 0-2
     */
    var $debugLevel=2;

    /**
     * ���������� ��������� ���������
     * ���������� ����� ������ ���������� 
     * @return debug
     */
    function debug($debugLevel=2)
    {
        $this->debugStartTime=$this->setTime();
        $this->debugLevel=$debugLevel;
    }


    /**
     * ����� ������� ��� ������ ������
     *
     * @param ������� ����� $DT
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
     * ����� ������
     * @return diftime
     */
    function getTime(){
        return sprintf("%0.4f",$this->debugStopTime-$this->debugStartTime)."sec";
    }

    /**
     * ���������� ������ ������
     */
    function getDebug()
    {
        $this->debugStopTime=$this->setTime();
        $body="\nDEBUG:\n".$this->debug;
        $body.="\n����� ���������� - ".$this->getTime();
        $body.="\n".today_date();
        $body=nl2br($body);
        return $body;
    }
    /**
     * ���������� ������ � �����
     * @param ������ ������ $str
     * @param �������(0,1) $level
     */
    function Addstring($str,$level=1)
    {
        if($this->debugLevel==0)return;
        if(($this->debugLevel && $level) || $this->debugLevel==2){
            $str=str_replace("%1%","<font color='#008000'>",$str);//�������
            $str=str_replace("%2%","<font color='#000040'>",$str);//����������� ����������
            $str=str_replace("%3%","<font color='#0000A0'>",$str);//�������
            $str=str_replace("%e%","<font color='#FF00FF'>",$str);// ������

            $str.="</font>";
            $this->debug.=$str."\n";
        }
    }
    function isDebug(){if($this->debug!='')return $this->debug;else return false;}
}
//���������� ���������� � �����
function debug($str,$level=false){
    global $SETTINGS,$PARAMS,$debug;
    if(!$debug)$debug=new debug($SETTINGS["DEBUG"]);
    $debug->Addstring($str,$level);
}

//����� ������
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
//�������� ��������� ���������������
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
