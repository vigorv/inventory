<?
debug("%1%���������� req/wm.class.php");

include_once PATH_API."/template.class.php";
include_once PATH_REPOSIT."/ip.class.php";
include_once PATH_REPOSIT."/cost.class.php";
class wm extends template
{
    var $payment=array();
    var $err=array();

    function wm()
    {
        parent::template();
        $vars=array();
        /* ������ �������� ������
        $var=array();
        $var["name"]="id";//�������� ����������
        $var["string"]="id";//������������ ���
        $var["is_show"]=TRUE;// ���������� � ������
        $var["is_edit"]=FALSE;// ��������� ��������������
        $var["is_get"]=TRUE;// ��������� � ��������� ������
        $var["is_save"]=TRUE;// ��������� � ��������� ������
        $var["show"][type]="";//��� �����������
        ����� - ������ ������
        %act% - �������� �������/���
        ����� ������, ����������� �� �������� ���� ���������
        $var["edit"][type]="input";//
        "textarea"
        ,"input"
        ,"hidden"
        ,"select"
        ,"chekbox"

        $var["edit"]["value_list"]=>array("Y","N")- ������ ��������

        $var["save"][obligation]=0 //���������� ����
        $var["save"][if_null]=0 //��������� ��� ������ ����
        $var["save"][type]= //	��� ������ str - ������
        str1- ������ ��� ���� html
        int - ������
        other - ��� ��������������

        $var["save"][errors]=array(0) //�� ��������� �� ������
        array(1,"dubl||null","LOGIN_DUBL||NOT_LOGIN")




        */
        $var=array();
        $var["name"]="id";$var["string"]="id";
        $var["is_edit"]=0;
        $var["show"]["type"]="%id%";
        $var["is_save"]=0;
        $vars[$var["name"]]=$var;
        
        $var=array();
        $var["name"]="summ";$var["string"]="�����";
        $var["save"]["obligation"]=1;
        $var["save"]["errors"]=array(1,"null","NOT_PRESENT");
        $var["save"]["type"]="float";
        $vars[$var["name"]]=$var;

        $var=array();
        $var["name"]="info";$var["string"]="����";
        $var["edit"]["type"]="text";
        $vars[$var["name"]]=$var;

        $var=array();
        $var["name"]="success";$var["string"]="������?";
        $var["edit"]["type"]="radio";
        $var["edit"]["value_list"]=array("Y","N","F","R");
        $vars[$var["name"]]=$var;
        $var=array();
        $var["name"]="type";$var["string"]="���";
        $vars[$var["name"]]=$var;

        $configs=array();
        $configs["table_name"]="payment_in";

        $var=array();
        $var["name"]="service";$var["string"]="������";
        $vars[$var["name"]]=$var;

        $var=array();
        $var["name"]="time";$var["string"]="�����";
        $vars[$var["name"]]=$var;

        
        $this->init($vars,$configs);

        $this->PARAMS["test_mode"]=1;//�������� �������� ����� ��� ��� ��������
        //����������� �������
        $this->PARAMS["default_pay_system"]="WMR";
        $this->PARAMS["pay_systems"]["WMR"]=array(
        "name"=>"WMR",         				/* ������� � �������*/
        "function_prefix"=>"wm", 		/*�������� ������������ �������*/
        "taker"=>"R186317053081"   /* ID ��������� ����� LMI_PAYEE_PURSE */
        ,"to_id"=>"LMI_PAYMENT_NO"
        ,"to_sum"=>"LMI_PAYMENT_AMOUNT"
        ,"to_info"=>"LMI_PAYER_WM||LMI_PAYER_PURSE"
        ,"to_hash"=>"LMI_HASH"
        ,"currency"=>"RUR"
        ,"locked"=>"0"
        ,"secret_key"=>"7Y213U" // ��������� ����
        // ��� ���������� ��� LMI ��������� � �������� �����
        //https://merchant.webmoney.ru/lmi/payment.asp
        );
        $this->PARAMS["pay_systems"]["WMZ"]=array(
        "name"=>"WMZ",         				/* ������� � �������*/
        "function_prefix"=>"wm", 		/*�������� ������������ �������*/
        "taker"=>"Z250257668568"   /* ID ��������� ����� LMI_PAYEE_PURSE */
        ,"to_id"=>"LMI_PAYMENT_NO"
        ,"to_sum"=>"LMI_PAYMENT_AMOUNT"
        ,"to_info"=>"LMI_PAYER_WM||LMI_PAYER_PURSE"
        ,"to_hash"=>"LMI_HASH"
        ,"currency"=>"$"
        ,"locked"=>"0"
        ,"secret_key"=>"7Y213U" // ��������� ����
        // ��� ���������� ��� LMI ��������� � �������� �����
        //https://merchant.webmoney.ru/lmi/payment.asp
        );
        
        $this->PARAMS["pay_systems"]["RuPay"]=array(
        "name"=>"RuPay",         				/* ������� � �������*/
        "function_prefix"=>"rupay", 		/*�������� ������������ �������*/
        "taker"=>"RU76839855",   /* ID ��������� �����  */
        "taker_email"=>"vigorv@mail.ru"
        ,"success_url"=>"https://pay.itdeluxe.com/?order_id=%id%&act=success&paysystem=RuPay"
        ,"result_key"=>"https://pay.itdeluxe.com/money_system/?paysystem=RuPay&act=success"
        ,"fail_url"=>"https://pay.itdeluxe.com/?order_id=%id%&act=fail&paysystem=RuPay"
        ,"to_id"=>"order_id"
        ,"to_info"=>"rupay_user_plat||rupay_id"               //���������� ��������� � info
        //����� �����||
        ,"to_hash"=>"rupay_hash"
        ,"to_sum"=>"rupay_sum"
        ,"currency"=>"$"
        ,"locked"=>"0"
        ,"secret_key"=>"7Y213U" // ��������� ����
        //https://rupay.com/pay.php
        );

        $this->PARAMS["pay_systems"]["bil"]=array(
        "name"=>"�������-����(�������,�������)"		/* ������� � �������*/
        ,"function_prefix"=>"bil" 		/*�������� ������������ �������*/
        ,"currency"=>"RUR"
        ,"locked"=>"1"
        ,"to_id"=>"order_id"
        ,"to_info"=>"ip"               //���������� ��������� � info
        ,"to_sum"=>"pay"
        );

        $this->PARAMS["pay_systems"]["NetUP"]=array(
        "name"=>"�������(NetUP)"		/* ������� � �������*/
        ,"function_prefix"=>"netup" 		/*�������� ������������ �������*/
        ,"currency"=>"RUR"
        ,"locked"=>"1"
        ,"to_id"=>"order_id"
        ,"to_info"=>"ip"               //���������� ��������� � info

        );

        $this->PARAMS["pay_systems"]["Yandex"]=array(
        "name"=>"������.������"         				/* ������� � �������*/
        ,"function_prefix"=>"ya" 		/*�������� ������������ �������*/
        ,"taker"=>"4100188826517"   /* ID ��������� �����  */
        ,"currency"=>"RUR"
        ,"locked"=>"1"
        );

        $this->PARAMS["pay_systems"]["paypal"]=array(
        "name"=>"PayPal"         				/* ������� � �������*/
        ,"function_prefix"=>"paypal" 		/*�������� ������������ �������*/
        ,"currency"=>"$"
        ,"locked"=>"1"
        );

        $this->PARAMS["pay_systems"]["mail"]=array(
        "name"=>"������.Mail"         				/* ������� � �������*/
        ,"function_prefix"=>"mail" 		/*�������� ������������ �������*/
        ,"currency"=>"RUR"
        ,"locked"=>"1"
        );

        $this->PARAMS["pay_systems"]["e-gold"]=array(
        "name"=>"E-gold"         				/* ������� � �������*/
        ,"function_prefix"=>"egold" 		/*�������� ������������ �������*/
        ,"currency"=>"$"
        ,"locked"=>"1"
        );


    }

