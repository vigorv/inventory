<?

debug("%1%Подключаем req/sql.class.php");

/**
 * Класс работы с SQL работает с mysql, mssql
 * нет необходимости вытаскивать какие-либо ресурсы из класса
 * @version 1.11b
 * @last modifired 21.11.2006 14:10
 * 1.11b
 *  - Добавленна простейшая потдержка PostgresSQL(без проверки на реальной дб)
 * 
 * @example 
 * 
 * 
   пример работы с классом :

   $mysql=new SQL("хост",'логин','пароль','mysql');
   $mysql->sql_select_db('база');
   $mysql->sql_execute('select id,user from table');
   while($row=$mysql->sql_fetch_row())
   {
          $mysql->sql_execute("update table set user='".$row[1]."_1' where id=".$row[0]);
          $mysql->res_pop();//Вытаскивает предыдущий ресурс из стека
       
   }
   // после цыкла $mysql->res_pop(); выролняется автоматически

   $mysql->sql_close();
   */
 
class SQL {

    /**
     * С какой базой работаем?
     * @var string='mysql?mssql'
     */
    var $type="mysql";
    /**
     * Параметры соединения
     * @var mixed
     */
    var $PARAMS;
    /**
     * Строка с ошибками
     * @var ctring
     */
    var $ERRORS="";
    /**
     * Параметры текущего соединения;
     * @var string
     */
    var $SQL_HOST,$SQL_LOGIN,$SQL_PASS,$SQL_DATABASE;
    /**
     * текущий ресурс запроса
     * @var resourse
     */
    var $res;
    /**
     * Стек ресурсов запровов
     * @var array(res)
     */
    var $ress;
    
    /**
     * Строка текущего запроса
     * @var string
     */
    var $query="";
    /**
     * Кодировка отображаемой страницы
     * @var string(def:'cp1251')
     */
    var $code="cp1251";


    /**
     * @param  $SQL_HOST ХОСТ
     * @param  $SQL_LOGIN ЛОГИН
     * @param  $SQL_PASS ПАРОЛЬ
     * @param  $type тип соединения='mysql'
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
     * выполняет функцию если она существует
     * @param  $func название 
     * @param  $params передаваемые_параметры
     */
    function func($func="",$params=array())
    {
        $out_f=FALSE;
        if(method_exists($this,$func))
        $out_f=call_user_func_array(array($this, $func),$params);
        else debug("%e%Не существует метод ".$func );
        return $out_f;
    }

    /**
     * Подключение к sql
     */
    function sql_connect(){
        debug("%2%Подключаемся к ".$this->SQL_HOST."(".$this->type.")");
        $this->PARAMS["SQL_CONN"]=$this->func($this->type."_connect");
        if(!$this->PARAMS["SQL_CONN"]){
            $err=$this->func($this->type."_getErrStrings",100);
            $this->PARAMS["SQL_CONN"]=FALSE;
            $this->ERRORS.="Ошибка подключения к sql(".$err.")\n";
            debug("%e%Ошибка подключения к sql(".$err.")",1);
        }
        else
        {
            if($this->type=='mysql')$this->sql_execute("SET NAMES {$this->code}");
        }
    }

    
    /**
     * выбирает базу данных
     * @param $SQL_DATABASE Название_базы 
     */
    function sql_select_db($SQL_DATABASE=''){
        $this->SQL_DATABASE=$SQL_DATABASE;
        if($this->PARAMS["SQL_CONN"]){
            debug("%2%Выбираем базу ".$this->SQL_DATABASE);
            $noerr=$this->func($this->type."_select_db");
            if(!$noerr){
                $err=$this->func($this->type."_getErrStrings",122);
                $this->ERRORS.="Неправильное название базы '".$this->SQL_DATABASE."' - (".$err.")";
                debug("%e%Неправильное название базы '".$this->SQL_DATABASE."' - (".$err.")",1);
            }
        }
    }

    
    /**
     * закрытие соединения
     */
    function sql_close(){
        if($this->PARAMS["SQL_CONN"]){
            debug("%2%Отключаемся от ".$this->SQL_HOST);
            $this->func($this->type."_close",$this->PARAMS["SQL_CONN"]);
        }
    }


    
    /**
     * Выполнение запроса
     * @param запрос $query
     * @return ERROR_STRING
     */
    function sql_execute($query){
        static $i;$i++;
        //Если существует подключение
        if($this->PARAMS["SQL_CONN"]){
            //Если есть запрос то занести старый в стек
            if(is_resource($this->res)){if(is_array($this->ress))array_push($this->ress,$this->res);else {$this->ress[]=$this->res;}}
            //echo $query."<br>";
            debug("%3%$i.Выполнение запроса - $query");
            $this->query=$query;
            $this->res=$this->func($this->type."_query",$query);
            //если есть ошибки 
            if(!$this->res){
                $err=$this->func($this->type."_getErrStrings",160);
                $this->ERRORS.="Ошибка выполнения запроса - $query (".$err.")\n";
                debug("%e%$i.Ошибка выполнения запроса - $query (".$err.")",1);
                return array("SQL_ERROR"=>"SQL_ERROR");
            }else return "";
        }
    }

