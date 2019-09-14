<?php

require_once("ResultIterator.class.php");

  class DataKuangyon extends CharaResultIterator
  {

    private $date = "";
    
    private $name = "";

    private $fullname = "";

    public final function __construct(PDO $dbh,string $chara,string $tableName,
    string $date,string $name,string $fullname)
    {
      $this->setDate($date);
      $this->setName($name);
      $this->setFullname($fullname);
      parent::__construct($dbh,$chara,$tableName);
    }

    protected final function setSelect_Sql(string $sql = " 
    `id`,`chara`,`initial`,`rimeclass`,`rime`,`division`,`rounding`,
    `tone`,`transliteration` 
    "): void
    { $this->select_sql = $sql;}
    
    #此处默认参数非必要 因为会从Info里面获取
    protected final function setTableName(string $tableName = "YKuangyon"): void
    { $this->tableName  = $tableName; }
    
    private final function setDate(string $date)
    { $this->date = $date;  }

    private final function setName(string $name)
    { $this->name = $name;  }

    private final function setFullname(string $fullname)
    { $this->fullname = $fullname;  }

    public final function getID() : string
    { return $this->getCurrentList()['id']; }    

    public final function getChara() : string
    { return $this->getCurrentList()['chara']; }      

    public final function getInitial() : string
    { return $this->getCurrentList()['initial']; }    

    public final function getRimeclass() : string
    { return $this->getCurrentList()['rimeclass']; }    

    public final function getRime() : string
    { return $this->getCurrentList()['rime']; }    

    public final function getDivision() : string
    { return $this->getCurrentList()['division']; }    

    public final function getRounding() : string
    { return $this->getCurrentList()['rounding']; }    

    public final function getTone() : string
    { return $this->getCurrentList()['tone']; }    

    public final function getTransliteration() : string
    { return $this->getCurrentList()['transliteration']; }      

  }

  class DataFanwan extends CharaResultIterator
  {
    
    private $date = "";
    
    private $name = "";

    private $fullname = "";

    public final function __construct(PDO $dbh,string $chara,string $tableName,
    string $date,string $name,string $fullname)
    {
      $this->setDate($date);
      $this->setName($name);
      $this->setFullname($fullname);
      parent::__construct($dbh,$chara,$tableName);
    }

    protected final function setSelect_Sql(string $sql = " 
    `id`,`chara`,`initial`,`nuclei`,`coda`,`tone`,`siuwan`,`meaning`,
    `initial_ch`,`final_ch`,`yunbu`,`tone_ch` 
    "): void
    { $this->select_sql = $sql;}
    
    #此处默认参数非必要 因为会从Info里面获取
    protected final function setTableName(string $tableName = "YFanwan"): void
    { $this->tableName  = $tableName; }

    private final function setDate(string $date)
    { $this->date = $date;  }

    private final function setName(string $name)
    { $this->name = $name;  }

    private final function setFullname(string $fullname)
    { $this->fullname = $fullname;  }

    public final function getName() : string
    { return $this->name; }

    public final function getFullname() : string
    { return $this->fullname; }

    public final function getDate() : string
    { return $this->date; }


    public final function getID() : string
    { return $this->getCurrentList()['id']; }

    public final function getChara() : string
    { return $this->getCurrentList()['chara']; }

    public final function getInitial() : string
    { return $this->getCurrentList()['initial']; }

    public final function getNuclei() : string
    { return $this->getCurrentList()['nuclei']; }

    public final function getCoda() : string
    { return $this->getCurrentList()['coda']; }

    public final function getTone() : string
    { return $this->getCurrentList()['tone']; }

    public final function getSiuwan() : string
    { return $this->getCurrentList()['siuwan']; }

    public final function getMeaning() : string
    { return $this->getCurrentList()['meaning']; }

    public final function getInitial_ch() : string
    { return $this->getCurrentList()['initial_ch']; }

    public final function getFinal_ch() : string
    { return $this->getCurrentList()['final_ch']; }

    public final function getYunbu() : string
    { return $this->getCurrentList()['yunbu']; }  

    public final function getTone_ch() : string
    { return $this->getCurrentList()['tone_ch']; }

  }

  #JIngwaa同Fanwan我想应该可以归为一个类节省代码（GETER）一样 但是想想太麻烦了
  class DataJingwaa extends CharaResultIterator
  {

    private $date = "";
    
    private $name = "";

    private $fullname = "";

    public final function __construct(PDO $dbh,string $chara,string $tableName,
    string $date,string $name,string $fullname)
    {
      $this->setDate($date);
      $this->setName($name);
      $this->setFullname($fullname);
      parent::__construct($dbh,$chara,$tableName);
    }

    protected final function setSelect_Sql(string $sql = " 
    `id`,`chara`,`initial`,`nuclei`,`coda`,`tone`,`pron`,`radical`,
    `radical_stroke`,`extra_stroke`,`page`,`state`,`order`
    "): void
    { $this->select_sql = $sql;}
    
    #此处默认参数非必要 因为会从Info里面获取
    protected final function setTableName(string $tableName = "YJingwaa"): void
    { $this->tableName  = $tableName; }


    private final function setDate(string $date)
    { $this->date = $date;  }

    private final function setName(string $name)
    { $this->name = $name;  }

    private final function setFullname(string $fullname)
    { $this->fullname = $fullname;  }

    public final function getID() : string
    { return $this->getCurrentList()['id']; }

    public final function getChara() : string
    { return $this->getCurrentList()['chara']; }

    public final function getInitial() : string
    { return $this->getCurrentList()['initial']; }

    public final function getNuclei() : string
    { return $this->getCurrentList()['nuclei']; }

    public final function getCoda() : string
    { return $this->getCurrentList()['coda']; }

    public final function getTone() : string
    { return $this->getCurrentList()['tone']; }

    public final function getPronunciation() : string
    { return $this->getCurrentList()['pron']; }

    public final function getRadical() : string
    { return $this->getCurrentList()['radical']; }

    public final function getRadical_stroke() : string
    { return $this->getCurrentList()['radical_stroke']; }

    public final function getExtra_stroke() : string
    { return $this->getCurrentList()['extra_stroke']; }

    public final function getPage() : string
    { return $this->getCurrentList()['page']; }  

    public final function getState() : string
    { return $this->getCurrentList()['state']; }

    public final function getOrder()  : string
    { return $this->getCurrentList()['order'];  }

  }

  class DataArea extends CharaResultIterator
  {
    private $longitude  = "";

    private $latitude = "";

    private $city = "";

    private $district = "";

    private $color  = "";

    private $division = "";
    
    public final function __construct(PDO $dbh,string $chara,string $tableName,
    string $longitude,string $latitude,string $division,string $city,
    string $district,string $color)
    {
      $this->setLongitude($longitude);
      $this->setLatitude($latitude);
      $this->setCity($city);        
      $this->setDistrict($district);
      $this->setColor($color);
      $this->setDivision($division);
      parent::__construct($dbh,$chara,$tableName);
    }

    protected final function setSelect_Sql(string $sql = " 
    `id`,`chara`,`initial`,`nuclei`,`coda`,`tone`,`ipa`,`note`
    "): void
    { $this->select_sql = $sql;}

    private final function setLongitude(string $longitude)
    { $this->longitude  = $longitude; }

    private final function setLatitude(string $latitude)
    { $this->latitude = $latitude;  }

    private final function setDivision(string $division)
    { $this->division  = $division; }

    private final function setCity(string $city)
    { $this->city = $city;  }

    private final function setDistrict(string $district)
    { $this->district = $district;  }

    private final function setColor(string $color)
    { $this->color  = $color; }

    public final function getDivision() : string
    { return $this->division; }
    
    public final function getLongitude()  : string
    { return $this->longitude;  }

    public final function getLatitude() : string
    { return $this->latitude; }

    public final function getCity() : string
    { return $this->city; }

    public final function getDistrict() : string
    { return $this->district; }

    public final function getColor()  : string
    { return $this->color;   }

    public final function getID() : string
    { return $this->getCurrentList()['id']; }

    public final function getChara() : string
    { return $this->getCurrentList()['chara']; }

    public final function getInitial() : string
    { return $this->getCurrentList()['initial']; }

    public final function getNuclei() : string
    { return $this->getCurrentList()['nuclei']; }

    public final function getCoda() : string
    { return $this->getCurrentList()['coda']; }

    public final function getTone() : string
    { return $this->getCurrentList()['tone']; }

    public final function getIPA() : string
    { return $this->getCurrentList()['ipa']; }

    public final function getNote() : string
    { return $this->getCurrentList()['note']; }

  }
?>