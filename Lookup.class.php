<?php
class Sim2TradLookup  {
	private function _construct() {
		self::$instance = null;
	}
	private static $instance;
	
	public static function getInstance() {
		if (!self::$instance instanceof Sim2TradLookup) {
			self::$instance = new Sim2TradLookup();
		}
		return self::$instance;
	}
	
	public function query($character, $dbh) {   ///到時候會大改一遍的…
		$charaArray = array($character);        //改的时候请好好规划
		$sim2Trad_getCharaId_sql = "
			SELECT chara_id
			FROM `Character_simtrad_list`
			WHERE`chara` = :chara";  #从字表中查询列出符合输入的字
		$sim2Trad_getCharaId_stmt = $dbh->prepare($sim2Trad_getCharaId_sql);
		$sim2Trad_getCharaId_stmt->execute(array(':chara'=>$character));
		$sim2Trad_getCharaId_result = $sim2Trad_getCharaId_stmt->fetchAll(PDO::FETCH_ASSOC);
		
		#执行SQL语句返回结果集数列
		if ($sim2Trad_getCharaId_result!=[]) {#如果结果集存在
			$sim2Trad_SimMap_sql = "
				SELECT `chara_id_trad`
				FROM `Character_simtrad_map`
				WHERE `chara_id_sim` =" . $sim2Trad_getCharaId_result[0]['chara_id'];#查找简繁映射表
			$sim2Trad_SimMap_stmt = $dbh->prepare($sim2Trad_SimMap_sql);
			$sim2Trad_SimMap_stmt->execute();
			$sim2Trad_SimMap_result = $sim2Trad_SimMap_stmt->fetchAll(PDO::FETCH_ASSOC);
			
			foreach ($sim2Trad_SimMap_result as $items) {
				$sim2Trad_getTradChara_sql = "
					SELECT `chara`
					FROM `Character_simtrad_list`
					WHERE `chara_id` =" . $items['chara_id_trad'];
				$sim2Trad_getTradChara_stmt = $dbh->prepare($sim2Trad_getTradChara_sql);
				$sim2Trad_getTradChara_stmt->execute();
				
				$result = $sim2Trad_getTradChara_stmt->fetchAll(PDO::FETCH_ASSOC);
				if ($character != $result[0]['chara']) array_push($charaArray, $result[0]['chara']);
			}#end foreach
		}#end if(!=[])
		return $charaArray;
	}#end function query
	/*
	这个Show是用来展示多个简繁异体转换的页面的 即 2个以上一简对多的情况出现是 会显示以下页面
	<div class="general-bg-deeper" id="charaSimToTrad">
		<span id="charaSimToTradHead">简转繁</span>
		<span id="charaSimToTradMain">
		 <a href="index.php?character=發">發</a>
		 <a href="index.php?character=髮">髮</a>
		</span>
	</div>
	*/
	#charaArray的格式为 $charaArray[整数序号] => String（字们）;
	public function show($charaArray) {
		if (is_array($charaArray)) {
			$count = count($charaArray);
			if ($count > 2) {
				?>
				<div class="general-bg-deeper" id="charaSimToTrad">
					<span id="charaSimToTradHead">简转繁</span>
					<span id="charaSimToTradMain">
						<?PHP
						unset($charaArray[0]);
						foreach($charaArray as $chara) {
							echo " <a href=\"index.php?character=" . $chara . "\">" . $chara . "</a>";
						} //end foreach
						?>
					</span>
				</div>
				<?PHP
			}#end if
		}
	}
}

?>