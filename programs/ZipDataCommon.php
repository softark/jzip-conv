<?php

/**
 * 郵便番号データ（共通部分）
 */
class ZipDataCommon
{
    /**
     * 郵便番号データ
     */
    const KEN_ALL_DATA = 'ken_all';
    const ADD_DATA_PREFIX = 'add_';
    const DEL_DATA_PREFIX = 'del_';

    /**
     * 個別事業所データ
     */
    const JG_ALL_DATA = 'jigyosyo';
    const JG_ADD_DATA_PREFIX = 'jadd';
    const JG_DEL_DATA_PREFIX = 'jdel';

    /**
     * 動作モード
     */
    const MODE_ALL = 'all';
    const MODE_DEL = 'del';
    const MODE_ADD = 'add';

    /**
     * ワーク・ディレクトリ
     */
    const WORK_DIR = 'work';

    /**
     * INSERT 文の行数
     */
    const LINES_PER_SQL = 40;

    /**
     * 1 SQL ファイルあたりの行数 ... アップロード可能なファイル・サイズに合わせて調節
     */
    const LINES_PER_SQL_FILE = 22000;

    /**
     * 1 SQL ファイルあたりの行数（事業所）
     */
    const LINES_PER_SQL_FILE_BIZ = 13000;

    /**
     * @var string データ・ディレクトリ
     */
    private $_dataDir = '';

    /**
     * @return string データ・ディレクトリ
     */
    public function getDataDir()
    {
        return $this->_dataDir;
    }

    /**
     * @var string データ名
     */
    private $_dataName = '';

    /**
     * @return string データ名（拡張子を除いたファイル名）
     */
    public function getDataName()
    {
        return $this->_dataName;
    }

    /**
     * @var int SQL ファイル数
     */
    private $_sqlFileCount = 1;

    /**
     * @return int SQL ファイル数
     */
    public function getSqlFileCount()
    {
        return $this->_sqlFileCount;
    }

    /**
     * @param int $count SQL ファイル数
     */
    public function setSqlFileCount($count)
    {
        $this->_sqlFileCount = $count;
    }

    /**
     * コンストラクタ
     * @param string $dataDir データ・ディレクトリ
     * @param string $dataName データ名 (拡張子を除いたソース CSV ファイル名)
     */
    public function __construct($dataDir, $dataName)
    {
        $this->_dataDir = $dataDir;
        $this->_dataName = $dataName;
    }

    /**
     * @return string 生 CSV ファイル名
     */
    protected function getRawCsvFileName()
    {
        return strtoupper($this->getDataName() . '.CSV');
    }

    /**
     * @return string 生 CSV ファイル・パス
     */
    protected function getRawCsvFilePath()
    {
        return $this->getDataDir() . DIRECTORY_SEPARATOR . $this->getRawCsvFileName();
    }

    /**
     * @return bool  生 CSV ファイルがあるかどうか
     */
    public function hasRawCsvFile()
    {
        return file_exists($this->getRawCsvFilePath());
    }

    /**
     * @return string 正規化 CSV ファイル名
     */
    protected function getCookedCsvFileName()
    {
        return $this->getDataName() . '_cooked.csv';
    }

    /**
     * @return string 正規化 CSV ファイル・パス
     */
    protected function getCookedCsvFilePath()
    {
        $this->makeReadyWorkDir();
        return $this->getDataDir() . DIRECTORY_SEPARATOR . self::WORK_DIR . DIRECTORY_SEPARATOR . $this->getCookedCsvFileName();
    }

    /**
     * @param int $fileNo ファイル番号
     * @return string SQL ファイル名
     */
    public function getSqlFileName($fileNo)
    {
        return $this->getDataName() . '-' . sprintf("%02d", $fileNo) . '.sql';
    }

    /**
     * @param int $fileNo ファイル番号
     * @return string SQL ファイル・パス
     */
    public function getSqlFilePath($fileNo)
    {
        $this->makeReadyWorkDir();
        return $this->getDataDir() . DIRECTORY_SEPARATOR . self::WORK_DIR . DIRECTORY_SEPARATOR . $this->getSqlFileName($fileNo);
    }

    /**
     * @return array SQL ファイル名
     */
    public function getSqlFileNames()
    {
        $fileNames = array();
        for ($n = 1; $n <= $this->getSqlFileCount(); $n++) {
            $fileNames[] = $this->getSqlFileName($n);
        }
        return $fileNames;
    }

    /**
     * @return array SQL ファイル・パス
     */
    public function getSqlFilePaths()
    {
        $this->makeReadyWorkDir();
        $filePaths = array();
        for ($n = 1; $n <= $this->getSqlFileCount(); $n++) {
            $filePaths[] = $this->getSqlFilePath($n);
        }
        return $filePaths;
    }

    /**
     * 既存の SQL ファイルを削除する
     */
    protected function deleteExistingSqlFiles()
    {
        // 既存の同名ファイルを削除
        $workDir = $this->getDataDir();
        $dir = opendir($workDir);
        $search = '/' . $this->getDataName() . '-\d\d\.sql$/';
        while ($dstFileName = readdir($dir)) {
            if (preg_match($search, $dstFileName)) {
                if (!unlink($workDir . DIRECTORY_SEPARATOR . $dstFileName)) {
                    fputs(STDERR, "Failed to unlink a file [$dstFileName].\n");
                }
            }
        }
    }

