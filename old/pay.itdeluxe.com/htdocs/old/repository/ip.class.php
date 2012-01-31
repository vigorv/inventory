<?
debug("%1%Подключаем req/ip.class.php");

class IP
{
	// Список локальных адресов в регулярных выражениях
	var $list=array(	'^10\.([0-9]|[0-9][0-9])\..*','^192\.168\..*','^127\.0\..*','217.70.100.242');
	//var $list=array(	'^10\.([0-9]|[0-9][0-9])\..*','^192\.168\..*','217.70.100.242');
	var $listnr=array(
										 '10.1.[1-26].*'//Снегири
										,'10.2.[1-26].*'
										,'10.3.[1-26].*'
										,'10.4.[1-26].*'//Родники
										,'10.5.[1-26].*'
										,'10.33.0.*'//Мжк
										,'10.34.0.*'//Волочаевский
										,'10.33.1.*'//New
										,'10.33.2.*'
										,'192.168.*.*'
										,'127.0.*.*'
										,'217.70.100.242'
									);

//Функция проверяющая является ли клиент локальным пользователем
	function is_localip()
	{
		$ip=$this->get_ip();
		for($i=0;$i<count($this->list); $i++) if (eregi($this->list[$i],$ip)) return TRUE;
		return FALSE;
	}
	
	function get_ip()
	{
				//echo $_SERVER['REMOTE_ADDR']."<br>";
				if(isset($_SERVER['REMOTE_ADDR']))return $_SERVER['REMOTE_ADDR'];else return "";
			    //return '10.5.5.2';

	}

	function get_referer()
	{
	    global $SETTINGS;
   if(isset($_SERVER['HTTP_REFERER'])&&$_SERVER['HTTP_REFERER']!="")
   {
			/*$ref_attrs=parse_url($_SERVER['HTTP_REFERER']);
			$ref_hostname = gethostbyaddr($ref_attrs['host']);
			if($ref_hostname=='localhost')return true; */
			//echo $_SERVER['HTTP_REFERER']." ".."<br>";
			if(strpos($_SERVER['HTTP_REFERER'],$_SERVER['SERVER_NAME'])===false)return false;else return true;
			
   }
   return false;
	}

  function ipInRange($range="")
  {
  	$ip=$this->get_ip();
  	if($range=="")$range=$this->listnr;
  	if(is_array($range))
  	{
			for($i=0;$i<count($range); $i++)
			{
				if($this->ipInRAnge_check($range[$i],$ip))return TRUE;
			}
  	}
  	elseif(is_string($range))
  	{
			if($this->ipInRAnge_check($range,$ip))return TRUE;
  	}
  	return FALSE;
  }

		 function ipInRange_check($cur_range,$cur_ip)
 		 {
 		 	 $ip=explode(".",$cur_ip);
			 $range=explode(".",$cur_range);
			 $flag=0;
			 for($i=0;$i < 4;$i++)
			 {
			 	$str_range=str_replace(array('[',']'),'',$range[$i]);
			 	if($str_range=='*'){$flag++;continue;}
			 	$str_ranges=explode("-",$str_range);
			 	if(count($str_ranges)==1)
			 	{if($str_ranges[0]==$ip[$i])$flag++;
			 			continue;
			 	}
			 	
			 	if(count($str_ranges)==2){
			 		if( $ip[$i] >= $str_ranges[0] && $ip[$i] <= $str_ranges[1]){$flag++;continue;}
			 	 }
			 }
			 if($flag==4)return TRUE;
			 return FALSE;
 		 }
 
};

?>