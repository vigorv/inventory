<?
debug("%1%Подключаем req/simple.class.php");
include_once PATH_REPOSIT."/template.class.php";

class simple extends template
{
    function simple($table_name,$info=1,$pager_type=2)
    {
        parent::template();
        $this->SETTINGS['pager_type']=$pager_type;
        $configs["table_name"]=$table_name;

        $vars=array();
            $var=array();
            $var["name"]="id";$var["string"]="id";
            $var["is_edit"]=0;
            $var["show"]["type"]="%id%";
            $var["is_save"]=0;
            $vars[$var["name"]]=$var;

            $var=array();
            $var["name"]="name";$var["string"]="Название";
            $vars[$var["name"]]=$var;

            if($info==1)
            {
                $var=array();
                $var["name"]='info';$var["string"]='Информация';
                $var["edit"]["type"]="text";
                $var["is_show"]=0;
            }
            $vars[$var["name"]]=$var;

        $this->init($vars,$configs);

    }


};?>