    function action_user()
    {
        global $SETTINGS;
        $this->PARAMS["out_url"]=out_url($this->PARAMS["url_link"],$_REQUEST);
        $this->mysql= new mySQL();
        $id=0;$noerr=1;
        if(isset($_REQUEST["act"]))$act=$_REQUEST["act"];else $act="";

        include $SETTINGS["PATH_INC"]."/admin/top.html";
        if($act=="paynetup"){
            $this->set_payment_ms();
            if(ip::get_ip()=='217.70.100.242' || ip::get_ip()=='80.64.84.66')
            {
                
                /*//����������� � ���� ��������
                $this->PARAMS["mssql"]=new SQL('192.168.251.21','media','it4215M67','mssql');
                //$this->PARAMS["mssql"]=new SQL('localhost','hawk','123','mssql');
                //������ �� (������� ����) ���������������� IP ������������
                $this->PARAMS["mssql"]->sql_select_db('Accounts');
                $this->PARAMS["mssql"]->sql_execute("SELECT * FROM M_Payments");
                echo "0";
                while($row=$this->PARAMS["mssql"]->sql_fetch_row())
                {
                    echo ++$i." ".$row[0]."  ".$row[1]." ".$row[2]."<br>";
                    
                }
                $this->PARAMS["mssql"]->sql_close();
                */
                $act="success";
                $this->set_success_money_system();
            }else {$this-> notify=' ������(�� �� ������������-))';$act="fail";}
        }

        if($act=="paybil"){
            $this->set_payment_ms();
            if($this->bil())
            {
                $act="success";
                $this->set_success_money_system();
            }else {$act="fail";}
        }
        if($act=="fail"){
        	$this->set_payment_user_final();$this->set_fail();	$act="";  
        	echo $this->notify;
        	}
        if($act=="success"){
            $this->set_payment_user_final();$attrs=$this->set_success();
            $suc_act=$this->succes_action();
            include $SETTINGS["PATH_INC"]."/success.html";
            //$act="";
        }
        if($act=="pay")
        {
            $this->set_payment_user();
            include $SETTINGS["PATH_INC"]."/pay.html";
        }
        if($act==""){
            $this->PARAMS['service']=isset($_REQUEST["serv"])?$_REQUEST["serv"]:"0";
            $this->PARAMS['service_hash']=isset($_REQUEST["hash"])?$_REQUEST["hash"]:"0";
            $this->set_article_byhash();
            
            include $SETTINGS["PATH_INC"]."/index.html";
        }
        include $SETTINGS["PATH_INC"]."/admin/bottom.html";
        $this->mysql->sql_close();
        if(isset($this->id))return $this->id;
    }
    function set_article_byhash()
    {
        if($this->PARAMS['service']!=0)
        {
            $this->mysql->sql_execute('SELECT * from services where id='.$this->PARAMS['service']);
            $row=$this->mysql->sql_fetch_assoc();
            //print_r($row);
            $this->PARAMS['current']['article_name']=$row['article'];
            $this->PARAMS['current']['service_name']=$row['name'];
            $db=$row['db'];
            $name_tablename=$row['table'];
            $name_tablefield=$row['table_field'];
            if($this->PARAMS['service_hash'])
            {
                $this->mysql->sql_execute("SELECT * from ".$db.".payment where hash='".$this->PARAMS['service_hash']."'");
                $row=$this->mysql->sql_fetch_assoc();
                $this->PARAMS['current']['article']=$row['file_id'];
                $this->PARAMS['current']['cost']=$row['cost'];


                $this->mysql->sql_execute("SELECT ".$name_tablefield." from ".$db.".".$name_tablename." where id='".$this->PARAMS['current']['article']."'");
                while($row=$this->mysql->sql_fetch_row())$this->PARAMS['current']['article_name'].=" ".$row[0];
            }
        }
    }
    function get_services2pay()
    {
        $this->mysql->sql_execute('select * from services');
        while($row=$this->mysql->sql_fetch_assoc())
        {
            $out[]=$row;
        }
        return $out;
    }
    


