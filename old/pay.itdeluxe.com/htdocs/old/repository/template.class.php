<?

debug("%1%Подключаем req/template.class.php");
include_once PATH_REPOSIT."/pager.class.php";
include_once PATH_REPOSIT."/sql.class.php";

/**
 * Основной класс шаблона содержит функции отображения для 
 * администрования данных
 * @version 0.794h
 * @last modifired 12.12.2006 13:20
 * 0.794h
 * - добавлен тип в edit check,
 * - сохранение i get НкМ
 * - Добавленна возможность изменять списки 1forN в get_1forN_child();
 * - Добавленна возможноость таскать с собой параметры различные(set_outurl_array)
 * 0.792b
 * - добавленна  подпись в режиме edit([edit][comment])
 * 0.791b
 * - добавленна поддержка action_child()
 * 0.79b
 * - добавленны parent - только 1 
 * - добавленны childs -много можно
 * 0.78b
 * - Добавленна поддержка saveFromAdminChild()
 * - p===parent
 * - добавлен тип обьета файл,
 * - добалены ссылки в папку с картинками(надо еше и везде заменить)
 * 0.77b
 * - пофиксен баг в get_razd()
 * -Добавленна возможность менять шаблоны админки $conf['(show/get/edit)_template']
 */
class template
{
    //==========================НАЧАЛО переменных================================

    /**
 	 *Здесь находятся все настройки
 	 * @var array
     */
    var $SETTINGS=array();
    /**
 	 *  Выводимые на экран строки задаваемые для потдержки легкого переноса подобных обьектов
 	 * @var array
     */
    var $STRINGS=array();
    /**
 	 *Сюда складываются любые нужные нам параметры
 	 * @var array
     */
    var $PARAMS=array();
    /**
 	 *Сюда складываются неизменные массивы (e.g. виды принимаемых валют)
 	 * @var array
     */
    var $ARRAYS=array();
    /**
 	 *Здесь все ошибки 
 	 * @var array
     */
    var $ERRORS;
    /**
 	 *Текущее подключение к mysql 
 	 * @var array
     */
    var $mysql=null;
    /**
 	 * Строка нотификации(e.g. Сохраненно, Удаленно)
 	 * @var array
     */
    var $notify='';
    /**
 	 * строка для вывода пагинации
 	 * @var array
     */
    var $pager;
    /**
 	 * Здесь лежат настроечные переменные
 	 * задаются в child::'child_name'
 	 * @var array
     */
    var $vars;
    /**
 	 * Здесь лежат таскаемые В URL переменные
 	 * меняются в set_outurl_array
 	 * @var array
     */
    var $outurl_array=array("f","p","t","s","d","part");

    /**
     * Форматирование строки вывода ошибки
     * @var string 
     */
    var $ERROR_STRING="<font color=\"red\"><b>%ERR%</b></font>";

    /*
    =================Конец переменных========================================
    *************************************************************************
    *************************************************************************
    *************************************************************************
    =================НАЧАЛО БАЗОВЫХ функции==================================
    */
    /**
	 * Возвращает файловый_перфикс(для засисящих страниц)
	 *
	 * @return string
	 */
    function get_filesprefix(){return $this->SETTINGS["files_prefix"];}
    /**
	 * Устанавливает необходимый нам параметр
	 * если этот параметр уже есть, то делает копию его в name.'_old'
	 * @param string $name - имя параметра
	 * @param mixed $param
	 */
    function set_param($name,$param){if(isset($this->PARAMS[$name])){$this->PARAMS[$name.'_old']=$this->PARAMS[$name];}$this->PARAMS[$name]=$param;}
    /**
     * Изменяет список параметров которые передаются с URL
     * @param параметр $param
     * @param 1-удалить/0-добавить $isdel
     */
    function set_outurl_array($param,$isdel=0)
    {
        if($isdel==1)
        {
            if(in_array($param,$this->outurl_array))unset($this->outurl_array[$param]);
        }
        else
        {
            if(!in_array($param,$this->outurl_array))array_push($this->outurl_array,$param);
        }
    }

    
    /**
	 * Возвращает текущий параметр
	 *
	 * @param string $name - имя параметра
	 * @return mixed
	 */
    function get_param($name){if (isset($this->PARAMS[$name]))return $this->PARAMS[$name];else return "";}
    /**
	 * Получает список  выводимых параметров для вывода админу
	 * в полном списке обьектов
	 * @return array
	 */
    function get_admin_show_rows(){return $this->PARAMS["show_rows"];}
    /**
	 * Получает список  выводимых параметров для вывода админу
	 * для конкретного обьекта
	 * @return array
	 */
    function get_admin_get_rows(){return $this->PARAMS["get_rows"];}
    /**
	 * Получает список  выводимых параметров для редакирования админу
	 * для конкретного обьекта
	 * @return array
	 */
    function get_admin_edit_rows(){return $this->PARAMS["edit_rows"];}
    /**
	 * Получает текущий разделитель
	 * @return '?_or_&'
	 */
    function get_razd() {if(strpos($this->PARAMS["out_url"],"?")===false)return "?"; else return "&"; }
    /**
	 * Возвращает имя текцщего класса
	 * @return string
	 */
    function name(){return get_class($this);}

