<?

debug("%1%���������� req/sql.class.php");

/**
 * ����� ������ � SQL �������� � mysql, mssql
 * ��� ������������� ����������� �����-���� ������� �� ������
 * @version 1.11b
 * @last modifired 21.11.2006 14:10
 * 1.11b
 *  - ���������� ���������� ��������� PostgresSQL(��� �������� �� �������� ��)
 * 
 * @example 
 * 
 * 
   ������ ������ � ������� :

   $mysql=new SQL("����",'�����','������','mysql');
   $mysql->sql_select_db('����');
   $mysql->sql_execute('select id,user from table');
   while($row=$mysql->sql_fetch_row())
   {
          $mysql->sql_execute("update table set user='".$row[1]."_1' where id=".$row[0]);
          $mysql->res_pop();//����������� ���������� ������ �� �����
       
   }
   // ����� ����� $mysql->res_pop(); ����������� �������������

   $mysql->sql_close();
   */
 
class SQL {

    /**
     * � ����� ����� ��������?
     * @var string='mysql?mssql'
     */
    var $type="mysql";
    /**
     * ��������� ����������
     * @var mixed
     */
    var $PARAMS;
    /**
     * ������ � ��������
     * @var ctring
     */
    var $ERRORS="";
    /**
     * ��������� �������� ����������;
     * @var string
     */
    var $SQL_HOST,$SQL_LOGIN,$SQL_PASS,$SQL_DATABASE;
    /**
     * ������� ������ �������
     * @var resourse
     */
    var $res;
    /**
     * ���� �������� ��������
     * @var array(res)
     */
    var $ress;
    
    /**
     * ������ �������� �������
     * @var string
     */
    var $query="";
    /**
     * ��������� ������������ ��������
     * @var string(def:'cp1251')
     */
    var $code="cp1251";


    /**
     * @param  $SQL_HOST ����
     * @param  $SQL_LOGIN �����
     * @param  $SQL_PASS ������
     * @param  $type ��� ����������='mysql'
     */
    function SQL($SQL_HOST,$SQL_LOGIN,$SQL_PASS,$type="mysql",$code="cp1251"){
        $this->SQL_HOST=$SQL_HOST;
        $this->SQL_LOGIN=$SQL_LOGIN;
        $this->SQL_PASS=$SQL_PASS;
        $this->type=$type;
        $this->code=$code;
        $this->sql_connect();
    }

    /**
     * ��������� ������� ���� ��� ����������
     * @param  $func �������� 
     * @param  $params ������������_���������
     */
    function func($func="",$params=array())
    {
        $out_f=FALSE;
        if(method_exists($this,$func))
        $out_f=call_user_func_array(array($this, $func),$params);
        else debug("%e%�� ���������� ����� ".$func );
        return $out_f;
    }

    /**
     * ����������� � sql
     */
    function sql_connect(){
        debug("%2%������������ � ".$this->SQL_HOST."(".$this->type.")");
        $this->PARAMS["SQL_CONN"]=$this->func($this->type."_connect");
        if(!$this->PARAMS["SQL_CONN"]){
            $err=$this->func($this->type."_getErrStrings",100);
            $this->PARAMS["SQL_CONN"]=FALSE;
            $this->ERRORS.="������ ����������� � sql(".$err.")\n";
            debug("%e%������ ����������� � sql(".$err.")",1);
        }
        else
        {
            if($this->type=='mysql')$this->sql_execute("SET NAMES {$this->code}");
        }
    }

    
    /**
     * �������� ���� ������
     * @param $SQL_DATABASE ��������_���� 
     */
    function sql_select_db($SQL_DATABASE=''){
        $this->SQL_DATABASE=$SQL_DATABASE;
        if($this->PARAMS["SQL_CONN"]){
            debug("%2%�������� ���� ".$this->SQL_DATABASE);
            $noerr=$this->func($this->type."_select_db");
            if(!$noerr){
                $err=$this->func($this->type."_getErrStrings",122);
                $this->ERRORS.="������������ �������� ���� '".$this->SQL_DATABASE."' - (".$err.")";
                debug("%e%������������ �������� ���� '".$this->SQL_DATABASE."' - (".$err.")",1);
            }
        }
    }

    
    /**
     * �������� ����������
     */
    function sql_close(){
        if($this->PARAMS["SQL_CONN"]){
            debug("%2%����������� �� ".$this->SQL_HOST);
            $this->func($this->type."_close",$this->PARAMS["SQL_CONN"]);
        }
    }


    
    /**
     * ���������� �������
     * @param ������ $query
     * @return ERROR_STRING
     */
    function sql_execute($query){
        static $i;$i++;
        //���� ���������� �����������
        if($this->PARAMS["SQL_CONN"]){
            //���� ���� ������ �� ������� ������ � ����
            if(is_resource($this->res)){if(is_array($this->ress))array_push($this->ress,$this->res);else {$this->ress[]=$this->res;}}
            //echo $query."<br>";
            debug("%3%$i.���������� ������� - $query");
            $this->query=$query;
            $this->res=$this->func($this->type."_query",$query);
            //���� ���� ������ 
            if(!$this->res){
                $err=$this->func($this->type."_getErrStrings",160);
                $this->ERRORS.="������ ���������� ������� - $query (".$err.")\n";
                debug("%e%$i.������ ���������� ������� - $query (".$err.")",1);
                return array("SQL_ERROR"=>"SQL_ERROR");
            }else return "";
        }
    }

