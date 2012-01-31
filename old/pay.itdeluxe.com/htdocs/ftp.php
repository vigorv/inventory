<?
/**Скрипт проверяющий включенные ФТП. 
 */
include "config.inc.php";
$SETTINGS["DEBUG"]=2; //0-отключен,1-по настройкам функций и модулей,2-полный
$SETTINGS["DEBUG_NOTIF"]=0; //0-в браузер,1-на емайл,2-в браузер и на емайл

//function set
function get_ips_from_mask($ftp_list)
{
    static $i=0;
    static $cur=array(10,1,1,2);
    static $thislist=array(array(10,10),array(1,2),array(1,100),array(2,100));
    static $pinthislist=0;
    if(isset($ftp_list[$i]))
    {
        $thisFtpList=$ftp_list[$i];
        list($ip1,$ip2,$ip3,$ip4)=explode('.',$thisFtpList);
        $thisip[0]=$ip1;$thisip[1]=$ip2;$thisip[2]=$ip3;$thisip[3]=$ip4;
        for($j=0;$j<4;$j++)
        {
            $b=0;$e=0;
            if(strpos($thisip[$j],']'))
            {
                $thisip[$j]=str_replace(array('[',']'),array('',''),$thisip[$j]);
                list($b,$e)=explode('-',$thisip[$j]);
            }
            elseif($thisip[$j]=='*')
            {
                $b=1;$e=254;
            }
            elseif(is_int((int)$thisip[$j])){$b=(int)$thisip[$j];$e=(int)$thisip[$j];}

            $thislist[$j]=array($b,$e);
        }
        //if()

        $i++;
        return $thislist;
    }
    else return false;
};
function get_ip_list($ftp_list)
{
    $iplist=array();
    while($iparr=get_ips_from_mask($ftp_list))
    {
        //echo "<pre>";print_r($ip);echo "<br>";
        $ip1b=$iparr[0][0];$ip1e=$iparr[0][1];
        $ip2b=$iparr[1][0];$ip2e=$iparr[1][1];
        $ip3b=$iparr[2][0];$ip3e=$iparr[2][1];
        $ip4b=$iparr[3][0];$ip4e=$iparr[3][1];
        //echo $ip1." ".$iparr[0][1]."<br>";
        $ip1=$ip1b;
        while($ip1<=$ip1e)
        {
            //echo $ip1;
            $ip2=$ip2b;
            while($ip2<=$ip2e)
            {
                //echo ".".$ip2;
                $ip3=$ip3b;
                while($ip3<=$ip3e)
                {
                    //echo ".".$ip3;
                    $ip4=$ip4b;
                    while($ip4<=$ip4e)
                    {
                        $iplist[]=$ip1.".".$ip2.".".$ip3.".".$ip4;
                        $ip4++;
                    }

                    $ip3++;
                }

                $ip2++;
            }
            //echo "<br>";
            $ip1++;
        }
    }
    return $iplist;
}

$ftp_list=array('10.[1-5].[2-18].[2-40]','10.[33-34].[2-18].[2-40]');
//$ftp_list=array('192.168.251.*',);
//$ftp_list=array('217.71.142.239',);
//$ftp_list=array('192.168.251.17',);

$ftp_server=$ftp_list[0];
$listip=get_ip_list($ftp_list);
$i=0;
if(1)
{foreach ($listip as $ip)
{
    // установка соединения
    //100 подсоединений за 19 секунд
    // вход с именем пользователя и паролем

		//echo $ip.">>".gethostbyaddr($ip);
		
    $conn_id = @ftp_connect($ip,21,1);
    $ftp_user_name='anonymous';
    $ftp_user_pass='support@itdeluxe.com';
    /*$ftp_user_name='web';
    $ftp_user_pass='axh31mn9';
    $ftp_user_name='hawk';
    $ftp_user_pass='hawker';*/


// проверка соединения
    if ((!$conn_id)) {
    //
        echo "<font color=red>".$ip."</font><br>";
        //echo "Не удалось установить соединение с FTP сервером!";
        //echo "Попытка подключения к серверу $ftp_server под именем $ftp_user_name!";
        //exit;
    } else {
        $login_result = @ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
        echo "<font color=green>".$ip."</font>";
	 			if(!$login_result)echo "(*)";
	 			echo "<br>";
        $i++;
        //echo "Установлено соединение с FTP сервером $ftp_server под именем $ftp_user_name";
        ftp_close($conn_id);
    }
    flush();
    // закрытие соединения
}
echo "<br>всего открыто ".$i."ФТП -серверов";
}
debug_echo();
?>
