<?php

/**
 * 郵便番号データ（共通部分）
 */
class ZipDataCommon
{
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
     * @param string $dataDir ワーク・ディレクトリ
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
        return $this->getDataName() . '.CSV';
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
        return $this->getDataDir() . DIRECTORY_SEPARATOR . WORK_SUB_DIR . DIRECTORY_SEPARATOR . $this->getCookedCsvFileName();
    }

    /**
     * @param int $fileNo ファイル番号
     * @return string SQL ファイル名
     */
    public function getSqlFileName($fileNo)
    {
        return $this->getDataName() . '-'  . sprintf("%02d", $fileNo) . '.sql';
    }

    /**
     * @param int $fileNo ファイル番号
     * @return string SQL ファイル・パス
     */
    public function getSqlFilePath($fileNo)
    {
        $this->makeReadyWorkDir();
        return $this->getDataDir() . DIRECTORY_SEPARATOR . WORK_SUB_DIR . DIRECTORY_SEPARATOR . $this->getSqlFileName($fileNo);
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
        $workDir = $this->getDataDir() . DIRECTORY_SEPARATOR . WORK_SUB_DIR;
        if (!file_exists($workDir)) {
            if (!mkdir($workDir)) {
                fputs(STDERR, "Failed to make working directory [$workDir].\n");
                exit(-1);
            }
        }
    }

    /**
     * 文字列の長さを既存の最大値と比べて、最大値を更新する
     * @param array $srcData
     * @param array $maxData
     * @param int $index
     */
    public function checkStrLength($srcData, &$maxLenData, $index)
    {
        if (($len = mb_strlen($srcData[$index], 'UTF-8')) > $maxLenData[$index]) {
            $maxLenData[$index] = $len;
        }
    }
}