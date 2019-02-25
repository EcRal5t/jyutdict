<?php
/**
 * Created by PhpStorm.
 * User: EcRalt
 * Date: 2019/02/21
 * Time: 21:05
 */

class Jyutping {
    protected $initial = "";
    protected $nuclei  = "";
    protected $coda    = "";
    protected $tone    = "";
    protected $vowels  = [];
    
    protected $ipa     = "";
    
    const format = "/^[a-z%]{1,10}\d{0,2}$/";
    const initialFormat = '/^(n[jg]?|bb?|dd?|[zcs][hrjl]?|[ptg]h?|[gk][wv]?|[hmqfvwjl]|%)(?=[aeoiuy%])/';
    const codaFormat    = '/[aoreiwu%](n[ng]?|[mptkh|%)(\d{0,2}|%)$/';
    const toneFormat    = '/\d{1,2}|%$/';
    const vowelFormat   = '/^(ng$|m$|ii|uu|[iu][rw]?|[aeo][aorew]?|yw|yu$|y|%$)/';
    
    const consonantIpa = [  //我要死了…
        "m"=>"m", "n"=>"n", "nj"=>"ȵ", "ng"=>"ŋ", "b"=>"p", "d"=>"t", "g"=>"k", "q"=>"ʔ", "p"=>"pʰ", "t"=>"tʰ", "k"=>"kʰ", "bb"=>"ɓ", "dd"=>"ɗ", "s"=>"s", "sh"=>"ʃ", "sr"=>"ʂ", "sj"=>"ɕ", "z"=>"ʦ", "zh"=>"ʧ", "zr"=>"ʈʂ", "zj"=>"ʨ", "c"=>"ʦʰ", "ch"=>"ʧʰ", "cr"=>"ʈʂʰ", "cj"=>"ʨʰ", "ph"=>"ɸ", "f"=>"f", "v"=>"v", "th"=>"θ", "h"=>"h", "w"=>"w", "j"=>"j", "sl"=>"ɬ", "zl"=>"tɬ", "cl"=>"tɬʰ", "l"=>"l", "gw"=>"kʷ", "kw"=>"kʷʰ", "gv"=>"kᵛ", "kv"=>"kᵛʰ", ""=>""
    ];
    const vowelIpa = [      //我真的要死了…
        "i"=>"i", "yu"=>"y", "y"=>"y", "ii"=>"ɨ", "uu"=>"ʉ", "ur"=>"ɯ", "u"=>"u", "iw"=>"ɪ", "yw"=>"ʏ", "uw"=>"ʊ", "ee"=>"e", "ew"=>"ø", "ir"=>"ɘ", "eo"=>"ɵ", "or"=>"ɤ", "oo"=>"o", "ea"=>"ə", "e"=>"ɛ", "oe"=>"œ", "aw"=>"ɜ", "ow"=>"ɞ", "er"=>"ʌ", "o"=>"ɔ", "ae"=>"æ", "a"=>"ɐ", "aa"=>"a", "ao"=>"ɶ", "ar"=>"ɑ", "oa"=>"ɒ", "m"=>"m", "n"=>"n", "ng"=>"ŋ"
    ];
    
    public function __construct($jyutping = "") {
        $tempResult = [];                               //划分粤拼音节
        
        if (preg_match(self::format, $jyutping)) {
            $tone    = preg_match(self::toneFormat   , $jyutping, $tempResult) ? $tempResult[0] : "";
            $initial = preg_match(self::initialFormat, $jyutping, $tempResult) ? $tempResult[0] : "";
            $coda    = preg_match(self::codaFormat   , $jyutping, $tempResult) ? $tempResult[1] : "";
            $nuclei  = substr($jyutping, strlen($initial), strlen($jyutping)-strlen($initial)-strlen($coda)-strlen($tone));

            $vowels = [];                               //用于存放划分得出的各个元音

            for ($count=0, $pos=0; $pos<strlen($nuclei); $count++) {  //划分韵母
                if (preg_match(self::vowelFormat ,substr($nuclei, $pos), $tempResult)) {
                    $vowels[$count] = $tempResult[0];   //从前到后用正则检测元音
                } else {
                    return;                             //元音输入有误，直接退出
                }
                $pos += strlen($vowels[$count]);        //划分出几个字母，就向后几个字母继续划分
            }
            
            $this->initial = $initial;
            $this->nuclei  = $nuclei;
            $this->coda    = $coda;
            $this->tone    = $tone;
            $this->vowels  = $vowels;
            return;                                   //划分成功
        }
        //如果运行到此，说明输入框为空，或输入粤拼结构有误（此时才改输入框背景色）
    }
    
    public function set($in, $nu, $co, $to) {
        if (!preg_match('/^(n[jg]?|bb?|dd?|[zcs][hrjl]?|[ptg]h?|[gk][wv]?|[hmqfvwjl]|%)?$/', $in) ||
            !preg_match('/^(n[ng]?|[mptkh]|%)?$/', $co) ||
            !preg_match('/^\d{0,2}|%?$/', $to) ||
            $nu == ""
        )   return 0;
        if ((empty(preg_match('/%/',$in))+empty(preg_match('/%/',$nu))+empty(preg_match('/%/',$co))+empty(preg_match('/%/',$to)) < 2)) return 0;
        
        $tempResult = [];
        $vowels = [];
        
        for ($count=0, $pos=0; $pos<strlen($nu); $count++) {  //划分韵母
            if (!preg_match(self::vowelFormat ,substr($nu, $pos), $tempResult))
                return 0;                           //元音输入有误，直接退出
            $vowels[$count] = $tempResult[0];       //从前到后用正则检测元音
            $pos += strlen($vowels[$count]);        //划分出几个字母，就向后几个字母继续划分
        }
    
        $this->initial = $in;
        $this->nuclei  = $nu;
        $this->coda    = $co;
        $this->tone    = $to;
        $this->vowels  = $vowels;
        return 1;                                   //划分成功
    }
    
    public function setIpa($ipa) {
        $this->ipa = $ipa;
    }
    
    public function show() {
        return array(
            "initial" => $this->initial,
            "nuclei"  => $this->nuclei ,
            "coda"    => $this->coda   ,
            "tone"    => $this->tone
        );
    }
    
    public function printWithColor($inColor="red", $nuColor="green", $coColor="blue", $toColor="yellow") {
        echo "<span class=\"hl-font-$inColor\">$this->initial</span>";
        echo "<span class=\"hl-font-$nuColor\">$this->nuclei</span>";
        echo "<span class=\"hl-font-$coColor\">$this->coda</span>";
        echo "<span class=\"hl-font-$toColor\">$this->tone</span>";
    }
    public function printIpaWithColor($ipaColor="cyan") {
        echo "<span class=\"hl-font-$ipaColor alphabet font-0p9em\">$this->ipa</span>";
    }
    public function printWithoutColor() {
        echo $this->initial.$this->nuclei.$this->coda.$this->tone;
    }
    
    public function toIPA() {  //這個是廢的
        $nuclei = "";
        foreach ($this->vowels as $vowel) {
            $nuclei .= self::vowelIpa[$vowel];
        }
        return array(
            "initial" => self::consonantIpa[$this->initial],
            "nuclei"  => $nuclei                           ,
            "coda"    => self::consonantIpa[$this->coda]   ,
            "tone"    => $this->tone
        );
    }
}