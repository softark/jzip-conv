<?php

/**
 * 郵便番号データ（大口事業所の個別番号を含まないデータ）
 */
class ZipData extends ZipDataCommon
{
    /**
     * 郵便番号 CSV
     *
     * 提供される CSV データの内容
     *
     * $data[0] = 全国地方公共団体コード(JIS X0401、X0402) ... AG_CODE varchar(5)
     * $data[1] = 旧郵便番号(5桁) ... OLD_ZIP_CODE varchar(5)
     * $data[2] = 郵便番号(7桁) ... ZIP_CODE varchar(7)
     * $data[3] = 都道府県カナ ... 半角カタカナ ... 全角カタカナに変換 ... PREF_KANA
     * $data[4] = 市区町村カナ ... 半角カタカナ ... 全角カタカナに変換 ... TOWN_KANA
     * $data[5] = 町域カナ ... 半角カタカナ ... 全角カタカナに変換 ... BLOCK_KANA
     * $data[6] = 都道府県 ... PREF
     * $data[7] = 市区町村 ... TOWN
     * $data[8] = 町域 ... BLOCK
     * $data[9] = 一町域が二以上の郵便番号で表される場合の表示　(「1」は該当、「0」は該当せず) ... (block has multi zip_codes) M_ZIPS ... 信用できない
     * $data[10] = 小字毎に番地が起番されている町域の表示 (「1」は該当、「0」は該当せず) ... (block has multi banchis) M_BANCHIS ... あまり有用でない
     * $data[11] = 丁目を有する町域の場合の表示　(「1」は該当、「0」は該当せず) ... (block has chomes) CHOMES ... あまり有用でない
     * $data[12] = 一つの郵便番号で二以上の町域を表す場合の表示　(「1」は該当、「0」は該当せず) ... (zip_code has multi blocks) M_BLOCKS ... 信用できない
     * $data[13] = 更新の表示（「0」は変更なし、「1」は変更あり、「2」廃止（廃止データのみ使用））... CHANGED
     * $data[14] = 変更理由　(「0」は変更なし、「1」市政・区政・町政・分区・政令指定都市施行、「2」住居表示の実施、「3」区画整理、「4」郵便区調整等、「5」訂正、「6」廃止(廃止データのみ使用)) ... REASON
     *
     * 以下は、このプログラムが追加する独自のデータ
     *
     * $data[15] = 町域詳細カナ ... STREET_KANA ... BLOCK_KANA の明細
     * $data[16] = 町域詳細 ... STREET ... BLOCK の明細
     *
     */
    const AG_CODE = 0;
    const OLD_ZIP_CODE = 1;
    const ZIP_CODE = 2;
    const PREF_KANA = 3;
    const TOWN_KANA = 4;
    const BLOCK_KANA = 5;
    const PREF = 6;
    const TOWN = 7;
    const BLOCK = 8;
    const M_ZIPS = 9;
    const M_BANCHIS = 10;
    const CHOMES = 11;
    const M_BLOCKS = 12;
    const CHANGED = 13;
    const REASON = 14;
    const STREET_KANA = 15;
    const STREET = 16;

    /**
     * 最大データ長チェック用
     */
    private $maxLength = array(
        self::PREF_KANA => 0,
        self::TOWN_KANA => 0,
        self::BLOCK_KANA => 0,
        self::STREET_KANA => 0,
        self::PREF => 0,
        self::TOWN => 0,
        self::BLOCK => 0,
        self::STREET => 0,
    );

    /**
     * コンストラクタ
     * @param $dataDir ワーク・ディレクトリ
     * @param $dataName データ名 (拡張子を除いたソース CSV ファイル名)
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
        echo "CSV データ正規化開始 ... \n";
        echo "変換元 CSV ファイル = " . $this->getRawCsvFilePath() . "\n";
        echo "正規化 CSV ファイル = " . $this->getCookedCsvFilePath() . "\n";

        /** @var $srcFile object 変換元 CSV ファイル */
        $srcFile = fopen($this->getRawCsvFilePath(), 'r');
        if (!$srcFile) {
            echo "変換元 CSV ファイルのオープンに失敗。\n";
            return;
        }
        /** @var $srcFile object 変換先 CSV ファイル */
        $dstFile = fopen($this->getCookedCsvFilePath(), 'w');
        if (!$dstFile) {
            echo "正規化 CSV ファイルのオープンに失敗。\n";
            fclose($srcFile);
            return;
        }
        /** @var $dataPending array データバッファ */
        $dataPending = array();
        /** @var $lineCountSrc int 変換元ライン数 */
        $lineCountSrc = 0;
        /** @var $lineCountDst int 変換先ライン数 */
        $lineCountDst = 0;

