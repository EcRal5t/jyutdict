<?php
const BEGIN = true;
const END = false;

class ShowViewFactory{
  
  private $dbh;

  private $chara = "";

  public final function getDictPresenter(string $dictname) : DictPresenter
  {
    switch ($dictname) {

      case 'wanshyu':
        return new WanshyuPresenter($this->dbh,$this->chara);
        break;

      case 'area':
        return new AreaPresenter($this->dbh,$this->chara);
        break;

      default:
        return null;
        break;
    }
  }

  public function __construct(PDO $dbh,string $chara)
  {
    $this->dbh  = $dbh;
    $this->chara = $chara;
  }

}

interface IndexPresenter{

  public function show();

}
abstract class DictPresenter implements IndexPresenter
{

  protected $dbh;

  protected $chara = "";

  public abstract function show();


  public function __construct(PDO $dbh,string $chara)
  {
    $this->dbh  = $dbh;
    $this->chara = $chara;
  }

}
class KuangyonPresenter extends DictPresenter{
  
  public function show()
  {
    $data = new DataKuangyon($this->dbh,$this->chara,"YKuangyon","","","");
    $view = new ViewKuangyon( ($data->listCount() > 0 ) );
    $view->updateData($data);
    $view->printFramework(BEGIN);
    for($time = 0;$data->hasNext();$data->next() )
    {
      $view->updateData($data);
      $view->printContentList();
    }
    $view->printFramework(END);
    
  }

}

class WanshyuPresenter extends DictPresenter{
  
  public function show()
  {
    ViewWanshyuResult::printFramework(BEGIN);
    $kuangyonPresenter = new KuangyonPresenter($this->dbh,$this->chara);
    $kuangyonPresenter->show();
    $info = new InfoWanshyu($this->dbh);
    ViewWanshyuResult::printContentListFramework(BEGIN);
    for(;$info->hasNext();$info->next() )
    {
      switch ($info->getName() ) {
        case '分韻':
          $data = new DataFanwan($this->dbh,$this->chara,
                $info->getSheetname(),$info->getdate(),
                $info->getName(),$info->getFullname());
          $isGet = ($data->listCount() > 0);
          $view = new ViewFanwan( $isGet );
          if($isGet)
          {
            for($time = 0;$data->hasNext();$data->next() )
            {
              $view->updateData($data);
              if($time++ == 0) $view->printFramework(BEGIN);
              $view->printContentList();
            }
          }else{
            $view->printContentList();
          }
          $view->printFramework(END);
          break;

        case '英華':
        $data = new DataJingwaa($this->dbh,$this->chara,
              $info->getSheetname(),$info->getdate(),
              $info->getName(),$info->getFullname());
        $lastOrder = '0';
        $isGet = ($data->listCount() > 0);
        $view = new ViewJingwaa( $isGet );
        if($isGet)
        {
          for($time = 0;$data->hasNext();$data->next() )
          {
            $view->updateData($data,$lastOrder);
            if($time++ == 0) $view->printFramework(BEGIN);
            $view->printContentList();
            $lastOrder = $data->getOrder();
          }
        }else{
          $view->printContentList();
        }
        $view->printFramework(END);
          break;

        default:
          break;
      }
    }
    ViewWanshyuResult::printContentListFramework(END);
    ViewWanshyuResult::printFramework(END);
  }
}
class AreaPresenter extends DictPresenter{
  

  public function show()
  {
    $info = new InfoArea($this->dbh);
    $view = new ViewArea(true);
    $time = 0;
    for(;$info->hasNext() ;$info->next() )
    {
      $data = new DataArea($this->dbh,$this->chara,$info->getSheetname(),
        $info->getLongitude(),$info->getLatitude(),$info->getDivision(),
        $info->getCity(),$info->getDistrict(),$info->getColor());
      if($data->listCount() > 0)
      {
        for(;$data->hasNext();$data->next() )
        {
          $view->updateData($data);
          if($time++ == 0) $view->printTableFramework(BEGIN);
          $view->printContentList();
        }
      }
    }
    $view->printTableFramework(END);
  }

  public function printAreaFramework(bool $isBegin)
  { ViewArea::printAreaFramework($isBegin);}

  public function printMapDependency()
  { ViewMap::printDependency(); }

  public function prepareMap(string $mapName)
  {
    $info = new InfoArea($this->dbh);
    $view = new ViewMap($mapName);
    for($count = 0;$info->hasNext() ;$info->next() )
    {
      
      $data = new DataArea($this->dbh,$this->chara,$info->getSheetname(),
      $info->getLongitude(),$info->getLatitude(),$info->getDivision(),
      $info->getCity(),$info->getDistrict(),$info->getColor());

      if($count == 0 && $data->listCount() > 0)
      {
        $view->printMapDiv();
        $view->initMap();
        $count++;
      }

      if($data->listCount() > 0)
      {
          $view->updateData($data);
          $view->addMaker();
      }
    }
  }
  public function showMap()
  { ViewMap::showMap();}
}

?>