    /**
     *получить строку( не ассоциативный массив)
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
     * получить строку(ассоциативный массив)
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
     * Получить количество строк вернутых запросом
     * @return int
     */
    function sql_num_rows(){
        if(is_resource($this->res)){
            return $this->func($this->type."_num_rows");
        }else return 0;
    }

    /**
     * Вернуть result запроса
     * @param  строка $row
     * @param столбец $col
     * @return array
     */
    function sql_result($row=0,$col=0){
        if(is_resource($this->res)){
            return $this->func($this->type."_result",$row,$col);
        }
        else return array();
    }

    /**
     * Получить строку ошибок
     * @return string
     */
    function get_errors(){return $this->ERRORS;}
    /**
     * pop res array
     */
    function res_pop(){if(is_array($this->ress)){$this->res=array_pop($this->ress);}}
    /**
     * Есть ли ошибки?
     * @return bool
     */
    function is_errors(){if($this->ERRORS == "")return 0; else return 1;}
    /**
     * Затронутые ряды(читайте мануал)
     * @return string
     */
    function sql_affected_rows(){if($this->PARAMS["SQL_CONN"]){return  $this->func($this->type."_affected_rows");}else return "";}
    /**
     * Получить весь ответ в виде ассоциативного массива
     * @return array(assoc_aray)
     */
    function get_outAssocArray(){$curar=array();while($out=$this->sql_fetch_assoc($this->res)){$curar[]=$out;}return $curar;}

    /**
     *Вспомогательная функция выводит результат, выполнения запроса с всеми 
     * атрибутами, затронутые ряды сам массив и ошибки
     */
    function echo_result(){
        $sql_type=$this->get_sqlattr();
        $PARAMS["current"]=$this->get_outAssocArray();
        $PARAMS["info"] = $this->sql_affected_rows();
        if($this->is_errors()){	 echo "<br>&nbsp;".$this->get_errors();}
        else{
            if($sql_type=="create")echo "Создана таблица<br>";
            elseif($sql_type=="drop")echo "Удалена таблица<br>";
            else{if($PARAMS["info"]!="")echo "Запрос затронул ".$PARAMS["info"]." рядов<br/>";
            else echo "MySQL вернула пустой результат (т.е. ноль рядов).<br/>";
		if(isset($PARAMS["current"][0])){$cols=array_keys($PARAMS["current"][0]);?>
	  	Результат выполнения запроса:<br>
	  	<table border=1><?
	  	foreach ($cols as $col){ echo "<td>".$col."&nbsp;</td>";}
	  	foreach ($PARAMS["current"] as $cur)
	  	{?><tr><?
	  	foreach ($cols as $col){ echo "<td>".$cur[$col]."&nbsp;</td>";}
	  	?></tr><?
    		}?>
  		</table><br>
		<?}else{?>Запрос выполнен<br>
<?}}}
    }

    /**
     * Выясняет тип текущего запроса
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




    /*------------функции MySQL-------------*/
    function mysql_connect(){return mysql_connect($this->SQL_HOST,$this->SQL_LOGIN,$this->SQL_PASS);}
    function mysql_select_db(){return	mysql_select_db($this->SQL_DATABASE,$this->PARAMS["SQL_CONN"]);}
    function mysql_close(){mysql_close($this->PARAMS["SQL_CONN"]);}
    function mysql_query($query){return mysql_query($query,$this->PARAMS["SQL_CONN"]);}
    function mysql_fetch_row(){return mysql_fetch_row($this->res);}
    function mysql_fetch_assoc(){return mysql_fetch_assoc($this->res);}
    function mysql_num_rows(){return mysql_num_rows($this->res);}
    function mysql_result($row=0,$col=0){return mysql_result($this->res,$row,$col);}
    function mysql_affected_rows(){return mysql_affected_rows($this->PARAMS["SQL_CONN"]);}
    //стандартная строка вывода ошибки(str- строка из которой происходит вызов)
    function mysql_getErrStrings($str="",$con=""){if(mysql_error()!="")return mysql_errno().":".mysql_error()."(str=".$str.")";else return FALSE;}


    /*------------функции MsSQL-------------*/
    //стандартная строка вывода ошибки(str- строка из которой происходит вызов)
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


    /*------------функции PgSQL-------------*/
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
    //стандартная строка вывода ошибки(str- строка из которой происходит вызов)
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
