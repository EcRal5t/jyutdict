<?php
interface printHTML{
	#輸出框架之類 HTML Print 例如 wanshyuResult div
	#傳入的bool 用來判別開始和結束標誌 用於起始和收尾
	public function printFramework(bool $args) :void;  
	public function printContentList()  :void;  
	#对于多音字毋使成舊框架輸出 輸出數據列表即可
}
interface updateData{
	public function updateData(CharaResultIterator  $data);
}
class ViewWanshyuResult{
	public static final function printFramework(bool $isBegin) : void
	{
		switch($isBegin)
		{
			case true:
				?><div id="wanshyuResult"><?php
				break;
			case false:
				?></div><?php
				break;
		}
	} 
	#突然見到 WanshyuResultForm DIV /DIV 加咗呢個入來
	public static function printContentListFramework(bool $isBegin)  :void
	{
		switch($isBegin)
		{
			case true:
				?><div id="wanshyuResultForm" class=""><?php
				break;
			case false:
				?></div><?php
				break;
		}
	}
}
class ViewKuangyon implements printHTML,updateData{

	private $isGet	=	false;
	
	private $data	=	null;

	public function __construct(bool $isGet)
	{
		$this->isGet	=	$isGet;
		
	}
	public final function updateData(CharaResultIterator $dataKuangyon)
	{
		$this->data	=	$dataKuangyon;
	}
	public final function printFramework(bool $isBegin) : void
	{
		switch($isBegin)
		{
			case true:
				?><div class="general-bg-deeper" id="charaHead">
						<div id="charaHeadSqu"><?PHP echo $this->data->getChara(); ?></div><?php
				break;
			case false:
				?></div><?php
				break;
		}
			 
	}

	public final function printContentList()  :void  #对于多音字毋使成舊框架輸出 輸出數據列表即可
	{
		?><div id="oldPronounce">
				<span><?php echo($this->data->getInitial() . $this->data->getRimeclass() . 
												$this->data->getRime() . $this->data->getDivision() . $this->data->getRounding() . 
												$this->data->getTone() . $this->data->getTransliteration());?>
				</span>
			</div><?php
	}

}

class ViewFanwan implements printHTML,updateData{

	private $isGet	=	false;

	private $data	=	null;

	public function __construct(bool $isGet)
	{
		$this->isGet	=	$isGet;
	}
	public final function updateData(CharaResultIterator $fanwan)
	{
		$this->data	=	$fanwan;
	}
	public function printFramework(bool $isBegin) :void  
	{
		switch ($isBegin) {
			case true:
				?>			
				<table class="general-form annex-form general-bg-deeper">
				<?php
				break;
			case false:
				?>
				</table>
				<div style="margin-top: 13px;"></div>
				<?php
				break;
		}
	}
	public  function printContentList()  :void
	{
		switch ($this->isGet) {
			case true:
				$jyutping = new Jyutping();
				?>
					<?PHP $jyutping->set($this->data->getInitial(), $this->data->getNuclei(), $this->data->getCoda(), $this->data->getTone());?>
					<tr>
						<td class="column2-20 font-22">分韻</td>
						<td class="column8-20">韻部 - 小韻</td>
						<td class="column6-20">聲 - 韻 - 調</td>
						<td rowspan="2" class="alphabet">
						<?PHP $jyutping->printWithColor("red", "green", "green"); ?>
						</td>
					</tr>
					<tr>
						<td><?php echo $this->data->getName(); ?></td>
						<td><?PHP echo $this->data->getYunbu() . "-" . $this->data->getSiuwan(); ?></td>
						<td><?PHP echo $this->data->getInitial_ch().'-'.$this->data->getFinal_ch().'-'.$this->data->getTone_ch(); ?></td>
					</tr>
					<tr>
						<td><?php echo $this->data->getName(); ?></td>
						<td colspan="4"><?PHP echo $this->data->getMeaning(); ?></td>
				</tr>
				<?PHP
				break;
			case false:
				?>
				<span style='font-size: 20px;'>分韻冇見有</span>
				<div style="margin-top: 13px;"></div>
				<?php
				break;
		}	
	}
}
class ViewJingwaa implements printHTML{

