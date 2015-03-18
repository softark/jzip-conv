<?php
/* 
 * 振り仮名辞書
 *
 * pref.csv ... 都道府県名辞書
 * town.csv ... 市区町村名辞書
 * block.csv ... 町域名辞書
 *
 * $key => $value の配列
 * $key ... [自治体コード]:[地名(漢字)] ... 自治体コードは、漢字が同じで読みが違う地名を区別するため
 *          ただし、都道府県名については、自治体コードは参照しない
 * $value ... [地名(全角カタカナ)]
 *
 */
class KanaDic
{
    /**
     * 振り仮名辞書の格納ディレクトリ
     */
    private $kanaDicDir;

    /**
     * 都道府県名辞書
     * @var array
     */
    private $prefDic = array();
    /**
     * 市区町村名辞書
     * @var array
     */
    private $townDic = array();
    /**
     * 町域名辞書
     * @var array
     */
    private $blockDic = array();

    /**
     * コンストラクタ
     */
    public function __construct($_kanaDicDir = null)
    {
        if (isset($_kanaDicDir)) {
            $this->kanaDicDir = $_kanaDicDir;
        } else {
            if (defined('KANA_DIC_DIR')) {
                $this->kanaDicDir = KANA_DIC_DIR;
            } else {
                $this->kanaDicDir = '.' . DIRECTORY_SEPARATOR;
            }
        }
        $this->prefDic = $this->readKanaDic('pref');
        $this->townDic = $this->readKanaDic('town');
        $this->blockDic = $this->readKanaDic('block');
        $this->addMissingData();
    }

    /**
     * 辞書を保存する
     */
    public function save()
    {
        $this->writeKanaDic($this->prefDic, 'pref');
        $this->writeKanaDic($this->townDic, 'town');
        $this->writeKanaDic($this->blockDic, 'block');
    }

    /**
     * 辞書から振り仮名を読む
     * @param string $type タイプ ... 'pref' or 'town' or 'block'
     * @param string $agCode 自治体コード
     * @param string $name 地名
     * @return string 振り仮名 (辞書に無ければ '')
     */
    public function lookUp($type, $agCode, $name)
    {
        $kana = '';
        if ($type == 'pref') {
            if (isset($this->prefDic[$name])) {
                $kana = $this->prefDic[$name];
            }
        } else if ($type == 'town') {
            $key = $agCode . ':' . $name;
            if (isset($this->townDic[$key])) {
                $kana = $this->townDic[$key];
            }
        } else if ($type == 'block') {
            $key = $agCode . ':' . $name;
            if (isset($this->blockDic[$key])) {
                $kana = $this->blockDic[$key];
            }
        }
        return $kana;
    }

    /**
     * 辞書に振り仮名を登録する
     * @param string $type タイプ ... 'pref' or 'town' or 'block'
     * @param string $agCode 自治体コード
     * @param string $name 地名
     * @param string $kana 振り仮名
     */
    public function register($type, $agCode, $name, $kana)
    {
        if ($type == 'pref') {
            $this->prefDic[$name] = $kana;
        } else if ($type == 'town') {
            $key = $agCode . ':' . $name;
            $this->townDic[$key] = $kana;
        } else if ($type == 'block') {
            $key = $agCode . ':' . $name;
            $this->blockDic[$key] = $kana;
        }
    }

    /**
     * 追加
     */
    private function addMissingData()
    {
        // 補遺  大口事業所の住所で、一般の住所の読み仮名からは読みが分らないもの
        // ヶ/ケ の違い、市町村合併後の更新漏れなど
        $this->register('town', '11226', '鳩ケ谷市', 'ハトガヤシ');
        $this->register('town', '11241', '鶴ケ島市', 'ツルガシマシ');
        $this->register('town', '14207', '茅ケ崎市', 'チガサキシ');
        $this->register('town', '15441', '北魚沼郡川口町', 'キタウオヌマグンカワグチマチ');
        $this->register('town', '17386', '羽咋郡宝逹志水町', 'ハクイグンホウダツシミズチョウ');
        $this->register('town', '22361', '富士郡芝川町', 'フジグンシバカワチョウ');
        $this->register('town', '22503', '浜名郡新居町', 'ハマナグンアライチョウ');
        $this->register('town', '23421', '海部郡七宝町', 'アマグンシッポウチョウ');
        $this->register('town', '23423', '海部郡甚目寺町', 'アマグンジモクジチョウ');
        $this->register('town', '23481', '幡豆郡一色町', 'ハズグンイッシキチョウ');
        $this->register('town', '23482', '幡豆郡吉良町', 'ハズグンキラチョウ');
        $this->register('town', '23483', '幡豆郡幡豆町', 'ハズグンハズチョウ');
        $this->register('town', '45301', '宮崎郡清武町', 'ミヤザキグンキヨタケチョウ');
        $this->register('town', '45362', '西諸県郡野尻町', 'ニシモロカタグンノジリチョウ');
        $this->register('town', '46441', '姶良郡加治木町', 'アイラグンカジキチョウ');
        $this->register('town', '46442', '姶良郡姶良町', 'アイラグンアイラチョウ');
        $this->register('town', '46443', '姶良郡蒲生町', 'アイラグンカモウチョウ');
    }

    /**
     * 振り仮名辞書をファイルから読む
     * @param string $type
     * @return array
     */
    private function readKanaDic($type)
    {
        $dic = array();
        $fileName = $this->kanaDicDir . DIRECTORY_SEPARATOR . $type . '.csv';
        if (file_exists($fileName)) {
            if ($dicFile = fopen($fileName, 'r')) {
                while ($line = fgets($dicFile)) {
                    $words = explode(',', $line);
                    $key = trim($words[0], '"');
                    $value = trim($words[1], "\"\n");
                    $dic[$key] = $value;
                }
                fclose($dicFile);
            } else {
                fputs(STDERR, "Failed to read kana dictionary [$fileName]\n");
                exit(-1);
            }
        }
        return $dic;
    }

    /**
     * 振り仮名辞書をファイルに書き出す
     * @param array $dic
     * @param string $type
     */
    private function writeKanaDic($dic, $type)
    {
        $fileName = $this->kanaDicDir . DIRECTORY_SEPARATOR . $type . '.csv';
        if ($dicFile = fopen($fileName, 'w')) {
            foreach ($dic as $key => $value) {
                fwrite($dicFile, '"' . $key . '","' . $value . '"' . "\n");
            }
            fclose($dicFile);
        } else {
            fputs(STDERR, "Failed to write kana dictionary [$fileName]\n");
            exit(-1);
        }
    }
}