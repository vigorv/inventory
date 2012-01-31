<?
debug("%1%Подключаем req/payment_out.class.php");

	include_once PATH_API."/template.class.php"; 
class payment_out extends template
{
	var $payment=array();
function payment_out()
{
	 parent::template();
	 $vars=array();
/* Пример создания класса 
	 		$var=array();
	 		$var["name"]="id";//название переменной
	 		$var["string"]="id";//отображаемое имя
	 		$var["is_show"]=TRUE;// показывать в списке
	 		$var["is_edit"]=FALSE;// разрешить редактирование
	 		$var["is_get"]=TRUE;// отобржать в одиночном режиме
	 		$var["is_save"]=TRUE;// отобржать в одиночном режиме
	 		$var["show"][type]="";//Тип отображения
	 													 					Пусто - просто строка
	 													 					%act% - картинка астивен/нет
	 													 					любая строка, подстановка из массивас этим названием 
	 		$var["edit"][type]="input";//
	 																	"textarea"
																		 ,"input"	
																			,"hidden"
																			,"select"
																			,"chekbox"

			$var["edit"]["value_list"]=>array("Y","N")- список значений

			$var["save"][obligation]=0 //обятельное поле
			$var["save"][if_null]=0 //обновлять при пустом поле
			$var["save"][type]= //	тип бывает str - строка
															str1- убрать все теги html
															int - целове
															other - без преобразований

			$var["save"][errors]=array(0) //не проверять на ошибки
			                    array(1,"dubl||null","LOGIN_DUBL||NOT_LOGIN")

*/
	 		$var=array();
	 		$var["name"]="id";$var["string"]="id";
	 		$var["is_edit"]=0;
	 		$var["show"]["type"]="%id%";
	 		$var["is_save"]=0;
	 	$vars[$var["name"]]=$var;
	 		$var=array();
	 		$var["name"]="IP";$var["string"]="IP";
	 		$var["save"]["type"]="float";
	 	$vars[$var["name"]]=$var;

	 		$var=array();
	 		$var["name"]="file_id";$var["string"]="id Файла";
	 	$vars[$var["name"]]=$var;

	 		$var=array();
	 		$var["name"]="hash";$var["string"]="hash";
	 	$vars[$var["name"]]=$var;

	 	$configs=array();
	 	$configs["table_name"]="payment";
	 	
	 	$this->init($vars,$configs);
	}
};?>