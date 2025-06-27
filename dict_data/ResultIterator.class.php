<?PHP
abstract class ResultIterator{

	protected $tableName = "";
	
	protected $dbh;
	
	protected $listIterator = NULL;
	
	protected $select_sql = "";

	protected $whereClause = "1";

	protected $argsList = array();
	
	protected function setArgsList(array $args)
	{	$this->argsList = $args;	}
	
	protected abstract function setSelect_Sql(string $sql = "") : void;
	
	protected abstract function setWhereClause(string $sql = "1") : void;
	
	protected abstract function setTableName(string $tableName = "") : void;
	
	#when Constrcting Would Excute a quert with SQL of (select_sql + listName)
	public function __construct(PDO $dbh)
	{
		#initialized PDO
		$this->dbh = $dbh;
		if($this->select_sql == "")
		{	$this->setSelect_Sql();	}
		if($this->whereClause == "1") 
		{	$this->setWhereClause();	}
		if($this->tableName == "") 
		{	$this->setTableName();	}
		#Thorw An Exception When Exception Occurred During Constucting
		try{
			$this->updateInfoFromDB($this->select_sql,$this->tableName,$this->whereClause,$this->argsList);
		}catch(Exception $e){
			throw($e);
		}
	}

	protected function updateInfoFromDB(string $select_sql,string $tableName,string $whereClause,array $argsList)
	{
		try{
			$sql = "
			SELECT " . $select_sql . " 
			FROM " . $tableName . " 
			WHERE " . $whereClause;
			$getList_stmt = $this->dbh->prepare($sql);
			$getList_stmt->execute($argsList);
			$getList_result = $getList_stmt->fetchAll(PDO::FETCH_ASSOC);
			$this->listIterator = new ArrayIterator($getList_result);
			return true;
		}catch(Exception $e){
			// throw($e);
			die("<h1> ErrorOccured: $tableName </h1>");
		}
		return false;
	}
	
	protected function getTableName()	: string 
	{	return $this->tableName;	}
	
	public function listCount()	: int 
	{ return $this->listIterator->count();}
	
	public function hasNext()	:	bool
	{ return $this->listIterator->valid();}
	
	public function next()	:	void
	{	$this->listIterator->next();	}
	
	public function getCurrentList()	:	array
	{	return $this->listIterator->current();	}

}

abstract class CharaResultIterator extends ResultIterator{

	protected $chara  = "";

	protected function setChara(string $chara)
	{	$this->chara = $chara;	}
	
	protected abstract function setSelect_Sql(string $sql = "") : void;
	
	protected function setTableName(string $tableName = ""): void
	{ $this->tableName  = $tableName; }

	protected function setWhereClause(string $sql = "`chara` = :chara") : void
	{	$this->whereClause = $sql;	}

	protected function setArgsList(array $args)
	{ $this->argsList = $args; }

	public function __construct(PDO $dbh,string $chara,string $tableName)
	{
		$this->setChara($chara);
		$this->setArgsList(array(":chara" => $this->chara));
		$this->setTableName($tableName);
		parent::__construct($dbh);
	}
} 

?>