<?php

/**
 * 郵便番号データ（大口事業所の個別番号データ）
 */
class ZipBizData extends ZipDataCommon
{
    /**
     * 大口事業所個別郵便番号 CSV
     *
     * 提供される CSV データの内容
     *
     * $data[0] = 全国地方公共団体コード(JIS X0401、X0402) ... ag_code varchar(5)
     * $data[1] = 大口事業所名（カナ）
     * $data[2] = 大口事業所名（漢字）
     * $data[3] = 都道府県 ... pref
     * $data[4] = 市区町村 ... town
     * $data[5] = 町域 ... block
     * $data[6] = 小字名、丁目、番地等 ... street
     * $data[7] = 郵便番号(7桁) ... zip_code varchar(7)
     * $data[8] = 旧郵便番号(5桁) ... old_zip_code varchar(5)
     * $data[9] = 取扱い支店名
     * $data[10] = 種別 (「0」は大口事業所、「1」は私書箱)
     * $data[11] = 複数番号 (「0」は複数番号無し、「1」以上は、複数番号のうちの何番目かを示す ... 大口事業所・私書箱は別勘定)
     * $data[12] = 更新の表示（「0」は変更なし、「1」新規追加、「5」廃止（廃止データのみ使用））... changed
     *
     * 以下は、このプログラムが追加する独自のデータ
     *
     * $data[13] = pref_kana
     * $data[14] = town_kana
     * $data[15] = block_kana
     */
    const AG_CODE = 0;
    const COMPANY_NAME_KANA = 1;
    const COMPANY_NAME = 2;
    const PREF = 3;
    const TOWN = 4;
    const BLOCK = 5;
    const STREET = 6;
    const ZIP_CODE = 7;
    const OLD_ZIP_CODE = 8;
    const SHITEN_NAME = 9;
    const TYPE = 10;
    const SERIAL_NO = 11;
    const CHANGED = 12;
    const PREF_KANA = 13;
    const TOWN_KANA = 14;
    const BLOCK_KANA = 15;

    /**
     * 最大データ長チェック用
     */
    private $maxLength = array(
        self::PREF_KANA => 0,
        self::TOWN_KANA => 0,
        self::BLOCK_KANA => 0,
        self::PREF => 0,
        self::TOWN => 0,
        self::BLOCK => 0,
        self::STREET => 0,
    );

    /**
     * コンストラクタ
     * @param string $dataDir ワーク・ディレクトリ
     * @param string $dataName データ名 (拡張子を除いたソース CSV ファイル名)
     */
    public function __construct($dataDir, $dataName)
    {
        parent::__construct($dataDir, $dataName);
    }

