<?
debug("%1%���������� req/payment_out.class.php");

	include_once PATH_API."/template.class.php"; 
class payment_out extends template
{
	var $payment=array();
function payment_out()
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
	 		$var["name"]="IP";$var["string"]="IP";
	 		$var["save"]["type"]="float";
	 	$vars[$var["name"]]=$var;

	 		$var=array();
	 		$var["name"]="file_id";$var["string"]="id �����";
	 	$vars[$var["name"]]=$var;

	 		$var=array();
	 		$var["name"]="hash";$var["string"]="hash";
	 	$vars[$var["name"]]=$var;

	 	$configs=array();
	 	$configs["table_name"]="payment";
	 	
	 	$this->init($vars,$configs);
	}
};?>