	private $isGet	=	false;

	private $data	=	null;

	private $lastOrder	=	"0";

	public function __construct(bool $isGet)
	{
		$this->isGet	=	$isGet;
	}
	public final function updateData(CharaResultIterator $jingwaa,string $lastOrder)
	{
		$this->data	=	$jingwaa;
		$this->lastOrder	=	$lastOrder;
	}

	public  final function printFramework(bool $isBegin): void
	{
		switch ($isBegin) {
			case true:
				?>
				<table class="annex-form general-form general-bg-deeper">
				<tr>
					<td class="column2-20 font-22">英華</td>
					<td class="column4-20">葉碼</td>
					<td class="column5-20">筆畫</td>
					<td class="column5-20">原標音</td>
					<td></td>
				</tr>
				<?php
				break;
			
			case false:
				?>
				</table>
				<?php
				break;
		}
	}

	public  final function printContentList(): void
	{
		switch ($this->isGet) {
			case true:
					$jyutping = new Jyutping();
					$jyutping->set($this->data->getInitial(),$this->data->getNuclei(),$this->data->getCoda(),$this->data->getTone());
					?>
					<tr>
						<td>英華</td>
						<td><?PHP echo $this->data->getPage(); ?></td>
						<td>
							<?PHP echo $this->data->getRadical_stroke() . '(' . $this->data->getRadical() . ')+' . $this->data->getExtra_stroke(); ?>
						</td>
						<td class="
						<?PHP echo ($this->lastOrder == $this->data->getOrder() ? "hl-font-gray":"") ?> alphabet">
						<?PHP echo $this->data->getPronunciation() ?>
						</td>
						<td class="alphabet"><?PHP $jyutping->printWithColor("red", "green", "green"); ?></td>
					</tr>
				<?php
				break;
			
			case false:
				?>
				<span style='font-size: 20px;'>英華冇見有</span>
				<div style="margin-top: 13px;"></div>
				<?php
				break;
		}

	}

}

class ViewArea implements updateData{
	
	private $isGet 	=	false;

	private $data	=	null;
	
	public function __construct(bool $isGet)
	{
		$this->isGet	=	$isGet;
	}
	public final function updateData(CharaResultIterator $area)
	{
		$this->data	=	$area;
	}
	public static final function printAreaFramework(bool $isBegin)
	{
		switch ($isBegin) {
			case true:
				?>
				<div id="regionalResult">
				<?php
				break;
			
			case false:
				?>	
				</div>
				<?php
				break;
		}
	}
	public final function printTableFramework(bool $isBegin): void
	{
		switch ($isBegin) {
			case true:
				?>
				<div class="general-bg-deeper" id="regionalResultForm">
					<table id="regionalResultTable" class="general-form annex-form">
						<tr>
							<td class="font-22" style='height: 36px;' colspan='5'><?PHP echo $this->data->getChara(); ?></td>
						</tr>
				<?php
				break;
			
			case false:
				?>
						</table>
				</div>
				<?php
				break;
		}
	}

	public final function printContentList(): void
	{
		$jyutping	=	new Jyutping();
		$jyutping->set(
				$this->data->getInitial(),
				$this->data->getNuclei(),
				$this->data->getCoda(),
				$this->data->getTone()
				);
		$jyutping->setIpa($this->data->getIPA());  
		?>
		<tr>
			<td class="column4-20 min-width60 "><?PHP echo $this->data->getDivision(); ?></td>
			<td class="column3-20 min-width45 <?PHP if($this->data->getDistrict() != "") { echo 'tips'; } ?> ">
				<?PHP
				echo $this->data->getCity();
				if ($this->data->getDistrict() != "") 
				{ 
					echo "<span class='hl-font-grayish font-0p9em tipsMain' style='width: 50px;'>"
					 		. $this->data->getDistrict() .
					 		 "</span>"; }
				?>
			</td>
			<td class="alphabet">
				<?PHP $jyutping->printWithColor(); ?>
			</td>
			<td class="column4-20 min-width45">
				<?PHP $jyutping->printIpaWithColor(); ?>
			</td>
			<?PHP
			if (mb_strlen($this->data->getNote(),'UTF8') > 5) {
				echo "<td class='tips font-0p9em'>" . mb_substr($this->data->getNote(), 0, 4, 'utf8')."…";
				echo "<span class='tipsMain'>" . $this->data->getNote() . "</span></td>";
			} else {
				echo "<td class='font-0p9em'>" . $this->data->getNote() . "</td>";
			}#end if (mb_strlen($note,'UTF8') > 5)
			?>
		</tr>
	<?PHP
	}
}