    //=================Конец БАЗОВЫХ функции========================================

    /**
     * начальные настройки.
     */
    function template()
    {
        //Подгружаем глобальные настройки из(req/config.php)
        global $SETTINGS;

        //Базовые строки ошибок
        $this->ERRORS["NOT_FOUND"]="Не найдено";
        $this->ERRORS["LOGIN_DUBL"]="Такой login уже есть";
        $this->ERRORS["DOMEN_DUBL"]="Такой domen уже есть";
        $this->ERRORS["NOT_EMAIL"]="Некорректный email";
        $this->ERRORS["NOT_LOGIN"]="Некорректный логин";
        $this->ERRORS["NOT_PRESENT"]="Не должно быть пусто";
        $this->ERRORS["NOT_PASSWORD"]="Некорректный пароль";
        $this->ERRORS["NOT_EMAIL"]="Некорректный email";
        $this->ERRORS["NOT_NAME"]="Некорректное имя";
        $this->ERRORS["NOT_PHONE"]="Некорректный телефон";
        $this->ERRORS["LOGIN_ERR"]="Ошибка в логине или пароле";
        $this->ERRORS["SQL_ERROR"]="ошибка SQL";
        $this->ERRORS["ADMN_ONLY_READ"]="Ошибка доступа";

        //Есть ли текущая ошибка
        $this->PARAMS["ERR"]="";
        //Ссылка на текущий файл
        $this->PARAMS["url_link"]="";
        //сортировочное поле(его номер столбца в выводе)
        $this->PARAMS["sort"]=0;
        //направление сортировки 0-asc 1-desc
        $this->PARAMS["direct"]=0;
        //текущий id
        $this->PARAMS["id"]=0;
        $this->PARAMS["parent"]=0;
        //
        $this->PARAMS["out_url"]="";
        $this->PARAMS["part_name"]="";
        $this->PARAMS["show_rows"]=array();
        $this->PARAMS["get_rows"]=array();
        $this->PARAMS["edit_rows"]=array();
        //текущий табличный префикс
        $this->SETTINGS["table_prefix"]="";
        //Количество обьектов на страницу 0- вывести все
        $this->SETTINGS['pager_type']=0;
        $this->SETTINGS["COUNT_OBJ_ON_PAGE"]=$SETTINGS["COUNT_OBJ_ON_PAGE"];
        $this->SETTINGS['SERVER_IMG_URL']=$SETTINGS['SERVER_IMG_URL'];
        $this->SETTINGS['IMG_URL']=$SETTINGS['IMG_URL'];

        $this->PARAMS["showparams"]['childs']=array();
        $this->PARAMS["showparams"]['parent']=array();
        $this->SETTINGS["get_arrays"]=array();
        $this->notify="";
        $this->SETTINGS["show_template"]='/admin/show.html';
        $this->SETTINGS["get_template"]='/admin/get.html';
        $this->SETTINGS["edit_template"]='/admin/edit.html';


        //Виды редактирования для edit ()
        $this->SETTINGS["edit_rows_types"]=array("textarea","input","hidden","select","chekbox");
        //Полный список настроек параметра используется в this::init
        $this->SETTINGS["all_vars_params"]=array(
            "name"=>""/*Название (e.g. если работаем с базой столбца)*/
            ,"string"=>""/*Название для вывода на экран*/
            ,"is_show"=>"1"/*Отображать для ф-ии show*/
            ,"is_edit"=>"1"/*Отображать для ф-ии edit*/
            ,"is_get"=>"1"/*Отображать для ф-ии get*/
            ,"is_save"=>"1"/*Сохранять по стандартному алгоритму*/
            ,"table"=>"this"/*Параметр принадлежит таблице(название таблицы или this для текущей таблицы)*/
            //,"edit_params"
            ,"edit"=> array("type"=>"input","value_list"=>array("Y","N"),'dir'=>'','resize'=>array('width'=>0,'filepostfix'=>''),'comment'=>'','attribs'=>array())
            ,"show"=> array("type"=>"")
            ,"save"=> array("type"=>"other","if_null"=>"1","obligation"=>"0","errors"=> array("0"))
            ,"specification" =>array()
        );
    }
    //Инициализация класса
    function init($vars,$conf)
    {
        foreach ($vars as $key => $value)
        {	foreach ($this->SETTINGS["all_vars_params"] as $v=>$def)
        {if(isset($value["$v"]))
        {

            if(is_array($value["$v"])){
                $this->vars[$key][$v]=$value["$v"]+$def;
            }
            else 	$this->vars[$key][$v]=$value["$v"];
        }
        else $this->vars[$key][$v]=$def;
        }
        }
        $this->SETTINGS["table"]=$conf["table_name"];
        $this->SETTINGS["table_prefix"]=$this->SETTINGS["table"]."_";
        if(isset($conf["thisfile"]) && $conf["thisfile"]!="")
        $this->PARAMS["url_link"]=$conf["thisfile"];
        if(isset($conf["show_template"]))$this->SETTINGS['show_template']=$conf["show_template"];
        if(isset($conf["get_template"]))$this->SETTINGS['get_template']=$conf["get_template"];
        if(isset($conf["edit_template"]))$this->SETTINGS['edit_template']=$conf["edit_template"];

        else $this->PARAMS["url_link"]=$_SERVER["PHP_SELF"];
        if(isset($conf["get_arrays"]))$this->SETTINGS["get_arrays"]=$conf["get_arrays"];
        debug("%1%Инициализируем класс <font color='#ff8080'>".$this->name()."</font>");
        unset($vars);

        //выбираем нужные переменные, для разных типов отображения
        foreach($this->vars as $var_name => $var_attrs)
        {
            if($var_attrs["is_show"]==1) 	$this->PARAMS["show_rows"][$var_name]=$var_attrs;
            if($var_attrs["is_get"]==1) 	$this->PARAMS["get_rows"][$var_name]=$var_attrs;
            if($var_attrs["is_edit"]==1) 	$this->PARAMS["edit_rows"][$var_name]=$var_attrs;
        }
    }

