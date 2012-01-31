<?
debug("%1%Подключаем req/pager.class.php");

/**
 * Класс пагинации
 * @version 0.93a 
 * @last mod.date 16.11.2006 11:30
 * Потдерживавет 4 типа пагинации
 * 
 */
class Pager
{
    /**Общее число объектов*/
    var $max;
    /**число объектов на страницу*/
    var $num;
    /**url для ссылки*/
    var $url;
    /**строка пагинации*/
    var $pager;
    /**левый символ огнаничения  (если есть)*/
    var $left="";
    /**правый символ огнаничения  (если есть)*/
    var $right="";
    /**разделитель ? или &*/
    var $razd;
    /**символ пагинации*/
    var $pag_str;
    /**текущая страница*/
    var $cur_page;

    //только для типа D
    /** Количество эл-тов слева*/
    var $cols_left;
    /**Количество эл-тов справа*/
    var $cols_right;

    /**
     * Создает обьект класса
     * @param $m общее_число_бьектов
     * @param $n количесвто_настраницу
     * @param $cp текущая_страница
     * @param $u url
     * @param $pag_str символ_пагинации
     * @param $cols_left кол-во_эл-тов_слева
     * @param $cols_right кол-во_эл-тов_справа
     */
    function Pager($m,$n,$cp,$u,$pag_str="f",$cols_left=2,$cols_right=2,$pager_left="&nbsp;",$pager_right="&nbsp;")
    {
        $this->cur_page=$cp;
        $this->pag_str=$pag_str;
        $this->pager="";
        $this->max=(int)$m;
        $this->num=(int)$n;
        $this->url=strip_tags($u);
        $this->razd=$this->get_razd($this->url);
        $this->left=$pager_left;
        $this->right=$pager_right;
        $this->cols_left=$cols_left;
        $this->cols_right=$cols_right;

    }

    //пагинация вида a b
    function CreateTypeB()
    {
        if($this->num==0) return;
        $count=($this->max/$this->num);
        if($count==0) return;
        $count=ceil($count);
        for($i=0;$i<$count;$i++)
        {
            $j=$i+1;
            $this->pager.=$this->left."<a href=\"".$this->url.$this->razd.$this->pag_str."=".$i."\">".$j."</a>".$this->right;
        }
    }

    //пагинация вида [a-b]
    function CreateTypeA()
    {
        if($this->num==0) return;
        $count=($this->max/$this->num);
        if($count==0) return;
        $count=ceil($count);
        for($i=0;$i<$count;$i++)
        {
            if(($this->num+$this->num*$i)>=$this->max)
            {
                $to=$this->max;
                if($i*$this->num+1!=$to)
                $str=$this->left.($this->num*$i+1)."-".$to.$this->right;
                else $str=$this->left.$to.$this->right;
            }
            else
            {
                $to=$this->num*$i+$this->num;
                $str=$this->left.($this->num*$i+1)."-".$to.$this->right;
            }
            ///if(isset($_REQUEST[$this->pag_str]))$cur_page=(int)$_REQUEST[$this->pag_str];else$cur_page=0;
            if($this->cur_page!=$i)
            $this->pager.="<a href=\"".$this->url.$this->razd.$this->pag_str."=".$i."\">".$str."</a>".$this->right;
            else $this->pager.=$this->left.$str.$this->right;
        }
    }


    function Create($PAGER_TYPE=0)
    {
        switch($PAGER_TYPE)
        {
            case 0:
                {
                    $this->CreateTypeA();
                    break;
                }
            case 1:
                {
                    $this->CreateTypeB();
                    break;
                }
            case 2:
                {
                    $this->CreateTypeC();
                    break;
                }
            case 3:
                {
                    $this->CreateTypeD();
                    break;
                }

        }
    }

    /**Возвращает строку пагинации */
    function Get(){return $this->pager;}


