<?php

/**
 * Class ZipDataConverter
 */
class ZipDataConverter
{
    /**
     * @var string  操作モード
     */
    private $operationMode;

    /**
     * @var string  対象の年月
     */
    private $yearMonth;

    /**
     * @var string インプット・ディレクトリ
     */
    private $inputDir;

    /**
     * @var string マスター用アウトプット・ディレクトリ
     */
    private $outputDirForMaster;

    /**
     * @var string 更新用アウトプット・ディレクトリ
     */
    private $outputDirForUpdate;

    /**
     * @var string SQL ディレクトリ
     */
    private $sqls_dir;

    /**
     * @var array マスター SQL ファイル
     */
    private $masterSqlFiles = [];

    /**
     * @var array 更新用 SQL ファイル名
     */
    private $updateSqlFilePaths = [];

    /**
     * コンストラクタ
     * @param string $yearMonth 年月
     * @param string $operationMode 操作モード
     */
    public function __construct($yearMonth, $operationMode)
    {
        // 年月の設定
        if (!preg_match('/(\d\d)([01]\d)/', $yearMonth, $matches)) {
            throw  new Exception('Invalid parameter specified for year and month.');
        }
        $m = intval($matches[2]);
        if ($m < 1 || $m > 12 ) {
            throw  new Exception('Invalid parameter specified for month.');
        }
        $this->yearMonth = $yearMonth;

        $baseDir = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;

        // インプット・ディレクトリの設定
        $this->inputDir = $baseDir . 'data' . DIRECTORY_SEPARATOR . $yearMonth;

        // アウトプット・ディレクトリの設定
        $this->outputDirForMaster = $baseDir . 'outputs' . DIRECTORY_SEPARATOR . 'masters' . DIRECTORY_SEPARATOR . $yearMonth;
        $this->outputDirForUpdate = $baseDir . 'outputs' . DIRECTORY_SEPARATOR . 'updates';

        // SQL ディレクトリ
        $this->sqls_dir = $baseDir . 'sqls';

        // 操作モード
        $this->operationMode = $operationMode;
    }

    /**
     * 変換実行
     */
    public function runConversion()
    {
        // ALL
        if ($this->operationMode == ZipDataDownloader::DOWNLOAD_ALL || $this->operationMode == ZipDataDownloader::DOWNLOAD_BOTH) {
            $this->processKenAll();
            $this->processBizAll();
            $this->copyMasters();
        }

        // DIFF
        if ($this->operationMode == ZipDataDownloader::DOWNLOAD_DIFF || $this->operationMode == ZipDataDownloader::DOWNLOAD_BOTH) {
            $this->processDelete();
            $this->processAdd();
            $this->processBizDelete();
            $this->processBizAdd();
            $this->mergeUpdates();
        }
    }

    /**
     * 全県データがあれば処理する
     */
    private function processKenAll()
    {
        $zipDataAll = new ZipData($this->inputDir, ZipDataCommon::KEN_ALL_DATA);
        if ($zipDataAll->processData(false, 'all')) {
            $zipDataAll->updateKanaDic();
            $this->masterSqlFiles = array_merge($this->masterSqlFiles, $zipDataAll->getSqlFileNames());
        }
    }

    /**
     * 削除用データがあれば処理する
     */
    private function processDelete()
    {
        $zipDataDelete = new ZipData($this->inputDir, ZipDataCommon::DEL_DATA_PREFIX . $this->yearMonth);
        if ($zipDataDelete->processData(false, 'del')) {
            $zipDataDelete->updateKanaDic();
            $this->updateSqlFilePaths = array_merge($this->updateSqlFilePaths, $zipDataDelete->getSqlFilePaths());
        }
    }

    /**
     * 追加用データがあれば処理する
     */
    private function processAdd()
    {
        $zipDataAdd = new ZipData($this->inputDir, ZipDataCommon::ADD_DATA_PREFIX . $this->yearMonth);
        if ($zipDataAdd->processData(false, 'add')) {
            $zipDataAdd->updateKanaDic();
            $this->updateSqlFilePaths = array_merge($this->updateSqlFilePaths, $zipDataAdd->getSqlFilePaths());
        }
    }

    /**
     * 事業所データがあれば処理する
     */
    private function processBizAll()
    {
        $zipBizDataAll = new ZipBizData($this->inputDir, ZipDataCommon::JG_ALL_DATA);
        if ($zipBizDataAll->processData(true, 'all')) {
            $this->masterSqlFiles = array_merge($this->masterSqlFiles, $zipBizDataAll->getSqlFileNames());
        }
    }

    /**
     * 事業所削除用データがあれば処理する
     */
    private function processBizDelete()
    {
        $zipBizDataDelete = new ZipBizData($this->inputDir, ZipDataCommon::JG_DEL_DATA_PREFIX . $this->yearMonth);
        if ($zipBizDataDelete->processData(true, 'del')) {
            $this->updateSqlFilePaths = array_merge($this->updateSqlFilePaths, $zipBizDataDelete->getSqlFilePaths());
        }
    }

