<?php
require_once("ResultIterator.class.php");
class InfoArea extends ResultIterator
{
  protected final function setSelect_Sql(string $sql = " 
  `id`,`longitude`,`latitude`,`first`,`second`,`third`,`sheetname`,`color`") : void
  { $this->select_sql	=	$sql; }

  protected final function setTableName(string $tableName = "IAreaList") : void
  { $this->tableName	=	$tableName; }

  protected final function setWhereClause(string $sql = "1") : void
  { $this->whereClause  = $sql; }

  public final function getID() : string
  { return $this->getCurrentList()['id']; }

  public final function getLongitude() : string
  { return $this->getCurrentList()['longitude']; }

  public final function getLatitude() : string
  { return $this->getCurrentList()['latitude']; }

  public final function getDivision() : string
  { return $this->getCurrentList()['first']; }

  public final function getCity() : string
  { return $this->getCurrentList()['second']; }
  
  public final function getDistrict() : string
  { return $this->getCurrentList()['third']; }

  public final function getSheetname()  : string
  { return $this->getCurrentList()['sheetname']; }

  public final function getColor()  : string
  { return $this->getCurrentList()['color']; }

}

class InfoWanshyu extends ResultIterator
{
  protected final function setSelect_Sql(string $sql ="
  `id`,`name`,`fullname`,`date`,`sheetname` ") : void
  { $this->select_sql	=	$sql; }

  protected final function setTableName(string $tableName = "IWanshyuList") : void
  { $this->tableName	=	$tableName; }

  protected final function setWhereClause(string $sql = "1") : void
  { $this->whereClause  = $sql; }
  
	public final function getID()	:	string
	{ return $this->getCurrentList()['id']; }
	
	public final function getName()	:	string
	{	return $this->getCurrentList()['name'];	}
	
	public final function getFullName()	:	string
	{	return $this->getCurrentList()['fullname'];	}

	public final function getDate()	:	string
	{	return $this->getCurrentList()['date'];	}

	public final function getSheetname()	:	string
	{	return $this->getCurrentList()['sheetname'];	}
}
?>