    /**
     *�������� ������( �� ������������� ������)
     * @return numered_array
     */
    function sql_fetch_row(){
        if(is_resource($this->res)){
            if($row=$this->func($this->type."_fetch_row"))
            return $row;
            else {
                //echo "<<.".$this->res."<br>"; 
                $this->res_pop();	
                //echo ">>>.".$this->res."<br>";
                return $row;
            }
        }else return array();
    }

    
    /**
     * �������� ������(������������� ������)
     * @return assoc_aray
     */
    function sql_fetch_assoc(){
        if(is_resource($this->res)){
            if($row=$this->func($this->type."_fetch_assoc")){
                return $row;}
                else{
                    $this->res_pop();
                    return $row;
                }
        }else return array();
    }

    /**
     * �������� ���������� ����� �������� ��������
     * @return int
     */
    function sql_num_rows(){
        if(is_resource($this->res)){
            return $this->func($this->type."_num_rows");
        }else return 0;
    }

    /**
     * ������� result �������
     * @param  ������ $row
     * @param ������� $col
     * @return array
     */
    function sql_result($row=0,$col=0){
        if(is_resource($this->res)){
            return $this->func($this->type."_result",$row,$col);
        }
        else return array();
    }

    /**
     * �������� ������ ������
     * @return string
     */
    function get_errors(){return $this->ERRORS;}
    /**
     * pop res array
     */
    function res_pop(){if(is_array($this->ress)){$this->res=array_pop($this->ress);}}
    /**
     * ���� �� ������?
     * @return bool
     */
    function is_errors(){if($this->ERRORS == "")return 0; else return 1;}
    /**
     * ���������� ����(������� ������)
     * @return string
     */
    function sql_affected_rows(){if($this->PARAMS["SQL_CONN"]){return  $this->func($this->type."_affected_rows");}else return "";}
    /**
     * �������� ���� ����� � ���� �������������� �������
     * @return array(assoc_aray)
     */
    function get_outAssocArray(){$curar=array();while($out=$this->sql_fetch_assoc($this->res)){$curar[]=$out;}return $curar;}

    /**
     *��������������� ������� ������� ���������, ���������� ������� � ����� 
     * ����������, ���������� ���� ��� ������ � ������
     */
    function echo_result(){
        $sql_type=$this->get_sqlattr();
        $PARAMS["current"]=$this->get_outAssocArray();
        $PARAMS["info"] = $this->sql_affected_rows();
        if($this->is_errors()){	 echo "<br>&nbsp;".$this->get_errors();}
        else{
            if($sql_type=="create")echo "������� �������<br>";
            elseif($sql_type=="drop")echo "������� �������<br>";
            else{if($PARAMS["info"]!="")echo "������ �������� ".$PARAMS["info"]." �����<br/>";
            else echo "MySQL ������� ������ ��������� (�.�. ���� �����).<br/>";
		if(isset($PARAMS["current"][0])){$cols=array_keys($PARAMS["current"][0]);?>
	  	��������� ���������� �������:<br>
	  	<table border=1><?
	  	foreach ($cols as $col){ echo "<td>".$col."&nbsp;</td>";}
	  	foreach ($PARAMS["current"] as $cur)
	  	{?><tr><?
	  	foreach ($cols as $col){ echo "<td>".$cur[$col]."&nbsp;</td>";}
	  	?></tr><?
    		}?>
  		</table><br>
		<?}else{?>������ ��������<br>
<?}}}
    }

    /**
     * �������� ��� �������� �������
     * @return string(SELECT,CREATE,DROP)
     */
    function get_sqlattr(){
        if($this->query !="")
        {
            $query=trim($this->query);
            //if(preg_match ("CREATE *TABLE", $query, $regs)>0)echo "CREATE";
            if(eregi ("^(select)(.*) *(.*)", $query, $regs))	return "SELECT";
            elseif(eregi ("^(create) *table(.*) *(.*)", $query, $regs))	return "create";
            elseif(eregi ("^(drop) *table(.*)", $query, $regs))	return "drop";
            else return "";
        }
    }