    /**
     * 事業所追加用データがあれば処理する
     */
    private function processBizAdd()
    {
        $zipBizDataAdd = new ZipBizData($this->inputDir, ZipDataCommon::JG_ADD_DATA_PREFIX . $this->yearMonth);
        if ($zipBizDataAdd->processData(true, 'add')) {
            $this->updateSqlFilePaths = array_merge($this->updateSqlFilePaths, $zipBizDataAdd->getSqlFilePaths());
        }
    }

    /**
     * マスター SQL ファイルをマスター・データ・ディレクトリにコピーする
     */
    private function copyMasters()
    {
        if (count($this->masterSqlFiles) > 0) {
            echo "Master SQL files ... copying ... ";
            self::makeReadyDir($this->outputDirForMaster, "master directory");
            $no = 1;
            foreach ($this->masterSqlFiles as $src) {
                $srcPath = $this->inputDir . DIRECTORY_SEPARATOR . 'work' . DIRECTORY_SEPARATOR . $src;
                $dstPath = $this->outputDirForMaster . DIRECTORY_SEPARATOR . sprintf('%02d-', $no) . $src;
                if (!copy($srcPath, $dstPath)) {
                    throw new Exception("Failed to copy a file [$srcPath] to [$dstPath]");
                }
                if ($no == 1) {
                    $this->prependDataInit($dstPath);
                }
                $no++;
            }
            $this->appendFlagUpdate($dstPath);
            $this->appendHist($dstPath);
            echo "done.\n";
            echo "\n";

            // 単一のファイルを作成する
            echo "Single Master SQL file ... creating ... ";
            $dstFileName = $this->outputDirForMaster . DIRECTORY_SEPARATOR . '00-zipdata.sql';
            if (file_exists($dstFileName)) {
                if (!unlink($dstFileName)) {
                    throw new Exception("Failed to unlink the single master file [$dstFileName]");
                }
            }

            foreach ($this->masterSqlFiles as $src) {
                $srcPath = $this->inputDir . DIRECTORY_SEPARATOR . 'work' . DIRECTORY_SEPARATOR . $src;
                if (file_put_contents($dstFileName, file_get_contents($srcPath), FILE_APPEND) === false) {
                    throw new Exception("Failed to create the single SQL file for updating [$dstFileName]");
                }
            }
            $this->prependDataInit($dstFileName);
            $this->appendFlagUpdate($dstFileName);
            $this->appendHist($dstFileName);
            echo "done.\n";
            echo "\n";
        }
    }

    /**
     * @param $dir string ディレクトリ
     * @param $dirName string ディレクトリの説明
     * ディレクトリを準備する
     */
    public static function makeReadyDir($dir, $dirName)
    {
        if (!file_exists($dir)) {
            if (!mkdir($dir)) {
                throw new Exception("Failed to make the $dirName [$dir]");
            }
        }
    }

    /**
     * 更新用 SQL を一つにまとめたファイルを更新データ・ディレクトリに作成する
     */
    private function mergeUpdates()
    {
        if (count($this->updateSqlFilePaths) > 0) {
            echo "Single SQL file for updating ... creating ... ";
            $dstFileName = $this->outputDirForUpdate  . DIRECTORY_SEPARATOR . "update_" . $this->yearMonth . ".sql";
            if (file_exists($dstFileName)) {
                if (!unlink($dstFileName)) {
                    throw new Exception("Failed to unlink the master updating file [$dstFileName]");
                }
            }

            foreach ($this->updateSqlFilePaths as $src) {
                if (file_put_contents($dstFileName, file_get_contents($src), FILE_APPEND) === false) {
                    throw new Exception("Failed to create the single SQL file for updating [$dstFileName]");
                }
            }
            $this->appendFlagUpdate($dstFileName);
            $this->appendHist($dstFileName);
            echo "done.\n";
            echo "\n";
        }
    }

    /**
     * @param $dstFile
     * データ初期化 SQL を先頭に挿入する
     */
    private function prependDataInit($dstFile)
    {
        $srcFile = $this->sqls_dir . DIRECTORY_SEPARATOR . "zip_data_init.sql";
        $prepend = file_get_contents($srcFile);
        $contents = file_get_contents($dstFile);
        if (file_put_contents($dstFile, $prepend . "\n" . $contents) === false) {
            throw new Exception("Failed to prepend the initializing SQL [$dstFile]");
        }
    }

    /**
     * @param $dstFile
     * フラグ更新 SQL を末尾に追加する
     */
    private function appendFlagUpdate($dstFile)
    {
        $srcFile = $this->sqls_dir . DIRECTORY_SEPARATOR . "zip_data_flag_update.sql";
        if (file_put_contents($dstFile, file_get_contents($srcFile), FILE_APPEND) === false) {
            throw new Exception("Failed to append the flag updating SQLs [$dstFile]");
        }
    }

    /**
     * @param $dstFile
     * 履歴を更新する SQL を末尾に追加する
     */
    private function appendHist($dstFile)
    {
        $file = fopen($dstFile, "a");
        fwrite($file, "\ninsert into `zip_hist` (`ym`) values (\"$this->yearMonth\");\n");
        fclose($file);
    }
}