        while ($line = fgets($srcFile)) {
            $lineCountSrc++;

            // SHIFT JIS --> UTF-8
            $line = mb_convert_encoding(trim($line), 'UTF-8', 'shift_jis');
            // カンマで分割
            $data = explode(',', $line);
            // 引用符と空白を削除
            for ($n = self::AG_CODE; $n <= self::REASON; $n++) {
                $data[$n] = trim($data[$n], "\" ");
            }
            // 半角カタカナ --> 全角カタカナ
            $data[self::PREF_KANA] = mb_convert_kana($data[self::PREF_KANA], 'KV', 'UTF-8');
            $data[self::TOWN_KANA] = mb_convert_kana($data[self::TOWN_KANA], 'KV', 'UTF-8');
            $data[self::BLOCK_KANA] = mb_convert_kana($data[self::BLOCK_KANA], 'KV', 'UTF-8');
            // 独自追加データを初期化
            $data[self::STREET_KANA] = '';
            $data[self::STREET] = '';

            // 町域の記述が複数行にわたっているデータを統合する

            if (isset($dataPending[self::AG_CODE])) {
                // 複数行の続きかどうかを判別
                // 本来 M_BLOCKS を使いたいが、全然当てにならないので、町域のデータの '（' と '）' を探して判別する。
                if ($data[self::ZIP_CODE] == $dataPending[self::ZIP_CODE] && // 郵便番号が同じ
                    $data[self::TOWN] == $dataPending[self::TOWN] && // 市区町村が同じ
                    strpos($dataPending[self::BLOCK], '（') !== false && // 町域が '（' を含む
                    strpos($dataPending[self::BLOCK], '）') === false // 町域が '）' を含まない ... すなわち、まだ行が続く
                ) {
                    // 複数行の継続行（まだ続きがある)
                    // 町域を連結する
                    if ($dataPending[self::BLOCK_KANA] != $data[self::BLOCK_KANA]) {
                        $dataPending[self::BLOCK_KANA] = $dataPending[self::BLOCK_KANA] . $data[self::BLOCK_KANA];
                    }
                    $dataPending[self::BLOCK] = $dataPending[self::BLOCK] . $data[self::BLOCK];
                } else {
                    // 単独行または複数行の最終行
                    // 書き出し
                    $this->outputOneLineToCsv($dstFile, $dataPending);
                    $lineCountDst++;
                    // 次のデータ
                    $dataPending = $data;
                }
            } else {
                // 次のデータ
                $dataPending = $data;
            }
        }
        // 最後のデータ
        if (count($dataPending) > 0) {
            // 書き出し
            $this->outputOneLineToCsv($dstFile, $dataPending);
            $lineCountDst++;
        }

        fclose($srcFile);
        fclose($dstFile);