    /**
     * CSV データを正規化する
     * 1) 物理的に2行以上になっているデータを一行にまとめる
     *    （町域カナ、町域が複数行にわたっているデータが存在する）
     * 2) 半角カタカナから全角カタカナへ変換する
     * 3) 町域(カナ)のデータを分析して、町域(カナ) と 町域詳細(カナ) に分割する
     */
    public function normalizeCsvData()
    {
        echo "Normalizing CSV data ... ";
        echo "from [{$this->getRawCsvFilePath()}] ";
        echo "to [{$this->getCookedCsvFilePath()}] ... ";

        /** @var $srcFile object 変換元 CSV ファイル */
        $srcFile = fopen($this->getRawCsvFilePath(), 'r');
        if (!$srcFile) {
            echo "done.\n";
            echo "Failed to open the source CSV file.\n";
            return;
        }
        /** @var $srcFile object 変換先 CSV ファイル */
        $dstFile = fopen($this->getCookedCsvFilePath(), 'w');
        if (!$dstFile) {
            echo "done.\n";
            echo "Failed to open the normalized CSV file.\n";
            fclose($srcFile);
            return;
        }

        /** @var int $lineCountSrc 変換元ライン数 */
        $lineCountSrc = 0;

        /** @var KanaDic $kanaDic フリカナ辞書 */
        $kanaDic = new KanaDic;

        while ($line = fgets($srcFile)) {
            $lineCountSrc++;

            // SHIFT JIS --> UTF-8
            $line = mb_convert_encoding(trim($line), 'UTF-8', 'shift_jis');
            // 分割
            $data = explode(',', $line);
            // 引用符と空白を削除
            for ($n = self::AG_CODE; $n <= self::CHANGED; $n++) {
                $data[$n] = trim($data[$n], "\" ");
            }
            // 半角カタカナ --> 全角カタカナ
            $data[self::COMPANY_NAME_KANA] = str_replace('-', 'ー', $data[self::COMPANY_NAME_KANA]);
            $data[self::COMPANY_NAME_KANA] = mb_convert_kana($data[self::COMPANY_NAME_KANA], 'KV', 'UTF-8');
            // 全角英数字 --> 半角英数字
            $data[self::COMPANY_NAME] = str_replace('，', '、', $data[self::COMPANY_NAME]);
            $data[self::COMPANY_NAME] = mb_convert_kana($data[self::COMPANY_NAME], 'as', 'UTF-8');
            $data[self::STREET] = str_replace('，', '、', $data[self::STREET]);
            $data[self::STREET] = mb_convert_kana($data[self::STREET], 'as', 'UTF-8');

            // 振り仮名を辞書から取得
            $data[self::PREF_KANA] = $kanaDic->lookUp('pref', '', $data[self::PREF]);
            $data[self::TOWN_KANA] = $kanaDic->lookUp('town', $data[self::AG_CODE], $data[self::TOWN]);
            $data[self::BLOCK_KANA] = $kanaDic->lookUp('block', $data[self::AG_CODE], $data[self::BLOCK]);

            $this->checkMaxLength($data);
            // 書き出し
            fwrite($dstFile, implode(',', $data) . "\n");
        }

        fclose($srcFile);
        fclose($dstFile);

        echo "done.\n";
        // $this->showMaxLengths();
        echo "The source line count = $lineCountSrc\n";
        echo "\n";
    }

    /**
     * 最大データ長をチェック
     * @param array $data データ
     */
    private function checkMaxLength($data)
    {
        $this->checkStrLength($data, $this->maxLength,
            array(
                self::PREF_KANA,
                self::TOWN_KANA,
                self::BLOCK_KANA,
                self::PREF,
                self::TOWN,
                self::BLOCK,
                self::STREET
            )
        );
    }

    /**
     * 最大データ長を表示
     */
    private function showMaxLengths()
    {
        echo "Max lengths of the data\n";
        echo "-- maxLength[pref_kana] = {$this->maxLength[self::PREF_KANA]}\n";
        echo "-- maxLength[town_kana] = {$this->maxLength[self::TOWN_KANA]}\n";
        echo "-- maxLength[block_kana] = {$this->maxLength[self::BLOCK_KANA]}\n";
        echo "-- maxLength[pref] = {$this->maxLength[self::PREF]}\n";
        echo "-- maxLength[town] = {$this->maxLength[self::TOWN]}\n";
        echo "-- maxLength[block] = {$this->maxLength[self::BLOCK]}\n";
        echo "-- maxLength[street] = {$this->maxLength[self::STREET]}\n";
    }

