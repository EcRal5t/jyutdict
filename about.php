<?PHP
include ("const.php");



?>

<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-16">
    <meta http-equiv="Cache-control" content="no-cache">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>泛粵大典</title>
    <link rel="stylesheet" type="text/css" href="./css/about.css?<?PHP echo rand(); ?>">
    <link rel="icon" href="./img/favicon.ico">
    
    <script src="./js/general.js?<?PHP echo rand(); ?>"></script>
</head>

<body>

<div id="wrapper" class="wrapper">
    <?PHP Info::showSidenav(); ?>
    <div id="container" class="container" style="text-align: center;">
        <button class="sidenav-show-btn" onclick="showSidenav()"></button>
        
        <div class="info" style="margin-top: 100px;">
            <h1>字表来源</h1>
            
            <div>暂时冇嘢講。</div>
        </div>
        <hr style="margin: 25px 0;">
        <div class="illustration">
            
            <div style="">
                調值表（及調號）<br>
                <img src="./img/tone_rotated.png" alt="調值表">
            </div>
        </div>
        
        
        
<!--        <table class="general-form annex-form">-->
<!--            <tr>-->
<!--                <td>&nbsp;</td><td>&nbsp;</td><td>陰平</td><td>1</td><td>陰上</td><td>2</td><td>陰去</td><td>3</td><td>陽平</td><td>4</td><td>陽上</td><td>5</td><td>陽去</td><td>6</td><td>(上)陰入</td><td>7</td><td>下陰入</td><td>8</td><td>(上)陽入</td><td>9</td><td>下陽入</td><td>10</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td>粵海</td><td>廣州</td><td>55/53</td><td>1</td><td>35</td><td>2</td><td>33</td><td>3</td><td>21</td><td>4</td><td>23</td><td>5</td><td>22</td><td>4</td><td>5</td><td>1</td><td>3</td><td>3</td><td>2</td><td>6</td><td></td><td></td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td>粵海</td><td>端州</td><td>45/53</td><td>1</td><td>24</td><td>2</td><td>33</td><td>3</td><td>21</td><td>4</td><td>12</td><td>5</td><td>52</td><td>6</td><td>5</td><td>1</td><td>3</td><td>3</td><td>52</td><td>6</td><td></td><td></td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td>粵海</td><td>梧州</td><td>53</td><td>1</td><td>35/24</td><td>2</td><td>33</td><td>3</td><td>21</td><td>4</td><td>13/23</td><td>5</td><td>(21)</td><td>4</td><td>5</td><td>1</td><td>3</td><td>3</td><td>2/21</td><td>4</td><td></td><td></td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td>高陽</td><td>高州</td><td>55</td><td>1</td><td>35</td><td>2</td><td>33</td><td>3</td><td>11</td><td>4</td><td>13</td><td>5</td><td>22</td><td>6</td><td>5</td><td>1</td><td>3</td><td>3</td><td>2</td><td>6</td><td></td><td></td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td>高陽</td><td>陽江</td><td>33</td><td>1</td><td>21</td><td>2</td><td>24</td><td>3</td><td>42</td><td>4</td><td>(21)</td><td>2</td><td>55</td><td>6</td><td>24</td><td>3</td><td>21</td><td>2</td><td>55</td><td>6</td><td>52</td><td>4</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td>勾漏</td><td>蒼梧石橋</td><td>53</td><td>1</td><td>44</td><td>2</td><td>423</td><td>3</td><td>231</td><td>4</td><td>24</td><td>5</td><td>212</td><td>6</td><td>45</td><td>2</td><td>423</td><td>3</td><td>212</td><td>6</td><td></td><td></td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td>勾漏</td><td>鬱林大塘</td><td>454</td><td>1</td><td>33</td><td>2</td><td>52</td><td>3</td><td>232</td><td>4</td><td>13</td><td>5</td><td>21</td><td>6</td><td>5</td><td>1</td><td>3</td><td>2</td><td>12</td><td>5</td><td>1</td><td>6</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td>勾漏</td><td>貴港街里</td><td>44</td><td>1</td><td>35</td><td>2</td><td>54</td><td>3</td><td>32</td><td>4</td><td>24</td><td>5</td><td>21</td><td>6</td><td>4</td><td>1</td><td></td><td></td><td>12</td><td>6</td><td></td><td></td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td>勾漏</td><td>桂平尋旺</td><td>44</td><td>1</td><td>33</td><td>2</td><td>45</td><td>3</td><td>131</td><td>4</td><td>22</td><td>5</td><td>51</td><td>6</td><td>3</td><td>2</td><td></td><td></td><td>2</td><td>5</td><td></td><td></td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td>邕潯</td><td>南寧</td><td>55</td><td>1</td><td>35</td><td>2</td><td>33</td><td>3</td><td>21</td><td>4</td><td>24</td><td>5</td><td>22</td><td>6</td><td>5</td><td>1</td><td>3</td><td>3</td><td>2</td><td>6</td><td></td><td></td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td>莞寶</td><td>東莞</td><td>213</td><td>1</td><td>35</td><td>2</td><td>32</td><td>3</td><td>21</td><td>4</td><td>13</td><td>5</td><td>(32)</td><td>3</td><td>4</td><td>7</td><td></td><td></td><td>12</td><td>5</td><td></td><td></td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td>四邑</td><td>台山</td><td>33</td><td>1</td><td>55</td><td>2</td><td>(33)</td><td>1</td><td>22</td><td>4</td><td>21</td><td>5</td><td>32</td><td>6</td><td>5</td><td>2</td><td>3</td><td>1</td><td>2</td><td>4</td><td></td><td></td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td>欽廉</td><td>欽州</td><td>45</td><td>1</td><td>24</td><td>2</td><td>33</td><td>3</td><td>21/22</td><td>4</td><td>(24)</td><td>2</td><td>(22)</td><td>4</td><td>5</td><td>1</td><td>3</td><td>3</td><td>2</td><td>4</td><td></td><td></td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td>欽廉</td><td>北海</td><td>45</td><td>1</td><td>24</td><td>2</td><td>33</td><td>3</td><td>21</td><td>4</td><td>(24)</td><td>2</td><td>(21)</td><td>4</td><td>5</td><td>1</td><td>3</td><td>3</td><td>2</td><td>4</td><td></td><td></td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td>欽廉</td><td>廉州</td><td>45</td><td>1</td><td>24</td><td>2</td><td>33</td><td>3</td><td>(33)</td><td>3</td><td>(24)</td><td>2</td><td>21</td><td>6</td><td>3</td><td>3</td><td>24</td><td>2</td><td>2</td><td>6</td><td></td><td></td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td>吳化</td><td>吳川</td><td>55</td><td>1</td><td>13</td><td>2</td><td>21</td><td>3</td><td>33</td><td>4</td><td>11</td><td>5</td><td>31</td><td>6</td><td>5</td><td>2</td><td>21</td><td>3</td><td>3</td><td>4</td><td></td><td></td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td>平話</td><td>南寧亭子</td><td>53</td><td>1</td><td>33</td><td>2</td><td>55</td><td>3</td><td>21</td><td>4</td><td>24</td><td>5</td><td>22</td><td>6</td><td>5</td><td>3</td><td>3</td><td>2</td><td>2</td><td>6</td><td>24</td><td>5</td>-->
<!--            </tr>-->
<!--        </table>-->
    </div>
</div>
</body>

</html>













































