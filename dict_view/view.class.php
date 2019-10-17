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
			<td class="column3-20 min-width45 <?PHP if($this->data->getDivision() != "") { echo 'tips'; } ?> ">
				<?PHP
				echo $this->data->getCity();
				if ($this->data->getnote() != "") 
				{ 
					echo "<span class='hl-font-grayish font-0p9em tipsMain' style='width: 50px;'>"
					 		. $this->data->getDivision() .
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
		<!-- leaflet CSS & JS Dependency -->
		<link rel="stylesheet" href="https://unpkg.com/leaflet@1.5.1/dist/leaflet.css"
		integrity="sha512-xwE/Az9zrjBIphAcBb3F6JVqxf46+CDLwfLMHloNu6KEQCAWi6HcDUbeOfBIptF7tcCzusKFjFw2yuvEpDL9wQ=="
		crossorigin=""/>
		<!-- Make sure you put this AFTER Leaflet's CSS -->
		<script src="https://unpkg.com/leaflet@1.5.1/dist/leaflet.js"
		integrity="sha512-GffPMF3RvMeYyc1LWMHtK8EbPv0iNZ8/oTtHPx9/cc2ILxQ+u905qIwdpULaqDkyBKgOaB57QTMg7ztg8Jm2Og=="
		crossorigin=""></script>
		<script>

			var initQueue	=	Array();

			let excuter	=	null;

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
			excuter	=	{
				mapIniter	: function(){},
				MakerPlacer	:	Array(function(){}),
				currentMap : null
			}

			excuter.mapIniter = function(){
				
				var leftup = L.latLng('26.938400','105.819028'); 
				
				var rightdown = L.latLng('17.119569','115.883134');
				
				var bounds = L.latLngBounds(leftup,rightdown);
				
				var map = L.map('<?php echo $this->mapName; ?>',{
						maxBounds : bounds
					}).setView([22.6,111], 7);

				L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/256/{z}/{x}/{y}@2x?access_token={accessToken}', {
					tms: false,
					id: 'zenam/cjzk0fd9k0esv1csaqrxhiemj',
					accessToken: 'pk.eyJ1IjoiemVuYW0iLCJhIjoiY2p4bjh5MjFxMGM4aTNobGF0dXNoejlseiJ9.BPrObTer-_5w5L3oEaEWfQ',
					attribution: '©Mapbox | Performed By Lingnaam Jyutjam',
					minZoom: 6,
					maxZoom: 12
				}).addTo(map);
				excuter.currentMap = map;
			}

			initQueue.push(excuter);

		</script>
		<?php
	}

	public final function addMaker()
	{
		?>
		<script>
			excuter = initQueue.pop();

			excuter.MakerPlacer.push(function(){
				L.circle([<?php echo $this->data->getLatitude(); ?>, <?php echo $this->data->getLongitude(); ?>], { 
					color :    '<?php echo $this->data->getColor(); ?>',
					fillColor: '<?php echo $this->data->getColor(); ?>',
					fillOpacity: 0.5,
					radius: 1000
				}).addTo(excuter.currentMap);
				L.marker([<?php echo $this->data->getLatitude(); ?>, <?php echo $this->data->getLongitude(); ?>], {
					icon: L.divIcon({
						className: 'divIconDefault',
						html: 
						"<div class='locale-label' " + 
						"style='background-color: <?php echo $this->data->getColor(); ?>;" + 
						"opacity: 0.85;'>" + 
							"<div class='label-triangle'" + 
							"style=" + 
							"'border-bottom-color: <?php echo $this->data->getColor(); ?>'></div>" + 
							"<span style='color:<?php echo $this->fontcolor; ?>;'><?php echo $this->pronunciation; ?></span>" + 
						"</div>",
						iconSize: [60,]
					})
				}).addTo(excuter.currentMap);
			} );

			initQueue.push(excuter);
		</script>
		<?php
	}

	public final static function showMap()
	{
		?>
		<script>
			onLoad = function(){
				for(;initQueue.length > 0;)
				{
					let exc = initQueue.shift();
					exc.mapIniter();
					for(let func of exc.MakerPlacer)
					{	func();	}
				}
			}
			Window.onload = onLoad(); 
		</script>
		<?php
	}
}