class ViewMap implements updateData
{
	
	private $data	=	null;
	
	private $mapName	=	"";

	private $pronunciation = "";

	private $fontcolor = null;

	public function __construct(string $mapName)
	{
		$this->mapName	=	$mapName;
	}
	
	public final function updateData(\CharaResultIterator $area)
	{
		$this->data	=	$area;
		$this->setFontcolorArr($area->getColor());
		$this->pronunciation = "";
		$this->setPronunciation($area);
	}
	
	private final function setPronunciation(DataArea $area)
	{
			for(;$area->hasNext();$area->next())
			{
				$this->pronunciation .= $area->getInitial() . $area->getNuclei() . 
				$area->getCoda() . $area->getTone() . '<br />'; 
			}
	}

	private final function setFontcolorArr(string $color)
	{
		$fontcolorArr = str_split(hex2bin(substr($color,1)));
		if ((ord($fontcolorArr[0])+ord($fontcolorArr[1])+ord($fontcolorArr[2])) < 384) {
			$this->fontcolor = "#F4F4EE"; //背景很暗則用亮色字體
		} else {
			$this->fontcolor = "#2F2F2F"; //背景足夠亮用暗色字體
		}
	}

	public final static function printDependency()
	{
		?>
<!--leaflet CSS&JS Dependency--><link rel="stylesheet"href="https://unpkg.com/leaflet@1.5.1/dist/leaflet.css"integrity="sha512-xwE/Az9zrjBIphAcBb3F6JVqxf46+CDLwfLMHloNu6KEQCAWi6HcDUbeOfBIptF7tcCzusKFjFw2yuvEpDL9wQ=="crossorigin=""/><!--Make sure you put this AFTER Leaflet's CSS--><script src="https://unpkg.com/leaflet@1.5.1/dist/leaflet.js"integrity="sha512-GffPMF3RvMeYyc1LWMHtK8EbPv0iNZ8/oTtHPx9/cc2ILxQ+u905qIwdpULaqDkyBKgOaB57QTMg7ztg8Jm2Og=="crossorigin=""></script><script>
		<script>
		var initQueue=Array();let excuter=null;
		</script>
		<?php
	}

	public  final function printMapDiv()
	{
		?>
		<div class="general-bg-deeper"
		id="<?php echo $this->mapName; ?>"></div>
		<?php
	}