    ///тут производятся все виды действии// в конкетных случаях подлежит переопрделению

    function action()
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

        $this->PARAMS["out_url"]=$this->out_url($this->PARAMS["url_link"],$_REQUEST);
        $this->mysql= new mySQL();
        include $SETTINGS["PATH_INC"]."/admin/top.html";
        $this->get_1forN();
        $this->getParent();
        if(method_exists($this,'action_child'))$this->action_child();
        if($act=="save"){
            //echo "<pre>";print_r($_REQUEST);
            $this->set_save_values();
            $this->saveFromAdmin();
            if(method_exists($this,'saveFromAdminChild'))$this->saveFromAdminChild();
            if($this->PARAMS["ERR"]!=""){error_echo();$err_flag=1;$act="edit";}
            else{	$this->notify="Сохранено";	$act="show";}
        }
        if($act=="edit"){
            $this->PARAMS['current']['parent']=$this->PARAMS['parent'];

            if($err_flag!=1){
                if($this->PARAMS["id"]!=0){
                    $this->get4admin();
                }
            }
            include $SETTINGS["PATH_INC"].$this->SETTINGS["edit_template"];
            $this->PARAMS["ERR"]="";
        }
        if($act=="del"){
            $this->delete();
            $this->notify="Удалено";
        }
        if($act=="activate"){
            $this->PARAMS["active"]=$active;
            $this->activate();
            $this->notify="Обновлено";
        }