    //Action ��� ��������� ������ ������������ � /money_system/
    function action_money_system()
    {
        $id=0;
        global $SETTINGS;
        $this->PARAMS["out_url"]=out_url($this->PARAMS["url_link"],$_REQUEST);
        $this->mysql= new mySQL();
        if(isset($_REQUEST["act"]))$act=$_REQUEST["act"];else $act="";
        $flag=0;
        switch ($act)
        {
            case "success":
                {
                    $this->set_payment_ms();
                    $this->set_success_money_system();
                    break;
                }
            default:echo "���� �� ��������� ��##����";
        }
        //debug_echo();
        $this->mysql->sql_close();
    }


    //����������� id ����� ��� ����� �� �����
    function reserve_id()
    {
        $sql1="";$sql2="";
        $sql1.="summ";$sql2.="'".$this->payment["sum"]."'";
        $sql1.=",success";$sql2.=", 'R'";
        $sql1.=",type";$sql2.=", '".$this->payment["system"]."'";
        $sql1.=",article";$sql2.=", '".$this->payment["article"]."'";
        $sql1.=",service";$sql2.=", '".$this->payment["service"]."'";
        $sql1.=",info";$sql2.=", '".(ip::get_ip())."'";

         
        $this->mysql->sql_execute("insert into ".$this->SETTINGS["table"]." (".$sql1.") values (".$sql2.")");
        $this->mysql->sql_execute("select LAST_INSERT_ID()");
        $res=$this->mysql->sql_result();
        debug("%2%����� id=".$res);
        return $res;
    }