	public final function initMap()
	{
		?>
		<script>
		eval(function(p,a,c,k,e,d){e=function(c){return(c<a?"":e(parseInt(c/a)))+((c=c%a)>35?String.fromCharCode(c+29):c.toString(36))};if(!''.replace(/^/,String)){while(c--)d[e(c)]=k[c]||e(c);k=[function(e){return d[e]}];e=function(){return'\\w+'};c=1};while(c--)if(k[c])p=p.replace(new RegExp('\\b'+e(c)+'\\b','g'),k[c]);return p}('2={3:0(){},1:6(0(){}),5:4}',7,7,'function|MakerPlacer|excuter|mapIniter|null|currentMap|Array'.split('|'),0,{}))excuter.mapIniter=function(){eval(function(p,a,c,k,e,d){e=function(c){return(c<a?"":e(parseInt(c/a)))+((c=c%a)>35?String.fromCharCode(c+29):c.toString(36))};if(!''.replace(/^/,String)){while(c--)d[e(c)]=k[c]||e(c);k=[function(e){return d[e]}];e=function(){return'\\w+'};c=1};while(c--)if(k[c])p=p.replace(new RegExp('\\b'+e(c)+'\\b','g'),k[c]);return p}('1 3=0.2(\'7.6\',\'8.5\');1 4=0.2(\'9.d\',\'e.c\');1 a=0.b(3,4);',15,15,'L|var|latLng|leftup|rightdown|819028|938400|26|105|17|bounds|latLngBounds|883134|119569|115'.split('|'),0,{}))var map=L.map('<?php echo $this->mapName; ?>',{maxBounds:bounds}).setView([22.6,111],7);eval(function(p,a,c,k,e,d){e=function(c){return(c<a?"":e(parseInt(c/a)))+((c=c%a)>35?String.fromCharCode(c+29):c.toString(36))};if(!''.replace(/^/,String)){while(c--)d[e(c)]=k[c]||e(c);k=[function(e){return d[e]}];e=function(){return'\\w+'};c=1};while(c--)if(k[c])p=p.replace(new RegExp('\\b'+e(c)+'\\b','g'),k[c]);return p}('h.e(\'f://c.d.i/j/g/{1}/a/b/{z}/{x}/{y}@5?4={0}\',{3:7,1:\'9/8\',0:\'B.u.w-v\',t:\'©A | C n k l\',o:6,r:s}).p(2);q.m=2;',39,39,'accessToken|id|map|tms|access_token|2x||false|cjzk0fd9k0esv1csaqrxhiemj|zenam|tiles|256|api|mapbox|tileLayer|https|v1|L|com|styles|Lingnaam|Jyutjam|currentMap|By|minZoom|addTo|excuter|maxZoom|12|attribution|eyJ1IjoiemVuYW0iLCJhIjoiY2p4bjh5MjFxMGM4aTNobGF0dXNoejlseiJ9|_0|BPrObTer||||Mapbox|pk|Performed'.split('|'),0,{}))}initQueue.push(excuter);
		</script>
		<?php
	}

	public final function addMaker()
	{
		?>
		<script>
		excuter=initQueue.pop();excuter.MakerPlacer.push(function(){L.circle([<?php echo $this->data->getLatitude();?>,<?php echo $this->data->getLongitude();?>],{color:'<?php echo $this->data->getColor(); ?>',fillColor:'<?php echo $this->data->getColor(); ?>',fillOpacity:0.5,radius:1000}).addTo(excuter.currentMap);L.marker([<?php echo $this->data->getLatitude();?>,<?php echo $this->data->getLongitude();?>],{icon:L.divIcon({className:'divIconDefault',html:"<div class='locale-label' "+"style='background-color: <?php echo $this->data->getColor(); ?>;"+"opacity: 0.85;'>"+"<div class='label-triangle'"+"style="+"'border-bottom-color: <?php echo $this->data->getColor(); ?>'></div>"+"<span style='color:<?php echo $this->fontcolor; ?>;'><?php echo $this->pronunciation; ?></span>"+"</div>",iconSize:[60,]})}).addTo(excuter.currentMap)});initQueue.push(excuter);
		</script>
		<?php
	}

	public final static function showMap()
	{
		?>
		<script>
		eval(function(p,a,c,k,e,d){e=function(c){return(c<a?"":e(parseInt(c/a)))+((c=c%a)>35?String.fromCharCode(c+29):c.toString(36))};if(!''.replace(/^/,String)){while(c--)d[e(c)]=k[c]||e(c);k=[function(e){return d[e]}];e=function(){return'\\w+'};c=1;};while(c--)if(k[c])p=p.replace(new RegExp('\\b'+e(c)+'\\b','g'),k[c]);return p;}('8=j(){a(;b["\\7\\2\\6\\i\\3\\c"]>0;){9 5=b["\\k\\c\\d\\h\\3"]();5["\\g\\1\\m\\s\\6\\d\\3\\2\\4"]();a(9 e n 5["\\p\\1\\o\\2\\4\\q\\7\\1\\t\\2\\4"]){e()}}}r["\\f\\6\\7\\f\\1\\l"]=8();',30,30,'|x61|x65|x74|x72|exc|x6e|x6c|onLoad|let|for|initQueue|x68|x69|func|x6f|x6d|x66|x67|function|x73|x64|x70|of|x6b|x4d|x50|Window|x49|x63'.split('|'),0,{}))
		</script>
		<?php
	}
}