    /*------------������� MySQL-------------*/
    function mysql_connect(){return mysql_connect($this->SQL_HOST,$this->SQL_LOGIN,$this->SQL_PASS);}
    function mysql_select_db(){return	mysql_select_db($this->SQL_DATABASE,$this->PARAMS["SQL_CONN"]);}
    function mysql_close(){mysql_close($this->PARAMS["SQL_CONN"]);}
    function mysql_query($query){return mysql_query($query,$this->PARAMS["SQL_CONN"]);}
    function mysql_fetch_row(){return mysql_fetch_row($this->res);}
    function mysql_fetch_assoc(){return mysql_fetch_assoc($this->res);}
    function mysql_num_rows(){return mysql_num_rows($this->res);}
    function mysql_result($row=0,$col=0){return mysql_result($this->res,$row,$col);}
    function mysql_affected_rows(){return mysql_affected_rows($this->PARAMS["SQL_CONN"]);}
    //����������� ������ ������ ������(str- ������ �� ������� ���������� �����)
    function mysql_getErrStrings($str="",$con=""){if(mysql_error()!="")return mysql_errno().":".mysql_error()."(str=".$str.")";else return FALSE;}


    /*------------������� MsSQL-------------*/
    //����������� ������ ������ ������(str- ������ �� ������� ���������� �����)
    function mssql_getErrStrings($str=""){return mssql_get_last_message();}
    function mssql_connect(){return @mssql_connect($this->SQL_HOST,$this->SQL_LOGIN,$this->SQL_PASS);}
    function mssql_select_db(){return	mssql_select_db($this->SQL_DATABASE,$this->PARAMS["SQL_CONN"]);}
    function mssql_close(){mssql_close($this->PARAMS["SQL_CONN"]);}
    function mssql_query($query){return mssql_query($query,$this->PARAMS["SQL_CONN"]);}
    function mssql_fetch_row(){return mssql_fetch_row($this->res);}
    function mssql_fetch_assoc(){return mssql_fetch_assoc($this->res);}
    function mssql_num_rows(){return mssql_num_rows($this->res);}
    function mssql_result($row=0,$col=0){return mssql_result($this->res,$row,$col);}
    function mssql_affected_rows(){return mssql_affected_rows($this->PARAMS["SQL_CONN"]);}


    /*------------������� PgSQL-------------*/
    function pg_connect(){
        return @pg_connect("host=".$this->SQL_HOST." user=".$this->SQL_LOGIN." password=".$this->SQL_PASS);
    }
    ///!!!!!!!!!!!!!!!!!!
    function pg_select_db(){
        return @pg_connect("host=".$this->SQL_HOST." user=".$this->SQL_LOGIN." password=".$this->SQL_PASS." dbname=".$this->SQL_DATABASE);
        //return	mysql_select_db($this->SQL_DATABASE,$this->PARAMS["SQL_CONN"]);
    }
    function pg_close(){pg_close($this->PARAMS["SQL_CONN"]);}
    function pg_query($query){return pg_query($this->PARAMS["SQL_CONN"],$query);}
    function pg_fetch_row(){return pg_fetch_row($this->res);}
    function pg_fetch_assoc(){return pg_fetch_assoc($this->res);}
    function pg_num_rows(){return pg_num_rows($this->res);}
    function pg_result($row=0,$col=0){return pg_get_result($this->res,$row,$col);}
    function pg_affected_rows(){return pg_affected_rows($this->PARAMS["SQL_CONN"]);}
    //����������� ������ ������ ������(str- ������ �� ������� ���������� �����)
    function pg_getErrStrings($str="",$con=""){if(pg_last_error()!="")return mysql_errno().":".pg_last_error()."(str=".$str.")";else return FALSE;}


}

class mySQL extends SQL {
    function mySQL() {
        global $SETTINGS;
        $this->SQL($SETTINGS["SQL_HOST"],$SETTINGS["SQL_LOGIN"],$SETTINGS["SQL_PASS"],"mysql");
        $this->sql_select_db($SETTINGS["SQL_DATABASE"]);
    }
}

class msSQL extends SQL {
    function msSQL() {
        global $SETTINGS;
        $this->SQL($SETTINGS["SQL_HOST"],$SETTINGS["SQL_LOGIN"],$SETTINGS["SQL_PASS"],"mssql");
        $this->sql_select_db($SETTINGS["SQL_DATABASE"]);
    }
}

class pgSQL extends SQL {
    function pgSQL() {
        global $SETTINGS;
        $this->SQL($SETTINGS["SQL_HOST"],$SETTINGS["SQL_LOGIN"],$SETTINGS["SQL_PASS"],"pg");
        $this->sql_select_db($SETTINGS["SQL_DATABASE"]);
    }
}

?>
