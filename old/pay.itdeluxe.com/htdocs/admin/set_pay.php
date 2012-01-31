<?
include "config.inc.php";
//include_once(PATH_API.'/index.class.php');
include_once(PATH_API.'/ip.class.php');

//$obj= new index();
            $PARAMS["mssql"]=new SQL('192.168.251.21','media','it4215M67','mssql');
            //$this->PARAMS["mssql"]=new SQL('localhost','hawk','123','mssql');
            //Запрос ЛС (лицевой счет) соответствующего IP пользователя
            $PARAMS["mssql"]->sql_select_db('Accounts');
            $uip=ip::get_ip();
            //$uip='10.2.13.10';
            $PARAMS["mssql"]->sql_execute("SELECT * FROM M_Users WHERE UserIP='{$uip}'");
            $num = $PARAMS["mssql"]->sql_num_rows();
            if ($num>0)     //если клиент биллинга
            {
                $row=$PARAMS["mssql"]->sql_fetch_assoc();
                $LS=$row['UserLogonName'];//Получаем ЛС
                $sum=$row['UserBalans'];
                //echo $sum."<br>";
                //if($sum>0){
                    $LS=$PARAMS["mssql"]->sql_result(0,'UserLogonName');     //узнаем лицевой счет
                    $summ=0;
                    //$summ=$this->payment['summ'];
                    $summ=1.0;
                    //$sum=$this->PARAMS["mssql"]->sql_result(0,'UserBalans');//
                    
                    echo "INSERT INTO M_Payments (UserAgr, MService, MSum) VALUES ('{$LS}', 32, '".$summ."');";
                    //$num = $PARAMS["mssql"]->sql_execute("INSERT INTO M_Payments (UserAgr, MService, MSum) VALUES ('{$LS}', 32, ".$summ.");");
                    //$num=TRUE;//false;
                    if($num===FALSE)
                    {
                        echo "ОШИБКА БД! Деньги не снимались с Вашего счета, попробуйте скачать чуть позже."; //снимаем деньги
                        return false;
                    }
                    $PARAMS["mssql"]->sql_close();
                //}else echo "Недостачно денег на счету";
            }
            else echo ip::get_ip();
debug_echo();
?>