    function rupay_checkhash()
    {
        $hash=$_POST["rupay_action"]."::";
        $hash.=$_POST["rupay_user"]."::";
        $hash.=$_POST["rupay_id"]."::";
        $hash.=$_POST["rupay_sum"]."::";
        $hash.=$_POST["rupay_user_plat"]."::";
        $hash.=$_POST["name_service"]."::";
        $hash.=$_POST["order_id"]."::";
        $hash.=$this->PARAMS["pay_systems"]["RuPay"]["secret_key"];
        if (md5($hash)==$_POST["rupay_hash"])return TRUE;else return FALSE;
    }
    
    function wm_checkhash()
    {
        if(isset($_REQUEST["LMI_PREREQUEST"])&&$_REQUEST["LMI_PREREQUEST"]==1 &&!isset($_POST["LMI_HASH"]))return TRUE;
        else
        {
            $hash=$_POST["LMI_PAYEE_PURSE"];
            $hash.=$_POST["LMI_PAYMENT_AMOUNT"];
            $hash.=$_POST["LMI_PAYMENT_NO"];
            $hash.=$_POST["LMI_MODE"];
            $hash.=$_POST["LMI_SYS_INVS_NO"];
            $hash.=$_POST["LMI_SYS_TRANS_NO"];
            $hash.=$_POST["LMI_SYS_TRANS_DATE"];
            $hash.=$this->PARAMS["pay_systems"][$this->payment["system"]]["secret_key"];
            $hash.=$_POST["LMI_PAYER_PURSE"];
            $hash.=$_POST["LMI_PAYER_WM"];
            if (strtoupper(md5($hash))==$_POST["LMI_HASH"])return TRUE;else return FALSE;
        }
        return FALSE;
        //return true;
    }

    function set_fail()
    {
        $this->mysql->sql_execute("select summ from ".$this->SETTINGS["table"]." where id=".$this->payment["id"]);
        if($this->mysql->sql_num_rows()==0){debug("������ �������(���������� � set_fail �������)",TRUE);}
        else
        {	$this->mysql->sql_execute("update ".$this->SETTINGS["table"]." set success='F' and info='".ip::get_ip()."' where id=".$this->payment["id"]);}
    }

    function set_success()
    {
        $this->mysql->sql_execute("select summ from ".$this->SETTINGS["table"]." where id=".$this->payment["id"]." and success='N'");
        if($this->mysql->sql_num_rows()==0){echo "������ �������!!";return 0;}
        else
        {
            $out=$this->mysql->sql_fetch_assoc();
            $sum=$out["summ"];
            $this->mysql->sql_execute("update ".$this->SETTINGS["table"]." set success='Y' where id=".$this->payment["id"]);
            return $sum;
        }
    }
    function set_success_money_system()
    {
        $func=$this->PARAMS["pay_systems"][$this->payment["system"]]["function_prefix"]."_checkhash";
        $hash_noerr=1;
        if(method_exists($this, $func))$hash_noerr=$this->$func();
        if($hash_noerr==1)
        {
            $this->mysql->sql_execute("select id from ".$this->SETTINGS["table"]." where id=".$this->payment["id"]);
            if($this->mysql->sql_num_rows()!=0)
            {
                $this->mysql->sql_execute("update ".$this->SETTINGS["table"]." set success='N' , info='".$this->payment["info"]."' where id=".$this->payment["id"]);
                //echo "YES";
            }
            else
            {
                $this->mysql->sql_execute("update ".$this->SETTINGS["table"]." set success='F' , info='".$this->payment["info"]."' where id=".$this->payment["id"]);
            }
            //else echo "NO";
        }else echo "������ ����";

    }

