<?
debug("%1%Подключаем req/cost.class.php");
include_once PATH_REPOSIT."/ip.class.php";

class cost
{
    // Список локальных адресов в регулярных выражениях
/*
10.1.1.4
10.1.9.3
10.2.12.3
10.2.13.10
10.2.16.3
10.3.3.4
10.3.18.4
10.3.18.5
10.5.9.4
10.5.9.7

*/
    var $listnr=array(
                 array('ip'=>'10.1.1.4',"cost"=>"7")
                 ,array('ip'=>'10.1.9.3',"cost"=>"7")
                 ,array('ip'=>'10.2.12.3',"cost"=>"7")
                 ,array('ip'=>'10.2.13.10',"cost"=>"7")
                 ,array('ip'=>'10.2.16.3',"cost"=>"7")
                 ,array('ip'=>'10.3.3.4',"cost"=>"7")
                 ,array('ip'=>'10.3.18.4',"cost"=>"7")
                 ,array('ip'=>'10.3.18.5',"cost"=>"7")
                 ,array('ip'=>'10.5.9.4',"cost"=>"7")
                 ,array('ip'=>'10.5.9.7',"cost"=>"7")

                 
                 ,array('ip'=>'10.1.[1-26].*',"cost"=>"7"/*Снегири*/)
                 ,array('ip'=>'10.2.[1-26].*',"cost"=>"7")
                 ,array('ip'=>'10.3.[1-26].*',"cost"=>"7")
                 
                 ,array('ip'=>'10.4.[1-26].*',"cost"=>"7"/*Родники*/)
                 ,array('ip'=>'10.5.[1-26].*',"cost"=>"7")

                 ,array('ip'=>'10.33.*.*',"cost"=>"3"/*Мжк*/)
                 ,array('ip'=>'10.34.*.*',"cost"=>"3"/*Волочаевский*/)

                 ,array('ip'=>'192.168.*.*',"cost"=>"7"/*LOCAL*/)
                 ,array('ip'=>'127.0.[0-1].*',"cost"=>"0.02"/*LOCAL*/)
                 ,array('ip'=>'217.70.100.242',"cost"=>"0.01"/*LOCAL*/)
                 ,array('ip'=>'*.*.*.*',"cost"=>"700"/*LOCAL*/)
                 );
    
 var $listnrmb=array(
                 array('ip'=>'10.1.[1-26].*',"cost"=>"0.01"/*Снегири*/)
                 ,array('ip'=>'10.2.[1-26].*',"cost"=>"0.01")
                 ,array('ip'=>'10.3.[1-26].*',"cost"=>"0.01")
                 
                 ,array('ip'=>'10.4.[1-26].*',"cost"=>"0.01"/*Родники*/)
                 ,array('ip'=>'10.5.[1-26].*',"cost"=>"0.01")

                 ,array('ip'=>'10.33.*.*',"cost"=>"0.01"/*Мжк*/)
                 ,array('ip'=>'10.34.*.*',"cost"=>"0.01"/*Волочаевский*/)

                 ,array('ip'=>'192.168.*.*',"cost"=>"0.01"/*LOCAL*/)
                 ,array('ip'=>'127.0.[0-1].*',"cost"=>"0.01"/*LOCAL*/)
                 ,array('ip'=>'217.70.100.242',"cost"=>"0.001"/*LOCAL*/)
                 ,array('ip'=>'*.*.*.*',"cost"=>"0.30"/*LOCAL*/)
                 );
                 var $currencys=array('RUR'=>1,"\$"=>26.7);
    var $def_cur="RUR";
  var $cost=0;

  function cost($from_mb=0)
  {
      if($from_mb==0)
      {
      $ip= new IP();
      for($i=0;$i<count($this->listnr);$i++)
      {    
          $range=$this->listnr[$i]['ip'];
          if($ip->ipInRange($range))
          {
              $this->cost=$this->listnr[$i]['cost']; 
              break;
          }
      }
      }
      else
      {
      $ip= new IP();
      for($i=0;$i<count($this->listnrmb);$i++)
      {    
          $range=$this->listnrmb[$i]['ip'];
          if($ip->ipInRange($range))
          {
              $this->cost=$this->listnrmb[$i]['cost']; 
              break;
          }
      }
      }
  }
  
  function get_cost($cost=0,$curr="RUR")
  {
      return sprintf("%.2f",$this->cost/$this->currencys[$curr]);
   }

  function get_cost2($cost=0,$curr="RUR")
  {
      return sprintf("%.2f",$cost/$this->currencys[$curr]);
     }
  function get_cost_from_size($size=0,$curr="RUR")
  {
      return sprintf("%.2f",($this->cost*$size)/$this->currencys[$curr]);
   }
};

?>