        if($act=="show"){
            if($this->PARAMS["id"]!=0){
                $this->get4admin();
                include $SETTINGS["PATH_INC"].$this->SETTINGS["get_template"];
            }
        }
        if($this->PARAMS["ERR"])
        {
            error_echo();
            $this->notify="";
        }
        $this->PARAMS["ERR"]="";
        $this->show4admin();
        include $SETTINGS["PATH_INC"].$this->SETTINGS["show_template"];
        include $SETTINGS["PATH_INC"]."/admin/bottom.html";
        $this->mysql->sql_close();
    }

    //Отобазить админу
    function show4admin()
    {
        $where=' ';
        if($this->PARAMS['parent']>0)$where.='where parent='.$this->PARAMS['parent'];

        $addsql="";$sql1="";$LIMIT="";$i=0;
        //выбираем нужные переменные
        foreach($this->vars as $var_name => $var_attrs)
        {
            if($var_attrs["is_show"]==1 && $var_attrs["table"]=="this")
            {
                //$this->PARAMS["show_rows"][$var_name]=$var_attrs;
                $outs[$var_name]="t.".$var_name." as ".$var_name;
                if($i==$this->PARAMS["sort"])$sort=$var_name;
                $i++;
            }
        }
        $addsql="order by ".$sort;
        if(isset($this->PARAMS["direct"]) &&$this->PARAMS["direct"]==1)$addsql.=" desc ";
        $func_in=implode(", " ,$outs);

        $qf=0;
        if($this->SETTINGS["COUNT_OBJ_ON_PAGE"]>0){
            $StLimit=(int)$this->PARAMS["cur_page"]*$this->SETTINGS["COUNT_OBJ_ON_PAGE"];

            $this->mysql->sql_execute("select ".$func_in." from ".$this->SETTINGS["table"]." as t ".$where);

            $count=$this->mysql->sql_num_rows();
            if($count>$this->SETTINGS["COUNT_OBJ_ON_PAGE"]){
                $pager=new Pager($count,$this->SETTINGS["COUNT_OBJ_ON_PAGE"],$this->PARAMS["cur_page"],$this->PARAMS["out_url"]);
                $pager->Create($this->SETTINGS['pager_type']);
                $this->PARAMS["pager"]=$pager->Get();
            }
            $LIMIT=" LIMIT ".$StLimit.",".$this->SETTINGS["COUNT_OBJ_ON_PAGE"];
        }
        $res=$this->mysql->sql_execute("select ".$func_in." from ".$this->SETTINGS["table"]." as t ".$where." ".$addsql.$LIMIT);
        if($this->PARAMS["ERR"])return 0;
        $count=0;
        $outar=array();
        while($out=$this->mysql->sql_fetch_assoc()){
            $count++;
            $curar=array();
            foreach ($outs as $key =>$val){
                $curar[$key]=$out[$key];//тут косяк наверно, нужно к каждой перемнной получаемой писать t.id as id
            }
            $curar["num"]=$StLimit+$count;
            $outar[]=$curar;
        }
        $this->PARAMS[$this->SETTINGS["table_prefix"]."show"]=$outar;
        return $this->PARAMS[$this->SETTINGS["table_prefix"]."show"];
    }

    function get4admin()//возвращает по id информацию для админа
    {$to_get=array();
    //выбираем нужные переменные
    foreach($this->vars as $var_name => $var_attrs)
    {
        if($var_attrs["is_get"]==1)
        if($var_attrs["table"]=="this"){
            //$this->PARAMS["get_rows"][$var_name]=$var_attrs;
            $outs[$var_name]="t.".$var_name." as ".$var_name;
        }
        else
        {
            $to_get[$var_attrs["table"]][$var_name]=$var_attrs;
        }
    }
    if(isset($this->PARAMS["direct"]) &&$this->PARAMS["direct"]==1)$addsql.=" desc ";
    $func_in=implode(", " ,$outs);
    //$sql="select ".$func_in." from  ".$this->SETTINGS["table"]." as t LEFT JOIN ".$this->SETTINGS["obj_logins_table"]." as ot ON (ot.id=t.id and ot.obj_type='".$this->SETTINGS["obj_type"]."') where t.id=".$this->PARAMS["id"];
    $sql="select ".$func_in." from  ".$this->SETTINGS["table"]." as t  where t.id=".$this->PARAMS["id"];
    $this->mysql->sql_execute($sql);
    if($this->PARAMS["ERR"]!="")return 0;

    if($this->mysql->sql_num_rows()==0){$this->PARAMS["ERR"]="NOT_FOUND";return 0;}
    $out =$this->mysql->sql_fetch_assoc();
    foreach ($out as $key=>$val)
    {
        $func_out[$key]=$val;
    }
    $this->PARAMS["current"]=$func_out;
    $this->get4admin_other_tables($to_get);
    return $this->PARAMS["current"];
    }

    function get4admin_other_tables($to_get)
    {
        //print_r($to_get);
        foreach($to_get as $table => $v)
        {
            foreach($v as $key => $cur_vals)//$cur_val-сохранненяемые занчения
            {
                $attrs=$this->vars[$key];
                if(isset($attrs['specification']['link_type'])&&$attrs['specification']['link_type']=='NforM')
                {
                    $this->mysql->sql_execute("SELECT child from ".$table." where parent=".$this->PARAMS['id']);
                    while($row=$this->mysql->sql_fetch_row())
                    {
                        $this->PARAMS['current'][$key][]=$row[0];
                    }
                    //print_r($this->PARAMS['current'][$key]);
                }
            }
        }
    }


    //Функция работающая, по нажатию кнопки Сохранить
    function saveFromAdmin()
    {
        //global $PARAMS;
        if(isset($ADMN_ONLY_READ)&& ADMN_ONLY_READ=="Y"){
            $this->PARAMS["ERR"]="ADMN_ONLY_READ";
            return;
        }
        $this->adapt();
        if($this->PARAMS["ERR"]!="")return;
        //если есть id обновляяем, если нет то новую запись
        if($this->PARAMS["id"]!=0) $this->update();else $this->add();

    }
    function save_other_tables($to_save)
    {
        foreach($to_save as $table => $v)
        {
            //  echo $table."=>";
            foreach($v as $key => $cur_vals)//$cur_val-сохранненяемые занчения
            {
                //    echo $key." ";
                $attrs=$this->vars[$key];

                if(isset($attrs['specification']['link_type'])&&$attrs['specification']['link_type']=='NforM')
                {
                    $this->mysql->sql_execute("delete from ".$table." where parent=".$this->PARAMS['id']);
                    if(is_array($cur_vals))foreach($cur_vals as $cur_val)
                    {
                        $this->mysql->sql_execute("insert into ".$table." (`parent`,`child`) VALUES ('".$this->PARAMS['id']."','".$cur_val."')");
                    }

                }
            }
        }
    }

    function update()
    {
        $sql="";$flag=0;$to_save=array();
        //echo "<pre>";print_r($this->vars);
        foreach($this->vars as $key => $v)
        {
            //echo $key." ";
            if($v["is_save"]==1)
            if($v["table"]=='this')
            {
                if($this->PARAMS["current"][$key]!="" ||($this->PARAMS["current"][$key]=="" &&$v["save"]["if_null"]==1))
                {
                    if($flag!=0){$sql.=" ,";}else { $flag=1;}
                    $sql.=$key."='".$this->PARAMS["current"][$key]."'";
                    // echo $key." ";
                }
                //echo "<br>";
            }
            else
            {
                $to_save[$v["table"]][$key]=$this->PARAMS["current"][$key];
            };
        }
        //echo "update ".$this->SETTINGS["table"]." set ".$sql." where id=".$this->PARAMS["id"]."<br>";
        $this->PARAMS["ERR"]=$this->mysql->sql_execute("update ".$this->SETTINGS["table"]." set ".$sql." where id=".$this->PARAMS["id"]);
        //Сохраняем таблицу связи НкМ
        $this->save_other_tables($to_save);


    }
    function add()
    {
        $sql1="";$sql2="";$flag=0;$to_save=array();
        foreach($this->vars as $key => $v)
        {
            if($v["is_save"]==1)
            if($v["table"]=='this')
            {
                if($this->PARAMS["current"][$key]!="" ||($this->PARAMS["current"][$key]=="" &&$v["save"]["if_null"]==1))
                {
                    if($flag!=0){$sql1.=" ,";$sql2.=", ";}else{ $flag=1;}
                    $sql1.=$key;$sql2.="'".$this->PARAMS["current"][$key]."'";
                }
            }
            else
            {
                $to_save[$v["table"]][$key]=$this->PARAMS["current"][$key];
            }

        };

        $this->PARAMS["ERR"]=$this->mysql->sql_execute("insert into ".$this->SETTINGS["table"]." (".$sql1.") values (".$sql2.")");
        if($this->PARAMS["ERR"]!="")return;
        $this->mysql->sql_execute("select LAST_INSERT_ID()");
        if($this->PARAMS["ERR"]!="")return;
        $this->PARAMS["id"]=$this->mysql->sql_result(0,0);
        //Сохраняем таблицу связи НкМ
        $this->save_other_tables($to_save);

    }


    function delete()
    {
        global $PARAMS;
        if(isset($PARAMS["ADMN_ONLY_READ"])&&$PARAMS["ADMN_ONLY_READ"]=="Y"){$this->PARAMS["ERR"]="ADMN_ONLY_READ";return;}
        $this->mysql->sql_execute("delete from ".$this->SETTINGS["table"]." where id=".$this->PARAMS["id"]);
    }

    function adapt()
    {
        foreach($this->vars as $k => $v)
        {
            $type=$v["save"]["type"];
            $check_err=$v["save"]["errors"]["0"];
            if($check_err!=0){
                $errt["types"]=$v["save"]["errors"][1];
                $errt["strs"]=$v["save"]["errors"][2];

            }
            switch ($type)
            {
                case "str":
                    $this->PARAMS["current"][$k]=AddSlashes($this->PARAMS["current"][$k]);
                    break;
                case "str1":
                    $this->PARAMS["current"][$k]=AddSlashes(htmlspecialchars(strip_tags(nl2br($this->PARAMS["current"][$k]))));
                    break;
                case "int":$this->PARAMS["current"][$k]=(int)$this->PARAMS["current"][$k];
            }
            if($check_err!=0)// есть ли проверки
            {
                $errs=explode("||",$errt["types"]);
                foreach($errs as $err)
                {
                    switch ($err)
                    {
                        case "null":{
                            if($this->PARAMS["current"][$k]=="" ||$this->PARAMS["current"][$k]==0 ||$this->PARAMS["current"][$k]==0.0)$this->PARAMS["ERR"][$k]="NOT_PRESENT";

                            break;
                        }
                        case "dubl":break;
                        case "mail":{
                            if($this->PARAMS["current"][$k]=="" || !eregi("^([._a-z0-9-]+[._a-z0-9-]*)@(([a-z0-9-]+\.)*([a-z0-9-]+)(\.[a-z]{2,3}))$",$this->PARAMS["current"][$k])){
                                $this->PARAMS["ERR"][$k]="NOT_EMAIL";
                                //return;
                            }
                            break;}
                        default: break;
                    }
                }
            }

        };
    }

    function set_save_values()
    {
        global $_REQUEST,$_FILES,$SETTINGS;
        foreach($this->vars as $name => $v)
        {
            $type=$v["edit"]["type"];
            switch($type)
            {
                default:
                    {
                        if(isset($_REQUEST[$name]))$this->PARAMS["current"][$name]=$_REQUEST[$name];else$this->PARAMS["current"][$name]="";
                        break;
                    }
            }

        }
    }

    function get_edit_string($attrs,$name)
    {
        global $SETTINGS;
        $attribs=$attrs["edit"];
        //$attribs=$attrs["edit"]['attribs'];
        $obligation=$attrs["save"]["obligation"];
        $type=$attrs["edit"]["type"];
        $comment=$attrs["edit"]["comment"];
        $key=$attrs["name"];
        $out_str="";
        switch ($type)
        {
            case "input":{
                if(isset($attribs[1])&& $attribs[1]!=0)$size="size=".$attribs[1];else $size="";
                if(isset($attribs[2])&& $attribs[2]!=0)$maxsize="maxlength=".$attribs[2];else $maxsize="";
                if(isset($attribs[3])&& $attribs[3]!=0)$width="style=\"width:".$attribs[3]."\"";else $width="";
                $out_str.="<input type='text' name='".$key."' value=\"".$name."\" ".$size." ".$maxsize." ".$width.">\n";
                break;
            }
            case "list":{
                $hash = $this->PARAMS["1forN"][$attribs["value_list"]];
                $out_str="\n<select name=\"".$key."\">\n";
                $out_str.="<option value='0'></option>\n";

                if(isset($name))$hashVal = $name;
                foreach($hash as $key0=>$val){
                    $sel=($key0==$hashVal)?" selected":"";
                    $out_str.="<option value='".$key0."'".$sel.">".$val['name']."</option>\n";
                }
                $out_str.="</select>";
                break;
            }
            case "radio":{

                $rows=$attribs["value_list"];//explode(",",$attribs["value_list"]);
                foreach ($rows as $value)
                {
                    $sel=($value==$name)?" checked":"";
                    $out_str.="<input type='radio' name='".$key."' value='".$value."' ".$sel.">".$value." \n";
                    //    	echo $key."=>".$value."<br>";
                }
                //$out_str.="<input type='text' name='".$key."' value=\"".$name."\" ".$size." ".$maxsize." ".$width.">";
                break;
            }

            case "pict":{
                $out_str.="<input type=\"File\" name=\"".$key."\">\n";
                if(isset($name)&&$name!=""){
                    $out_str.="<br><img src=\"".$name."\"><a href=\"javascript:void(0);\"
					onClick=\"window.open('".$SETTINGS["SERVER_URL"]."/admin/".$this->PARAMS["url_link"]."?act=del_pict&id=".$this->PARAMS["id"]."&pic=".$key."','help','width=650,height=50,,left=40,top=100, status=no,toolbar=no,menubar=no,resizable=yes,scrollbars=yes');\">
					".get_img("Del")."</a>";
                }
                break;
            }

            /*case "time":{
            ob_start();
            include ($SETTINGS["PATH_INC"]."/need/time_wotk_edit.html");
            $out_str=ob_get_contents();
            ob_clean();
            break;
            }*/
            /****case "%adress%":{
            ob_start();
            echo "<select name=\"city\" onchange=alist1.populate();alist2_1.populate();alist2_2.populate();alist2_3.populate();alist3.populate(); >";
            $hash = $this->PARAMS["1forN"]["_city"];
            $hashVal = $this->PARAMS["current"]["city"];
            foreach($hash as $key=>$val){?>
            <option value="<?echo $key;?>"<?echo ($key==$hashVal)?" selected":"";?>><?echo $val["name"];?></option>
            <?
            }?>
            </select><br>
            Регион:	<SELECT  name="region" style="width:350px"></SELECT><br>
            Метро :	<SELECT  name="metro_1" style="width:250px"></SELECT><br>
            Метро :	<SELECT  name="metro_2" style="width:250px"></SELECT><br>
            Метро :	<SELECT  name="metro_3" style="width:250px"></SELECT><br>
            Улица : 	<SELECT  name="street" style="width:300px"></SELECT>
            <?
            $out_str=ob_get_contents();
            ob_clean();

            //$this->get_c_adress();
            break;
            }*/
            case "text":{
                if(isset($attribs[1])&& $attribs[1]!=0)$cols=$attribs[1];else $cols=60;
                if(isset($attribs[2])&& $attribs[2]!=0)$rows=$attribs[2];else $rows=10;
                $out_str.="<textarea name=\"".$key."\" cols='".$cols."' rows='".$rows."'>".$name."</textarea> ";
                break;
            }
            case "check":{
                $on_line=10;$hashVal=array();
                $hash = $this->PARAMS["1forN"][$attribs["value_list"]];
                $i=0;
                if(isset($attribs['attribs']['on_line'])&&$attribs['attribs']['on_line']!=0)$on_line=$attribs['attribs']['on_line'];
                if(isset($name)&&is_array($name))$hashVal = $name;
                foreach($hash as $key0=>$val){
                    $sel=(in_array($key0,$hashVal))?" checked":"";
                    $out_str.="<INPUT type='checkbox' value='".$key0."'  name=\"".$key."[".$i."]\" ".$sel.">".$val['name']."</option>\n";
                    if(++$i%$on_line==0)$out_str.="<br>";
                }
                //$out_str.="</select>";
                break;
            }
            case "%file%":
                {
                    $out_str.="<input type=file name=\"".$key."\">";
                    list($fsize,$fname)=$this->file_get($this->PARAMS['id']);
                    if($fname!='') $out_str.='<br>'.$fname."(".$fsize.")";
                    break;
                }
        }
        if($comment!="")$out_str.="<br>(".$comment.")";
        if($obligation==1)$out_str.="*";
        if(isset($this->PARAMS["ERR"][$key]))$out_str.="<br>".str_replace("%ERR%",$this->ERRORS[$this->PARAMS["ERR"][$key]],$this->ERROR_STRING);

        return $out_str;
    }

    function error_echo()
    {
        global $SETTINGS;
        echo str_replace("ЕСТЬ ОШИБКИ",$this->ERRORS[$this->PARAMS['ERR']],$SETTINGS["ERROR_STRING"]);
    }
    function get_1forN()
    {
        foreach ($this->SETTINGS["get_arrays"] as $k=>$v){
            $res=$this->mysql->sql_execute("select id,name from ".$k." order by name");
            $outar=array();
            while($out=$this->mysql->sql_fetch_assoc()){
                $curar=array();
                $curar['id']=$out['id'];
                $curar['name']=$out['name'];
                $outar[$out['id']]=$curar;
            }
            $this->PARAMS['1forN'][$k]=$outar;
        }
        //        echo "<pre>";print_r($this->PARAMS['1forN']);
        if(method_exists($this,'get_1forN_child'))$this->get_1forN_child();
    }
    function getParent()
    {
        if(isset($this->PARAMS["showparams"]["parent"]))$p=$this->PARAMS["showparams"]["parent"];
        else $p=$this->PARAMS["parent"];
        if(count($p)>0)
        {
            $table=$p['table'];
            $string=$p['name'];
            $parent=$this->PARAMS['parent'];
            $link=str_replace('%p%',$parent,$p['link']);
            $where=str_replace('%p%',$parent,$p['where']);
            $column=$p['column'];
            $res=$this->mysql->sql_execute("select ".$column." from ".$table." where ".$where);
            $outar=array();
            while($out=$this->mysql->sql_fetch_assoc())
            $this->PARAMS['parent_link']=$out;
            $this->PARAMS['parent_link']['string']=$string;
            //$this->PARAMS['parent_link']['id']=$parent;
            $this->PARAMS['parent_link']['link']=$link;
        }
    }
    function get_parent_link()
    {
        $out_str="";
        if(isset($this->PARAMS['parent_link'])&&count($this->PARAMS['parent_link'])>0)
        {
            $p=$this->PARAMS['parent_link'];
            if(isset($p['name'])&&$p['name']!='')$name=$p['name'];
            else $name=$p['string'];
            $out_str.="<a href=\"".$p['link']."\">".$name."</a>";

        }
        return $out_str;

    }
    function file_get()
    {
        return array(0,'');

    }

    // Формирует выходную URL
    function out_url($url,$_REQUEST,$serverName="",$ServAddFlag=0){

        $Op=$this->outurl_array;
        $out_url=$url;
        $qf=0;
        foreach($Op as $val){
            if(isset($_REQUEST[$val])){if($qf==0){$razd="?";$qf=1;}else{$razd="&";}
            $out_url.=$razd.$val."=".$_REQUEST[$val];}
            if($ServAddFlag==1)$PARAMS["out_url"]=$serverName.$out_url;
        }

        return $out_url;
    }
    //Выставляет теже параметры что и Out_url но в список параметров хидден
    function set_urt4edit()
    {
        $out_func="";
        $Op=$this->outurl_array;
        foreach($Op as $val)
        {
            if((is_int($val)&&$val>0)||(is_string($val)&&$val!=''))
            $out_func.="<input type=hidden name=\"".$val."\" value=\"".$_REQUEST[$val]."\">\n";
        }
        if(isset($this->PARAMS['id'])&&$this->PARAMS["lock_id"]==0)$out_func.="<input type=hidden name=\"id\" value=\"".$this->PARAMS['id']."\">\n";
        return $out_func;
    }
}
?>