    //���������� ������ ��������� ,���� ������ ��������� FALSE �����  TRUE
    function set_payment_user($check=0)
    {
        $flag=0;//���� ������

        if(isset($_POST["paysystem"]))
        {
            $this->payment["system"]=$_POST["paysystem"];
            $this->payment["service"]=$_POST["service"];
            if(array_key_exists($this->payment["system"],$this->PARAMS["pay_systems"]))
            {
                if(isset($_POST["article"]))$this->payment["article"]=$_POST["article"];else $this->payment["article"]="";
                if(isset($_POST["pay"]))$this->payment["sum"]=$_POST["pay"];else $this->payment["sum"]=0;
                $this->payment["sum"]=str_replace(",",".",$this->payment["sum"]);//�������� ������� �� �����
            }
            else{$flag=1;}
        }
        else{$this->payment["system"]=""; $flag=1;}
        if($flag==0){return TRUE;}else{return FALSE;}
    }

    function set_payment_user_final()
    {
        $flag=0;//���� ������
        if(isset($_REQUEST["paysystem"]))
        {
            $this->payment["system"]=$_REQUEST["paysystem"];
            if(array_key_exists($this->payment["system"],$this->PARAMS["pay_systems"]))
            {
                $attrs=$this->PARAMS["pay_systems"][$this->payment["system"]];
                $this->payment["id"]=(int)$_REQUEST[$attrs["to_id"]];
            }
            else{$flag=1;}
        }
        else{$this->payment["system"]=""; $flag=1;}
        if($flag==0){return TRUE;}else{return FALSE;}
    }

    function set_payment_ms()
    {
        $flag=0;//���� ������
        if(isset($_REQUEST["paysystem"]))
        {
            //print_r($_POST);
            $this->payment["system"]=$_REQUEST["paysystem"];
            $this->payment["service"]=$_POST["service"];

            if(array_key_exists($this->payment["system"],$this->PARAMS["pay_systems"]))
            {
                $attrs=$this->PARAMS["pay_systems"][$this->payment["system"]];
                $this->payment["id"]=(int)$_REQUEST[$attrs["to_id"]];
                $this->payment["summ"]=(float)$_REQUEST[$attrs["to_sum"]];

                $info=explode("||",$attrs["to_info"]);
                $this->payment["info"]="";$razd=0;
                //���������� ���� ������ ���� ����� ���������� ��� ������ ��������
                /*foreach($_REQUEST as $k=>$v)
                {
                    if($razd==1)$this->payment["info"].="||";else $razd=1;
                    $this->payment["info"].=$k."=>".$v;
                }*/

                foreach($info as $v)
                {
                    if($razd==1)$this->payment["info"].="||";else $razd=1;
                    $this->payment["info"].=$_POST[$v];
                }
            }
            else{$flag=1;}
        }
        else{$this->payment["system"]=""; $flag=1;}
        if($flag==0){return TRUE;}else{return FALSE;}
    }

    function succes_action()
    {
        $this->mysql->sql_execute("select * from ".$this->SETTINGS["table"]." where id=".$this->payment["id"]." and success='Y'");
        ob_start();
        if($this->mysql->sql_num_rows()==0){debug("������ �  cuccess_action#1");echo "������ �������";return 0;}
        else{
            $out=$this->mysql->sql_fetch_assoc();
            $this->payment['article']=$out['article'];
            $this->payment['service']=$out['service'];

            $this->set_outcome();
        }
        $out_f=ob_get_contents();
        ob_end_clean();
        return $out_f;
    }