    /**
     * INSERT SQL ファイルの作成
     */
    public function createInsertSqlFiles()
    {
        echo "Writing the INSERT SQL file ...\n";

        // 既存の同名ファイルを削除
        $this->deleteExistingSqlFiles();

        $srcFile = fopen($this->getCookedCsvFilePath(), 'r');
        if (!$srcFile) {
            echo "Failed to open the normalized CSV file.\n";
            return;
        }
        $this->setSqlFileCount(1);
        $dstFileName = $this->getSqlFilePath($this->getSqlFileCount());
        $dstFile = fopen($dstFileName, 'w');
        if (!$dstFile) {
            echo "Failed to open the SQL file.\n";
            fclose($srcFile);
            return;
        }
        echo "Writing the INSERT SQL file [$dstFileName] ...";

        $lineCount = 0;
        $sqlCount = 0;
        while ($line = fgets($srcFile)) {
            $data = explode(',', trim($line));
            $sqlLine =
                '(' .
                '"' . $data[self::AG_CODE] . '",' .
                '"' . $data[self::ZIP_CODE] . '",' .
                '"' . $data[self::PREF_KANA] . '",' .
                '"' . $data[self::TOWN_KANA] . '",' .
                '"' . $data[self::BLOCK_KANA] . '",' .
                '"' . $data[self::PREF] . '",' .
                '"' . $data[self::TOWN] . '",' .
                '"' . $data[self::BLOCK] . '",' .
                '"' . $data[self::STREET] . '",' .
                '1,' . // biz
                $data[self::TYPE] . ',' .
                $data[self::SERIAL_NO] . ',' .
                '"' . $data[self::COMPANY_NAME_KANA] . '",' .
                '"' . $data[self::COMPANY_NAME] . '"' .
                ')';
            if ($sqlCount > 0) {
                if ($sqlCount < LINES_PER_SQL) {
                    fwrite($dstFile, ",\n");
                } else {
                    fwrite($dstFile, ";\n");
                    $sqlCount = 0;
                }
            }
            if ($lineCount >= LINES_PER_SQL_FILE) {
                fclose($dstFile);
                echo "done. \n";
                $lineCount = 0;
                $sqlCount = 0;
                $this->setSqlFileCount($this->getSqlFileCount() + 1);
                $dstFileName = $this->getSqlFilePath($this->getSqlFileCount());
                $dstFile = fopen($dstFileName, 'w');
                if (!$dstFile) {
                    echo "Failed to open the SQL file.\n";
                    fclose($srcFile);
                    return;
                }
                echo "done.\n";
                echo "Writing the INSERT SQL file [$dstFileName] ... ";
            }
            if ($sqlCount == 0) {
                fwrite($dstFile,
                    "INSERT INTO `zip_data` (`ag_code`, `zip_code`, `pref_kana`, `town_kana`, `block_kana`, `pref`, `town`, `block`, `street`, `biz`, `biz_type`, `biz_ser`, `company_kana`, `company`) VALUES \n"
                );
            }
            fwrite($dstFile, $sqlLine);
            $lineCount++;
            $sqlCount++;
        }
        fwrite($dstFile, ";\n");
        fclose($dstFile);
        echo "done.\n";
        echo "\n";
    }

    /**
     * DELETE SQL ファイルの作成
     */
    public function createDeleteSqlFiles()
    {
        echo "Writing the DELETE SQL file ...\n";

        // 既存の同名ファイルを削除
        $this->deleteExistingSqlFiles();

        $srcFile = fopen($this->getCookedCsvFilePath(), 'r');
        if (!$srcFile) {
            echo "Failed to open the normalized CSV file.\n";
            return;
        }
        $this->setSqlFileCount(1);
        $dstFileName = $this->getSqlFilePath($this->getSqlFileCount());
        $dstFile = fopen($dstFileName, 'w');
        if (!$dstFile) {
            echo "Failed to open the SQL file.\n";
            fclose($srcFile);
            return;
        }
        echo "Writing the DELETE SQL file [$dstFileName] ... ";

        $lineCount = 0;
        while ($line = fgets($srcFile)) {
            $data = explode(',', trim($line));
            $sqlLine = 'DELETE FROM `zip_data` WHERE ' .
                '`zip_code` = "' . $data[self::ZIP_CODE] . '" AND ' .
                '`pref` = "' . $data[self::PREF] . '" AND ' .
                '`town` = "' . $data[self::TOWN] . '" AND ' .
                '`block` = "' . $data[self::BLOCK] . '" AND ' .
                '`street` = "' . $data[self::STREET] . '";' . "\n";
            if ($lineCount >= LINES_PER_SQL_FILE) {
                fclose($dstFile);
                echo "done. \n";
                $lineCount = 0;
                $this->setSqlFileCount($this->getSqlFileCount() + 1);
                $dstFileName = $this->getSqlFilePath($this->getSqlFileCount());
                $dstFile = fopen($dstFileName, 'w');
                if (!$dstFile) {
                    echo "Failed to open the SQL file.\n";
                    fclose($srcFile);
                    return;
                }
                echo "done.\n";
                echo "Writing the DELETE SQL file [$dstFileName] ... ";
            }
            fwrite($dstFile, $sqlLine);
            $lineCount++;
        }
        fclose($dstFile);
        echo "done.\n";
        echo "\n";
    }
}