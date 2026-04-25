<?php
/**
 * 粵拼解析器
 * 從根目錄 Jyutping.class.php 搬入，移除了不再使用的 HTML 輸出方法和廢棄的 toIPA()。
 */

class Jyutping {
    protected $initial = "";
    protected $nuclei  = "";
    protected $coda    = "";
    protected $tone    = "";
    protected $vowels  = [];
    protected $ipa     = "";
    protected $valid   = false;

    const format = "/^[a-z%]{1,10}([0-9]?[0-9*][0-9\']?)?$/";
    const initialFormat = '/^(mb?|n[jrd]?|ngg?|[bdg]{1,2}|g[hn]?|r[bdgzscrh]|[zcs][hrjl]?|[ptkvw]h?|[hqfjlrx0])([jwv]?)(?=[aeoiuymn])/';
    const codaFormat    = '/[aoreiwu%](n[ng]?|[mptkh|%])(\d{0,2}|%)$/';
    const toneFormat    = '/[0-9]?[0-9*][0-9\']?$/';
    const vowelFormat   = '/(^ng?$|^m$|i[rwi]?|u[rwu]?|[aeo][aeowr]?|yu$|y)$/';

    const consonantIpa = [
        "m"=>"m", "n"=>"n", "nj"=>"ȵ", "ng"=>"ŋ", "b"=>"p", "d"=>"t", "g"=>"k", "q"=>"ʔ", "p"=>"pʰ", "t"=>"tʰ", "k"=>"kʰ", "bb"=>"ɓ", "dd"=>"ɗ", "s"=>"s", "sh"=>"ʃ", "sr"=>"ʂ", "sj"=>"ɕ", "z"=>"ʦ", "zh"=>"ʧ", "zr"=>"ʈʂ", "zj"=>"ʨ", "c"=>"ʦʰ", "ch"=>"ʧʰ", "cr"=>"ʈʂʰ", "cj"=>"ʨʰ", "ph"=>"ɸ", "f"=>"f", "v"=>"v", "th"=>"θ", "h"=>"h", "w"=>"w", "j"=>"j", "sl"=>"ɬ", "zl"=>"tɬ", "cl"=>"tɬʰ", "l"=>"l", "gw"=>"kʷ", "kw"=>"kʷʰ", "gv"=>"kᵛ", "kv"=>"kᵛʰ", ""=>""
    ];
    const vowelIpa = [
        "i"=>"i", "yu"=>"y", "y"=>"y", "ii"=>"ɨ", "uu"=>"ʉ", "ur"=>"ɯ", "u"=>"u", "iw"=>"ɪ", "yw"=>"ʏ", "uw"=>"ʊ", "ee"=>"e", "ew"=>"ø", "ir"=>"ɘ", "eo"=>"ɵ", "or"=>"ɤ", "oo"=>"o", "ea"=>"ə", "e"=>"ɛ", "oe"=>"œ", "aw"=>"ɜ", "ow"=>"ɞ", "er"=>"ʌ", "o"=>"ɔ", "ae"=>"æ", "a"=>"ɐ", "aa"=>"a", "ao"=>"ɶ", "ar"=>"ɑ", "oa"=>"ɒ", "m"=>"m", "n"=>"n", "ng"=>"ŋ", "z"=>"z"
    ];

    public function __construct() {
    }

    public static function jyutping_parser($jyutping_str) {
        $tempResult = [];
        if (preg_match(self::format, $jyutping_str)) {
            $tone    = preg_match(self::toneFormat   , $jyutping_str, $tempResult) ? $tempResult[0] : "";
            $initial = preg_match(self::initialFormat, $jyutping_str, $tempResult) ? $tempResult[0] : "";
            $coda    = preg_match(self::codaFormat   , $jyutping_str, $tempResult) ? $tempResult[1] : "";
            $nuclei  = substr($jyutping_str, strlen($initial), strlen($jyutping_str)-strlen($initial)-strlen($coda)-strlen($tone));
            $vowels = [];
            for ($count=0, $pos=0; $pos<strlen($nuclei); $count++) {
                if (preg_match(self::vowelFormat, substr($nuclei, $pos), $tempResult)) {
                    $vowels[$count] = $tempResult[0];
                } else {
                    return false;
                }
                $pos += strlen($vowels[$count]);
            }
            return [$initial, $nuclei, $coda, $tone];
        }
        return false;
    }

    public function setWithRaw($jyutping) {
        $parse_result = self::jyutping_parser($jyutping);
        if ($parse_result) {
            $this->initial = $parse_result[0];
            $this->nuclei  = $parse_result[1];
            $this->coda    = $parse_result[2];
            $this->tone    = $parse_result[3];
            $this->valid   = true;
            return true;
        }
        return false;
    }

    public function set($in, $nu, $co, $to) {
        if (!preg_match('/^(n[jg]?|bb?|dd?|[zcs][hrjl]?|[ptg]h?|[gk][wv]?|[hmqfvwjl]|%)?$/', $in) ||
            !preg_match('/^(n[ng]?|[mptkh]|%)?$/', $co) ||
            !preg_match('/^\d{0,2}|%?$/', $to) ||
            $nu == ""
        ) return 0;

        if ((empty(preg_match('/%/',$in))+empty(preg_match('/%/',$nu))+empty(preg_match('/%/',$co))+empty(preg_match('/%/',$to)) < 2)) return 0;

        $tempResult = [];
        $vowels = [];
        for ($count=0, $pos=0; $pos<strlen($nu); $count++) {
            if (!preg_match(self::vowelFormat, substr($nu, $pos), $tempResult))
                return 0;
            $vowels[$count] = $tempResult[0];
            $pos += strlen($vowels[$count]);
        }

        $this->initial = $in;
        $this->nuclei  = $nu;
        $this->coda    = $co;
        $this->tone    = $to;
        $this->vowels  = $vowels;
        return 1;
    }

    public function setIpa($ipa) {
        $this->ipa = $ipa;
    }

    public function isValid()    { return $this->valid; }
    public function getInitial() { return $this->initial; }
    public function getNuclei()  { return $this->nuclei; }
    public function getCoda()    { return $this->coda; }
    public function getTone()    { return $this->tone; }
    public function getVowels()  { return $this->vowels; }

    public function show() {
        return [
            "initial" => $this->initial,
            "nuclei"  => $this->nuclei,
            "coda"    => $this->coda,
            "tone"    => $this->tone
        ];
    }
}
