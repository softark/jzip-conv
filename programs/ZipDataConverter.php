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
     * @var array 更新用 SQL ファイル名
     */
    private $masterSqlFiles = array();

    /**
     * @var array 更新用 SQL ファイル名
     */
    private $updateSqlFilePaths = array();

    /**
     * コンストラクタ
     * @param string $yearMonth 年月
     */
    public function __construct($yearMonth)
    {
        $this->yearMonth = $yearMonth;
        $this->dataDir = DATA_DIR . DIRECTORY_SEPARATOR . $yearMonth;
    }

    /**
     * 変換実行
     */
    public function runConversion()
    {
        $this->processKenAll();
        $this->processDelete();
        $this->processAdd();
        $this->processBizAll();
        $this->processBizDelete();
        $this->processBizAdd();

        $this->copyMasters();
        $this->mergeUpdates();
    }

    /**
     * 全県データがあれば処理する
     */
    private function processKenAll()
    {
        $zipDataAll = new ZipData($this->dataDir, 'KEN_ALL');
        if ($zipDataAll->hasRawCsvFile()) {
            echo "Zip data (all) ... converting ...\n";
            echo "\n";
            $zipDataAll->normalizeCsvData();
            $zipDataAll->updateKanaDic();
            $zipDataAll->createInsertSqlFiles();
            $this->masterSqlFiles = array_merge($this->masterSqlFiles, $zipDataAll->getSqlFileNames());
            echo "Zip data (all) ... conversion completed.\n";
        } else {
            echo "Zip data (all) ... no data.\n";
        }
        echo "\n";
    }

    /**
     * 削除用データがあれば処理する
     */
    private function processDelete()
    {
        $zipDataDelete = new ZipData($this->dataDir, 'DEL_' . $this->yearMonth);
        if ($zipDataDelete->hasRawCsvFile()) {
            echo "Zip data (deleted) ... converting ...\n";
            echo "\n";
            $zipDataDelete->normalizeCsvData();
            $zipDataDelete->updateKanaDic();
            $zipDataDelete->createDeleteSqlFiles();
            $this->updateSqlFilePaths = array_merge($this->updateSqlFilePaths, $zipDataDelete->getSqlFilePaths());
            echo "Zip data (deleted) ... conversion completed.\n";
        } else {
            echo "Zip data (deleted) ... no data.\n";
        }
        echo "\n";
    }

    /**
     * 追加用データがあれば処理する
     */
    private function processAdd()
    {
        $zipDataAdd = new ZipData($this->dataDir, 'ADD_' . $this->yearMonth);
        if ($zipDataAdd->hasRawCsvFile()) {
            echo "Zip data (added) ... converting ...\n";
            echo "\n";
            $zipDataAdd->normalizeCsvData();
            $zipDataAdd->updateKanaDic();
            $zipDataAdd->createInsertSqlFiles();
            $this->updateSqlFilePaths = array_merge($this->updateSqlFilePaths, $zipDataAdd->getSqlFilePaths());
            echo "Zip data (added) ... conversion completed.\n";
        } else {
            echo "Zip data (added) ... no data.\n";
        }
        echo "\n";
    }

    /**
     * 事業所データがあれば処理する
     */
    private function processBizAll()
    {
        $zipBizDataAll = new ZipBizData($this->dataDir, 'JIGYOSYO');
        if ($zipBizDataAll->hasRawCsvFile()) {
            echo "Business zip data (all) ... converting ...\n";
            echo "\n";
            $zipBizDataAll->normalizeCsvData();
            $zipBizDataAll->createInsertSqlFiles();
            $this->masterSqlFiles = array_merge($this->masterSqlFiles, $zipBizDataAll->getSqlFileNames());
            echo "Business zip data (all) ... conversion completed.\n";
        } else {
            echo "Business zip data (all) ... no data.\n";
        }
        echo "\n";
    }

    /**
     * 事業所削除用データがあれば処理する
     */
    private function processBizDelete()
    {
        $zipBizDataDelete = new ZipBizData($this->dataDir, 'JDEL' . $this->yearMonth);
        if ($zipBizDataDelete->hasRawCsvFile()) {
            echo "Business zip data (deleted) ... converting ...\n";
            echo "\n";
            $zipBizDataDelete->normalizeCsvData();
            $zipBizDataDelete->createDeleteSqlFiles();
            $this->updateSqlFilePaths = array_merge($this->updateSqlFilePaths, $zipBizDataDelete->getSqlFilePaths());
            echo "Business zip data (deleted) ... conversion completed.\n";
        } else {
            echo "Business zip data (deleted) ... no data.\n";
        }
        echo "\n";
    }

    /**
     * 事業所追加用データがあれば処理する
     */
    private function processBizAdd()
    {
        $zipBizDataAdd = new ZipBizData($this->dataDir, 'JADD' . $this->yearMonth);
        if ($zipBizDataAdd->hasRawCsvFile()) {
            echo "Business zip data (added) ... converting ...\n";
            echo "\n";
            $zipBizDataAdd->normalizeCsvData();
            $zipBizDataAdd->createInsertSqlFiles();
            $this->updateSqlFilePaths = array_merge($this->updateSqlFilePaths, $zipBizDataAdd->getSqlFilePaths());
            echo "Business zip data (added) ... conversion completed.\n";
        } else {
            echo "Business zip data (added) ... no data.\n";
        }
        echo "\n";
    }

    /**
     * マスター SQL ファイルをマスター・データ・ディレクトリにコピーする
     */
    private function copyMasters()
    {
        if (count($this->masterSqlFiles) > 0) {
            echo "Master SQL file ... copying ... ";
            $masterDir = MASTERS_DIR . DIRECTORY_SEPARATOR . $this->yearMonth;
            if (!file_exists($masterDir)) {
                if (!mkdir($masterDir)) {
                    echo "\n";
                    fputs(STDERR, "Failed to make the master directory [$masterDir]\n");
                    exit(-1);
                }
            }
            foreach ($this->masterSqlFiles as $src) {
                $srcPath = $this->dataDir . DIRECTORY_SEPARATOR . WORK_SUB_DIR . DIRECTORY_SEPARATOR . $src;
                $dstPath = $masterDir . DIRECTORY_SEPARATOR . $src;
                if (!copy($srcPath, $dstPath)) {
                    echo "\n";
                    fputs(STDERR, "Failed to copy a file [$srcPath] to [$dstPath]\n");
                    exit(-1);
                }
            }
            echo "done.\n";
            echo "\n";
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
            echo "done.\n";
            echo "\n";
        }
    }
}