    /**
     * ワーク・ディレクトリを準備する
     */
    private function makeReadyWorkDir()
    {
        $workDir = $this->getDataDir() . DIRECTORY_SEPARATOR . self::WORK_DIR;
        ZipDataConverter::makeReadyDir($workDir, "working directory");
    }

    /**
     * @param $biz boolean 事業所データなら true
     * @param $mode string 動作モード : self::MODE_ALL, self::MODE_DEL or self::MODE_ADD
     * @return bool
     */
    public function processData($biz, $mode)
    {
        $data = $biz ? 'Biz zip data' : 'Zip data';
        if ($this->hasRawCsvFile()) {
            echo "$data ($mode) ... converting ...\n";
            echo "\n";
            $this->normalizeCsvData();
            $maxlines = $biz ? self::LINES_PER_SQL_FILE_BIZ : self::LINES_PER_SQL_FILE;
            if ($mode === self::MODE_ADD || $mode === self::MODE_ALL) {
                $this->createInsertSqlFiles($maxlines);
            } else {
                $this->createDeleteSqlFiles($maxlines);
            }
            echo "$data ($mode) ... conversion completed.\n";
            echo "\n";
            return true;
        } else {
            echo "$data ($mode) ... no data.\n";
            echo "\n";
            return false;
        }
    }

    /**
     * CSV データを正規化する
     */
    public function normalizeCsvData()
    {
        echo "Normalizing CSV data ... ";
        echo "from [{$this->getRawCsvFilePath()}] ";
        echo "to [{$this->getCookedCsvFilePath()}] ... ";

        /** @var $srcFile resource 変換元 CSV ファイル */
        $srcFile = fopen($this->getRawCsvFilePath(), 'r');
        if (!$srcFile) {
            echo "\n";
            echo "Failed to open the source CSV file.\n";
            return;
        }
        /** @var $dstFile resource 変換先 CSV ファイル */
        $dstFile = fopen($this->getCookedCsvFilePath(), 'w');
        if (!$dstFile) {
            echo "\n";
            echo "Failed to open the normalized CSV file.\n";
            fclose($srcFile);
            return;
        }

        // データを正規化
        list($lineCountSrc, $lineCountDst) = $this->normalizeData($srcFile, $dstFile);

        fclose($srcFile);
        fclose($dstFile);

        echo "done.\n";
        $this->showMaxLengths();
        echo "The source line count = $lineCountSrc\n";
        echo "The destination line count = $lineCountDst\n";
        $diff = $lineCountSrc - $lineCountDst;
        echo "Unified line count = $diff\n";
        echo "\n";
    }

    /**
     * データを正規化する
     * @param $srcFile resource ソースの CSV ファイル
     * @param $dstFile resource デスティネーションの CSV ファイル
     * @return array
     */
    protected function normalizeData($srcFile, $dstFile)
    {
        return array(0, 0);
    }

    /**
     * 一行のデータを取得する
     * @param $line string 一行のデータ
     * @return array
     */
    protected function getDataFromLine($line)
    {
        // SHIFT JIS --> UTF-8
        $line = mb_convert_encoding(trim($line), 'UTF-8', 'shift_jis');
        // カンマで分割
        $data = explode(',', $line);
        // 引用符と空白を削除
        $len = count($data);
        for ($n = 0; $n < $len; $n++) {
            $data[$n] = trim($data[$n], "\" ");
        }
        return $data;
    }

    /**
     * 文字列の長さを既存の最大値と比べて、最大値を更新する
     * @param array $srcData
     * @param array $maxLenData
     * @param int[] $indexes
     */
    public function checkStrLength($srcData, &$maxLenData, $indexes)
    {
        foreach ($indexes as $index) {
            if (($len = mb_strlen($srcData[$index], 'UTF-8')) > $maxLenData[$index]) {
                $maxLenData[$index] = $len;
            }
        }
    }

    /**
     * @param integer $maxlines
     * INSERT SQL ファイルの作成
     */
    public function createInsertSqlFiles($maxlines)
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
        echo "Writing the INSERT SQL file [$dstFileName] ... ";

        $lineCount = 0;
        $sqlCount = 0;
        while ($line = fgets($srcFile)) {
            $data = explode(',', trim($line));
            $sqlLine = $this->getInsSqlValue($data);
            if ($sqlCount > 0) {
                if ($sqlCount < self::LINES_PER_SQL) {
                    fwrite($dstFile, ",\n");
                } else {
                    fwrite($dstFile, ";\n");
                    $sqlCount = 0;
                }
            }
            if ($maxlines > 0 && $lineCount >= $maxlines) {
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
                echo "Writing the INSERT SQL file [$dstFileName] ... ";
            }
            if ($sqlCount == 0) {
                fwrite($dstFile, $this->getInsSql());
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

    protected function getInsSql()
    {
        return '';
    }

    protected function getInsSqlValue($data)
    {
        return '';
    }

    /**
     * @param integer $maxlines
     * DELETE SQL ファイルの作成
     */
    public function createDeleteSqlFiles($maxlines)
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
            $sqlLine = $this->getDelSql($data);
            if ($maxlines > 0 && $lineCount >= $maxlines) {
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
                echo "Writing the DELETE SQL file [$dstFileName] ... ";
            }
            fwrite($dstFile, $sqlLine);
            $lineCount++;
        }
        fclose($dstFile);
        echo "done.\n";
        echo "\n";
    }

    protected function getDelSql($data)
    {
        return '';
    }
}