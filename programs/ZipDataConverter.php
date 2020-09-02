<?php

/**
 * Class ZipDataConverter
 */
class ZipDataConverter
{
    /**
     * @var string  対象の年月
     */
    private $yearMonth;

    /**
     * @var string データ・ディレクトリ
     */
    private $dataDir;

    /**
     * @var array マスター SQL ファイル名
     */
    private $masterSqlFilePaths = array();

    /**
     * @var array 更新用 SQL ファイル名
     */
    private $updateSqlFilePaths = array();

    /**
     * コンストラクタ
     * @param string $yearMonth 年月
     * @param string $dir データ・ディレクトリ
     */
    public function __construct($yearMonth, $dir)
    {
        if (!preg_match('/(\d\d)([01]\d)/', $yearMonth, $matches)) {
            throw  new Exception('Invalid parameter specified for year and month.');
        }
        $m = intval($matches[2]);
        if ($m < 1 || $m > 12 ) {
            throw  new Exception('Invalid parameter specified for month.');
        }
        $this->yearMonth = $yearMonth;

        if (!file_exists($dir)) {
            throw  new Exception('Invalid parameter specified for directory.');
        }
        $this->dataDir = $dir . DIRECTORY_SEPARATOR . $yearMonth;
    }

    /**
     * 変換実行
     */
    public function runConversion()
    {
        $this->processKenAll();
        $this->processBizAll();
        $this->copyMasters();

        $this->processDelete();
        $this->processAdd();
        $this->processBizDelete();
        $this->processBizAdd();

        $this->mergeUpdates();
    }

    /**
     * 全県データがあれば処理する
     */
    private function processKenAll()
    {
        $zipDataAll = new ZipData($this->dataDir, 'KEN_ALL');
        if ($zipDataAll->processData(false, 'all')) {
            $zipDataAll->updateKanaDic();
            $this->masterSqlFilePaths = array_merge($this->masterSqlFilePaths, $zipDataAll->getSqlFileNames());
        }
    }

    /**
     * 削除用データがあれば処理する
     */
    private function processDelete()
    {
        $zipDataDelete = new ZipData($this->dataDir, 'DEL_' . $this->yearMonth);
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
        $zipDataAdd = new ZipData($this->dataDir, 'ADD_' . $this->yearMonth);
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
        $zipBizDataAll = new ZipBizData($this->dataDir, 'JIGYOSYO');
        if ($zipBizDataAll->processData(true, 'all')) {
            $this->masterSqlFilePaths = array_merge($this->masterSqlFilePaths, $zipBizDataAll->getSqlFileNames());
        }
    }

    /**
     * 事業所削除用データがあれば処理する
     */
    private function processBizDelete()
    {
        $zipBizDataDelete = new ZipBizData($this->dataDir, 'JDEL' . $this->yearMonth);
        if ($zipBizDataDelete->processData(true, 'del')) {
            $this->updateSqlFilePaths = array_merge($this->updateSqlFilePaths, $zipBizDataDelete->getSqlFilePaths());
        }
    }

    /**
     * 事業所追加用データがあれば処理する
     */
    private function processBizAdd()
    {
        $zipBizDataAdd = new ZipBizData($this->dataDir, 'JADD' . $this->yearMonth);
        if ($zipBizDataAdd->processData(true, 'add')) {
            $this->updateSqlFilePaths = array_merge($this->updateSqlFilePaths, $zipBizDataAdd->getSqlFilePaths());
        }
    }

    /**
     * マスター SQL ファイルをマスター・データ・ディレクトリにコピーする
     */
    private function copyMasters()
    {
        if (count($this->masterSqlFilePaths) > 0) {
            echo "Master SQL file ... copying ... ";
            $masterDir = MASTERS_DIR . DIRECTORY_SEPARATOR . $this->yearMonth;
            self::makeReadyDir($masterDir, "master directory");
            $no = 1;
            foreach ($this->masterSqlFilePaths as $src) {
                $srcPath = $this->dataDir . DIRECTORY_SEPARATOR . WORK_SUB_DIR . DIRECTORY_SEPARATOR . $src;
                $dstPath = $masterDir . DIRECTORY_SEPARATOR . sprintf('%02d-', $no) . $src;
                if (!copy($srcPath, $dstPath)) {
                    echo "\n";
                    fputs(STDERR, "Failed to copy a file [$srcPath] to [$dstPath]\n");
                    exit(-1);
                }
                if ($no == 1) {
                    $this->prependDataInit($dstPath);
                    $prependDone = true;
                }
                $no++;
            }
            $this->appendFlagUpdate($dstPath);
            $this->appendHist($dstPath);
            echo "done.\n";
            echo "\n";
        }
    }

    public static function makeReadyDir($dir, $dirName)
    {
        if (!file_exists($dir)) {
            if (!mkdir($dir)) {
                echo "\n";
                fputs(STDERR, "Failed to make the $dirName [$dir]\n");
                exit(-1);
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
            $dstFileName = UPDATES_DIR . DIRECTORY_SEPARATOR . "update_" . $this->yearMonth . ".sql";
            if (file_exists($dstFileName)) {
                if (!unlink($dstFileName)) {
                    echo "\n";
                    fputs(STDERR, "Failed to unlink the master updating file [$dstFileName]\n");
                    exit(-1);
                }
            }

            foreach ($this->updateSqlFilePaths as $src) {
                if (file_put_contents($dstFileName, file_get_contents($src), FILE_APPEND) === false) {
                    echo "\n";
                    fputs(STDERR, "Failed to create the single SQL file for updating [$dstFileName]\n");
                    exit(-1);
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
        $srcFile = SQLS_DIR . DIRECTORY_SEPARATOR . "zip_data_init.sql";
        $prepend = file_get_contents($srcFile);
        $contents = file_get_contents($dstFile);
        if (file_put_contents($dstFile, $prepend . "\n" . $contents) === false) {
            echo "\n";
            fputs(STDERR, "Failed to prepend the initializing SQL [$dstFile]\n");
            exit(-1);
        }
    }

    /**
     * @param $dstFile
     * フラグ更新 SQL を末尾に追加する
     */
    private function appendFlagUpdate($dstFile)
    {
        $srcFile = SQLS_DIR . DIRECTORY_SEPARATOR . "zip_data_flag_update.sql";
        if (file_put_contents($dstFile, file_get_contents($srcFile), FILE_APPEND) === false) {
            echo "\n";
            fputs(STDERR, "Failed to append the flag updating SQLs [$dstFile]\n");
            exit(-1);
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