    function set_outcome()
    {
        $id=$this->payment["id"];
        //$id=$this->payment['article'];
        $ip=ip::get_ip();
        $hash=md5("!".$id.":".$ip."!");
        $serv=$this->payment['service'];
        $this->mysql->sql_execute("select * from services where id=".$serv);
        $out=$this->mysql->sql_fetch_assoc();
        $link=$out['returned_link'];
        echo "<form action='".$link."' method=post>\n";
        echo "<input type='hidden' name='hash' value='".$hash."'>\n";
        echo "<input type='hidden' name='id' value='".$id."'>\n";
        echo "<input type='submit' value='��������'>";
        echo "</form>\n";
        
        //echo "<br><a href='".$link."?hash=".$hash."&id=".$id."'>�������</a>";


    }

    function bil()
    {
        $ip=new IP();
        if($ip->is_localip())
        {
            //����������� � ���� ��������
            $this->PARAMS["mssql"]=new SQL('192.168.251.21','media','it4215M67','mssql');
            //$this->PARAMS["mssql"]=new SQL('localhost','hawk','123','mssql');
            //������ �� (������� ����) ���������������� IP ������������
            $this->PARAMS["mssql"]->sql_select_db('Accounts');
            $uip=$ip->get_ip();
            $this->PARAMS["mssql"]->sql_execute("SELECT * FROM M_Users WHERE UserIP='{$uip}'");
            $num = $this->PARAMS["mssql"]->sql_num_rows();
            if ($num>0)     //���� ������ ��������
            {
                $row=$this->PARAMS["mssql"]->sql_fetch_assoc();
                $LS=$row['UserLogonName'];//�������� ��
                $sum=$row['UserBalans'];
                //echo $sum."<br>";
                if($sum>0){
                    $LS=$this->PARAMS["mssql"]->sql_result(0,'UserLogonName');     //������ ������� ����
                    $summ=0;
                    $summ=$this->payment['summ'];
                    //$summ=0.0;
                    //$sum=$this->PARAMS["mssql"]->sql_result(0,'UserBalans');//
                    
                    //echo "INSERT INTO M_Payments (UserAgr, MService, MSum) VALUES ('{$LS}', 32, '".$summ."');";
                    $num = $this->PARAMS["mssql"]->sql_execute("INSERT INTO M_Payments (UserAgr, MService, MSum) VALUES ('{$LS}', 32, ".$summ.");");
                    //$num=TRUE;//false;
                    if($num===FALSE)
                    {
                        echo "������ ��! ������ �� ��������� � ������ �����, ���������� ������� ���� �����."; //������� ������
                        return false;
                    }
                    $this->PARAMS["mssql"]->sql_close();
                    return true;
                }else echo "���������� ����� �� �����";
            }
            else echo "�� ��� ���� ����� � ��������";
            $this->PARAMS["mssql"]->sql_close();
        }else echo $ip->get_ip();
        return false;
    }
    function action_stat()
    {
        global $SETTINGS,$ADMN_USER_ID;
        //
        $id=(isset($_REQUEST['id']))?(int)$_REQUEST['id']:0;
        $this->PARAMS["id"]=$id;
        $p=(isset($_REQUEST['p']))?$_REQUEST['p']:$this->PARAMS['parent'];
        $this->PARAMS['parent']=$p;
        $act=(isset($_REQUEST['act']))?$_REQUEST['act']:'';
        $s=(isset($_REQUEST['s']))?$_REQUEST['s']:0;
        $f=(isset($_REQUEST['f']))?$_REQUEST['f']:0;
        $d=(isset($_REQUEST['d']))?$_REQUEST['d']:0;

        $err_flag=0;
        $this->PARAMS["sort"]=(int)$s;
        $this->PARAMS["direct"]=(int)$d;
        $this->PARAMS["cur_page"]=(int)$f;

        $this->PARAMS["out_url"]=out_url($this->PARAMS["url_link"],$_REQUEST);
        $this->mysql= new mySQL();

        include $SETTINGS["PATH_INC"]."/admin/top.html";

        $this->PARAMS["ERR"]="";
        //��������� ��� ��������-)
        $this->mysql->sql_execute("SELECT count(id),sum(summ) FROM `payment_in` WHERE `success`='Y' and summ>=1.0");
        $row=$this->mysql->sql_fetch_row();
        $this->PARAMS['stat']['SUCCESS']['num_all']=$row[0];
        $this->PARAMS['stat']['SUCCESS']['sum_all']=$row[1];
        echo "�� ���������� �������� ��� ������ ������ 4 �������, �.�. �� ��������� ����(��� ���������� ���� � ����)<hr>1.����� ".$this->PARAMS['stat']['SUCCESS']['num_all']." �������� �� ����� ����� ".$this->PARAMS['stat']['SUCCESS']['sum_all']."�.<br>";

        $this->mysql->sql_execute("SELECT count(id),sum(summ) FROM `payment_in` WHERE `success`='F' and summ>=1.0");
        $row=$this->mysql->sql_fetch_row();
        $this->PARAMS['stat']['FAIL']['num_all']=$row[0];
        $this->PARAMS['stat']['FAIL']['sum_all']=$row[1];
        echo "2.����� ".$this->PARAMS['stat']['FAIL']['num_all']." �� ����������� �������� �� ����� ����� ".$this->PARAMS['stat']['FAIL']['sum_all']."�.<br>";

        $this->mysql->sql_execute("SELECT count(id) FROM `payment_in` WHERE `success`='R' and summ>=1.0");
        $row=$this->mysql->sql_fetch_row();
        $this->PARAMS['stat']['RESERVE']['num_all']=$row[0];
        echo "3.".$this->PARAMS['stat']['RESERVE']['num_all']." �������� ������������������� <br>";

        $this->mysql->sql_execute("SELECT count(id) FROM `payment_in` WHERE `success`='N'");
        $row=$this->mysql->sql_fetch_row();
        $this->PARAMS['stat']['NO']['num_all']=$row[0];
        echo "4.".$this->PARAMS['stat']['NO']['num_all']." �������� �������� � �� ��������� �� ��� ����(���� � ����� �����-����� �����)<br>";
        ////////------------------------------------------------------
        $this->mysql->sql_execute("SELECT count(id),sum(summ) FROM `payment_in` WHERE `success`='Y' and summ>=1.0 and time>DATE_SUB(CURDATE(),  INTERVAL 7 DAY)");
        $row=$this->mysql->sql_fetch_row();
        $this->PARAMS['stat']['SUCCESS']['num_week']=$row[0];
        $this->PARAMS['stat']['SUCCESS']['sum_week']=$row[1];
        echo "<hr>2.1.�� ������ ".$this->PARAMS['stat']['SUCCESS']['num_week']." �������� �� ����� ����� ".$this->PARAMS['stat']['SUCCESS']['sum_week']."�.<br>";
        
        $this->mysql->sql_execute("SELECT count(id),sum(summ) FROM `payment_in` WHERE `success`='Y' and summ>=1.0 AND time> DATE_FORMAT(CURDATE( ) ,'%Y-%m-01') ");
        $row=$this->mysql->sql_fetch_row();
        $this->PARAMS['stat']['SUCCESS']['num_week']=$row[0];
        $this->PARAMS['stat']['SUCCESS']['sum_week']=$row[1];
        echo "2.2.� ������ ������ ".$this->PARAMS['stat']['SUCCESS']['num_week']." �������� �� ����� ����� ".$this->PARAMS['stat']['SUCCESS']['sum_week']."�.(��������� ��� �������� ������ 5 ��������-()<br><hr>";
        
        $this->mysql->sql_execute("SELECT count(id),sum(summ),DATE_FORMAT(time,'%M'),YEAR(time) FROM `payment_in` WHERE `success` = 'Y' group by DATE_FORMAT(time,'%Y-%M') order by DATE_FORMAT(time,'%Y') DESC, DATE_FORMAT(time,'%m') DESC");
        $i=1;
        while($row=$this->mysql->sql_fetch_row())
        {
            $count=$row[0];$sum=$row[1];
            $month=$row[2];$year=$row[3];
            echo "3.".($i++).".� ".$month." ".$year." - ".$count." �������� �� ����� ����� ".$sum."�.<br>";
        }
        echo "<hr>";
        include $SETTINGS["PATH_INC"]."/admin/bottom.html";
        $this->mysql->sql_close();
        
    }

};?>