        echo 'CSV データ正規化完了!!' . "\n";
        echo "\n";
        $this->showMaxLengths();
        echo '$lineCountSrc = ' . $lineCountSrc . "\n";
        echo '$lineCountDst = ' . $lineCountDst . "\n";
        echo '統合数 = ' . ($lineCountSrc - $lineCountDst) . "\n";
        echo "\n";
    }

    /**
     * 一行書き出す
     * @param $dstFile 書き出し先のファイル
     * @param $data 一行のデータ
     */
    private function outputOneLineToCsv($dstFile, $data)
    {
        // 町域を分析して、必要なら、町域と町域詳細とに分割する
        $data = $this->divideBlockData($data);
        // 長さをチェック
        $this->checkMaxLength($data);
        // 書き出し
        fwrite($dstFile, implode(',', $data) . "\n");
    }

    /**
     * 町域を分析して、必要なら、町域と町域詳細とに分割する
     * @param $data 一行分のデータ
     * @return array 変換された $data
     */
    private function divideBlockData($data)
    {
        // 町域 (block) を分析
        // 「以下に掲載がない場合」
        if ($data[self::BLOCK] == '以下に掲載がない場合') {
            $data[self::STREET] = $data[self::BLOCK];
            $data[self::BLOCK_KANA] = '';
            $data[self::BLOCK] = '';
        }
        // 「... の次に番地がくる場合」
        else if (strpos($data[self::BLOCK], 'の次に番地がくる場合') !== false) {
            $data[self::STREET] = $data[self::BLOCK];
            $data[self::BLOCK_KANA] = '';
            $data[self::BLOCK] = '';
        }
        // 「... 一円」
        // ただし、「一円」という地名もあるので、strpos() は使用不可
        else if (preg_match('/^.+一円$/u', $data[self::BLOCK])) {
            $data[self::STREET] = $data[self::BLOCK];
            $data[self::BLOCK_KANA] = '';
            $data[self::BLOCK] = '';
        }
        // BLOCK 全体が複数データの併記または範囲データの記述である場合は、すべてを STREET に移動
        else if (preg_match('/^([^（]*)(、|〜)/u', $data[self::BLOCK])) {
            $data[self::STREET] = $data[self::BLOCK];
            $data[self::STREET_KANA] = $data[self::BLOCK_KANA];
            $data[self::BLOCK] = '';
            $data[self::BLOCK_KANA] = '';
        }
        else {
            // BLOCK_KANA 細分化 ... '(' と ')' に囲まれた部分を BLOCK_KANA から STREET_KANA に移動
            if (preg_match('/(.*)\((.*)\)$/', $data[self::BLOCK_KANA], $matches)) {
                $data[self::BLOCK_KANA] = $matches[1];
                $data[self::STREET_KANA] = $matches[2];
            }
            // BLOCK 細分化 ... '（' と '）' に囲まれた部分を BLOCK から STREET に移動
            if (preg_match('/(.*)（(.*)）$/u', $data[self::BLOCK], $matches)) {
                $data[self::BLOCK] = $matches[1];
                $data[self::STREET] = mb_convert_kana($matches[2], 'a', 'UTF-8');
            }
        }
        return $data;
    }

    /**
     * 最大データ長をチェック
     * @param $data データ
     */
    private function checkMaxLength($data)
    {
        if (mb_strlen($data[self::PREF_KANA], 'UTF-8') > $this->maxLength[self::PREF_KANA])
            $this->maxLength[self::PREF_KANA] = mb_strlen($data[self::PREF_KANA], 'UTF-8');
        if (mb_strlen($data[self::TOWN_KANA], 'UTF-8') > $this->maxLength[self::TOWN_KANA])
            $this->maxLength[self::TOWN_KANA] = mb_strlen($data[self::TOWN_KANA], 'UTF-8');
        if (mb_strlen($data[self::BLOCK_KANA], 'UTF-8') > $this->maxLength[self::BLOCK_KANA])
            $this->maxLength[self::BLOCK_KANA] = mb_strlen($data[self::BLOCK_KANA], 'UTF-8');
        if (mb_strlen($data[self::STREET_KANA], 'UTF-8') > $this->maxLength[self::STREET_KANA])
            $this->maxLength[self::STREET_KANA] = mb_strlen($data[self::STREET_KANA], 'UTF-8');
        if (mb_strlen($data[self::PREF], 'UTF-8') > $this->maxLength[self::PREF])
            $this->maxLength[self::PREF] = mb_strlen($data[self::PREF], 'UTF-8');
        if (mb_strlen($data[self::TOWN], 'UTF-8') > $this->maxLength[self::TOWN])
            $this->maxLength[self::TOWN] = mb_strlen($data[self::TOWN], 'UTF-8');
        if (mb_strlen($data[self::BLOCK], 'UTF-8') > $this->maxLength[self::BLOCK])
            $this->maxLength[self::BLOCK] = mb_strlen($data[self::BLOCK], 'UTF-8');
        if (mb_strlen($data[self::STREET], 'UTF-8') > $this->maxLength[self::STREET])
            $this->maxLength[self::STREET] = mb_strlen($data[self::STREET], 'UTF-8');
    }

    /**
     * 最大データ長を表示
     */
    private function showMaxLengths()
    {
        echo '-- データの最大長' . "\n";
        echo '-- $maxLength[pref_kana] = ' . $this->maxLength[self::PREF_KANA] . "\n";
        echo '-- $maxLength[town_kana] = ' . $this->maxLength[self::TOWN_KANA] . "\n";
        echo '-- $maxLength[block_kana] = ' . $this->maxLength[self::BLOCK_KANA] . "\n";
        echo '-- $maxLength[street_kana] = ' . $this->maxLength[self::STREET_KANA] . "\n";
        echo '-- $maxLength[pref] = ' . $this->maxLength[self::PREF] . "\n";
        echo '-- $maxLength[town] = ' . $this->maxLength[self::TOWN] . "\n";
        echo '-- $maxLength[block] = ' . $this->maxLength[self::BLOCK] . "\n";
        echo '-- $maxLength[street] = ' . $this->maxLength[self::STREET] . "\n";
        echo "\n";
    }

    /**
     * INSERT SQL ファイルの作成
     */
    public function createInsertSqlFiles()
    {
        echo "INSERT SQL ファイル書き出し開始 ... \n";

        // 既存の同名ファイルを削除
        $this->deleteExistingSqlFiles();

        $srcFile = fopen($this->getCookedCsvFilePath(), 'r');
        if (!$srcFile) {
            echo "正規化 CSV ファイルのオープンに失敗。\n";
            return;
        }
        $this->setSqlFileCount(1);
        $dstFileName = $this->getSqlFilePath($this->getSqlFileCount());
        $dstFile = fopen($dstFileName, 'w');
        if (!$dstFile) {
            echo "SQL ファイルのオープンに失敗。\n";
            fclose($srcFile);
            return;
        }
        echo "INSERT SQL ファイル " . $dstFileName . " 書き出し中 ... \n";

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
                    '"' . $data[self::STREET_KANA] . '",' .
                    '"' . $data[self::PREF] . '",' .
                    '"' . $data[self::TOWN] . '",' .
                    '"' . $data[self::BLOCK] . '",' .
                    '"' . $data[self::STREET] . '",' .
                    $data[self::M_ZIPS] . ',' .
                    $data[self::M_BANCHIS] . ',' .
                    $data[self::CHOMES] . ',' .
                    $data[self::M_BLOCKS] .
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
                    echo "SQL ファイルのオープンに失敗。\n";
                    fclose($srcFile);
                    return;
                }
                echo "INSERT SQL ファイル " . $dstFileName . " 書き出し中 ... \n";
            }
            if ($sqlCount == 0) {
                fwrite($dstFile,
                    "INSERT INTO `zip_data` (`ag_code`, `zip_code`, `pref_kana`, `town_kana`, `block_kana`, `street_kana`, `pref`, `town`, `block`, `street`, `m_zips`, `m_banchis`, `chomes`, `m_blocks`) VALUES \n"
                );
            }
            fwrite($dstFile, $sqlLine);
            $lineCount++;
            $sqlCount++;
        }
        fwrite($dstFile, ";\n");
        fclose($dstFile);
        echo "done.\n";
        echo "INSERT SQL ファイル書き出し完了!!\n\n";
    }

    /**
     * DELETE SQL ファイルの作成
     */
    public function createDeleteSqlFiles()
    {
        echo "DELETE SQL ファイル書き出し開始 ... \n";

        // 既存の同名ファイルを削除
        $this->deleteExistingSqlFiles();

        $srcFile = fopen($this->getCookedCsvFilePath(), 'r');
        if (!$srcFile) {
            echo "正規化 CSV ファイルのオープンに失敗。\n";
            return;
        }
        $this->setSqlFileCount(1);
        $dstFileName = $this->getSqlFilePath($this->getSqlFileCount());
        $dstFile = fopen($dstFileName, 'w');
        if (!$dstFile) {
            echo "SQL ファイルのオープンに失敗。\n";
            fclose($srcFile);
            return;
        }
        echo "DELETE SQL ファイル " . $dstFileName . " 書き出し中 ... \n";

        $lineCount = 0;
        while ($line = fgets($srcFile)) {
            $data = explode(',', trim($line));
            $sql_line = 'DELETE FROM `zip_data` WHERE ' .
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
                    echo "SQL ファイルのオープンに失敗。\n";
                    fclose($srcFile);
                    return;
                }
                echo "DELETE SQL ファイル " . $dstFileName . " 書き出し中 ... \n";
                $dstFile = fopen($dstFileName, 'w');
            }
            fwrite($dstFile, $sql_line);
            $lineCount++;
        }
        fclose($dstFile);
        echo "done.\n";
        echo "DELETE SQL ファイル書き出し完了!!\n\n";
    }

    /**
     * 振り仮名辞書を更新
     */
    public function updateKanaDic()
    {
        echo "フリカナ辞書更新開始 ... \n";

        $kanaDic = new KanaDic;

        $srcFile = fopen($this->getCookedCsvFilePath(), 'r');
        if (!$srcFile) {
            echo "正規化 CSV ファイルのオープンに失敗。\n";
            return;
        }
        while ($line = fgets($srcFile)) {
            $words = explode(',', $line);

            $prefKana = trim($words[self::PREF_KANA], '"');
            $pref = trim($words[self::PREF], '"');
            if ($prefKana != '' && $pref != '') {
                $kanaDic->register('pref', '', $pref, $prefKana);
            }

            $agCode = trim($words[self::AG_CODE], '"');

            $townKana = trim($words[self::TOWN_KANA], '"');
            $town = trim($words[self::TOWN], '"');
            if ($townKana != '' && $town != '') {
                $kanaDic->register('town', $agCode, $town, $townKana);
            }

            $blockKana = trim($words[self::BLOCK_KANA], '"');
            $block = trim($words[self::BLOCK], '"');
            if ($blockKana != '' && $block != '') {
                $kanaDic->register('block', $agCode, $block, $blockKana);
            }
        }
        fclose($srcFile);

        $kanaDic->save();

        echo "フリカナ辞書更新完了 !! \n";
    }
}