    //пагинация вида a b
    function CreateTypeD()
    {
        if($this->num==0) return;
        $count=($this->max/$this->num);
        $count=ceil($count);
        if($count==0) return;

        // echo $this->cols_left."-";
        // echo $this->cols_right." ";

        if($this->cols_left>$this->cur_page){
            $this->cols_right+=($this->cols_left-$this->cur_page)+1;
            $this->cols_left=$this->cur_page;
        }

        // echo $this->cols_left."-";
        // echo $this->cols_right." ";

        if($this->cols_right>($count-$this->cur_page-1))
        {
            $right=$this->cols_right;
            $this->cols_right=($count-$this->cur_page-1);
            $this->cols_left+=$right-$this->cols_right+1;
        }

        //echo $this->cols_left."-";
        //echo $this->cols_right." ";

        $this->pager.=$this->get_extreme(0);
        $kl=$this->cur_page-$this->cols_left;$kr=$this->cols_right+$this->cur_page;
        for($i=1;$i<$count-1;$i++)
        {
            if($i==$kl)$this->pager.="...";
            if($i>=$kl&&$i<=$kr)$this->pager.=$this->get_extreme($i);
            if($i==$kr)$this->pager.="...";

        }
        $this->pager.=$this->get_extreme($count-1);

    }
    //Функция для типа D
    function get_extreme($i=0)
    {
        $j=$i+1;
        if($i==$this->cur_page)
        $pager=$this->left.$j.$this->right;
        else
        $pager="&nbsp;<a href=\"".$this->url.$this->razd.$this->pag_str."=".$i."\">".$j."</a>&nbsp;";
        return $pager;
    }


    //постраничный вывод с умножением коэфииента
    function CreateTypeC(){
        //постраничный вывод - внутренняя функция

        if($this->num==0) return;

        $StLimit=(int)$this->cur_page*$this->num;
        $page_url=$this->url;
        $razd=$this->razd;
        $count_on_page=$this->num;
        $count=$this->max;

        if(($StLimit+$this->num)>$this->max)
        $part_line=$this->left.($StLimit+1)."-$count".$this->right;
        else
        $part_line=$this->left.($StLimit+1)."-".($StLimit+$this->num).$this->right;

        $part_line=$this->mul_pager($StLimit,$this->max,$this->num,true,false,$page_url,$razd,$this->num).$part_line;
        $part_line.=$this->mul_pager($StLimit,$this->max,$this->num,false,false,$page_url,$razd,$this->num);
        $dimiler=100;
        while($dimiler/$count_on_page<=1)$dimiler*=10;
        while($dimiler<$count){
            $part_line=$this->mul_pager($StLimit,$this->max,$dimiler,true,false,$page_url,$razd,$this->num).$part_line;
            $part_line.=$this->mul_pager($StLimit,$this->max,$dimiler,false,false,$page_url,$razd,$this->num);
            $dimiler*=10;
        }
        $part_line=$this->mul_pager($StLimit,$this->max,$dimiler,true,true,$page_url,$razd,$this->num).$part_line;
        $part_line.=$this->mul_pager($StLimit,$this->max,$dimiler,false,true,$page_url,$razd,$this->num);
        $this->pager=$part_line;
        //return $part_line;
    }
    function mul_pager($StLimit,$count,$dimiler,$to,$first,$page_url,$razd,$count_on_page){
        $mn=10;$part_line="";
        if($dimiler==$count_on_page){
            while($mn/$dimiler<=1)$mn*=10;
            $mn=$mn/$count_on_page;
        }
        if($to){//назад
            if($first){
                $start=0;
            }
            else{
                //ближайшее
                $start=$StLimit-$StLimit%($dimiler*$mn)-$StLimit%$dimiler;
                if($start<0)$start=0;
            }
            $stop=$StLimit-$StLimit%$dimiler;
        }
        else{//вперед
            $start=$StLimit+$dimiler-$StLimit%$dimiler;
            if($first){
                $stop=$count;
            }
            else{
                $stop=$StLimit+$dimiler*$mn-$StLimit%($dimiler*$mn);
                if($stop>$count)$stop=$count;
            }
        }
        for($i=$start;$i<$stop;$i+=$dimiler){
            $min=$i+1;
            $max=$min+$dimiler-1;
            if($max>$count)$max=$count;
            $part_line.="<a href=\"".$page_url.$razd.$this->pag_str."=".($i/$count_on_page)."\">$min-$max</a>".$this->right;
        }
        return $part_line;
    }

    function get_razd($url) {if (strpos($url,'?') === FALSE) return '?';else return'&';}


}

?>
