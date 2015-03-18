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
            echo '郵便番号データ(全県データ) ... 変換開始 ...' . "\n\n";
            $zipDataAll->normalizeCsvData();
            $zipDataAll->updateKanaDic();
            $zipDataAll->createInsertSqlFiles();
            $this->masterSqlFiles = array_merge($this->masterSqlFiles, $zipDataAll->getSqlFileNames());
            echo '郵便番号データ(全県データ) ... 変換完了 !!' . "\n\n";
        } else {
            echo '郵便番号データ(全県データ) ... なし' . "\n\n";
        }
    }

    /**
     * 削除用データがあれば処理する
     */
    private function processDelete()
    {
        $zipDataDelete = new ZipData($this->dataDir, 'DEL_' . $this->yearMonth);
        if ($zipDataDelete->hasRawCsvFile()) {
            echo '郵便番号データ((削除分) ... 変換開始 ...' . "\n\n";
            $zipDataDelete->normalizeCsvData();
            $zipDataDelete->updateKanaDic();
            $zipDataDelete->createDeleteSqlFiles();
            $this->updateSqlFilePaths = array_merge($this->updateSqlFilePaths, $zipDataDelete->getSqlFilePaths());
            echo '郵便番号データ(削除分) ... 変換完了 !!' . "\n\n";
        } else {
            echo '郵便番号データ(削除分) ... なし' . "\n\n";
        }
    }

    /**
     * 追加用データがあれば処理する
     */
    private function processAdd()
    {
        $zipDataAdd = new ZipData($this->dataDir, 'ADD_' . $this->yearMonth);
        if ($zipDataAdd->hasRawCsvFile()) {
            echo '郵便番号データ((追加分) ... 変換開始 ...' . "\n\n";
            $zipDataAdd->normalizeCsvData();
            $zipDataAdd->updateKanaDic();
            $zipDataAdd->createInsertSqlFiles();
            $this->updateSqlFilePaths = array_merge($this->updateSqlFilePaths, $zipDataAdd->getSqlFilePaths());
            echo '郵便番号データ(追加分) ... 変換完了 !!' . "\n\n";
        } else {
            echo '郵便番号データ(追加分) ... なし' . "\n\n";
        }
    }

    /**
     * 事業所データがあれば処理する
     */
    private function processBizAll()
    {
        $zipBizDataAll = new ZipBizData($this->dataDir, 'JIGYOSYO');
        if ($zipBizDataAll->hasRawCsvFile()) {
            echo '大口事業所個別番号データ(全データ) ... 変換開始 ...' . "\n\n";
            $zipBizDataAll->normalizeCsvData();
            $zipBizDataAll->createInsertSqlFiles();
            $this->masterSqlFiles = array_merge($this->masterSqlFiles, $zipBizDataAll->getSqlFileNames());
            echo '大口事業所個別番号データ(全データ) ... 変換完了 !!' . "\n\n";
        } else {
            echo '大口事業所個別番号データ(全データ) ... なし' . "\n\n";
        }
    }

    /**
     * 事業所削除用データがあれば処理する
     */
    private function processBizDelete()
    {
        $zipBizDataDelete = new ZipBizData($this->dataDir, 'JDEL' . $this->yearMonth);
        if ($zipBizDataDelete->hasRawCsvFile()) {
            echo '大口事業所個別番号データ(削除分) ... 変換開始 ...' . "\n\n";
            $zipBizDataDelete->normalizeCsvData();
            $zipBizDataDelete->createDeleteSqlFiles();
            $this->updateSqlFilePaths = array_merge($this->updateSqlFilePaths, $zipBizDataDelete->getSqlFilePaths());
            echo '大口事業所個別番号データ(削除分) ... 変換完了 !!' . "\n\n";
        } else {
            echo '大口事業所個別番号データ(削除分) ... なし' . "\n\n";
        }
    }

    /**
     * 事業所追加用データがあれば処理する
     */
    private function processBizAdd()
    {
        $zipBizDataAdd = new ZipBizData($this->dataDir, 'JADD' . $this->yearMonth);
        if ($zipBizDataAdd->hasRawCsvFile()) {
            echo '大口事業所個別番号データ(追加分) ... 変換開始 ...' . "\n\n";
            $zipBizDataAdd->normalizeCsvData();
            $zipBizDataAdd->createInsertSqlFiles();
            $this->updateSqlFilePaths = array_merge($this->updateSqlFilePaths, $zipBizDataAdd->getSqlFilePaths());
            echo '大口事業所個別番号データ(追加分) ... 変換完了 !!' . "\n\n";
        } else {
            echo '大口事業所個別番号データ(追加分) ... なし' . "\n\n";
        }
    }

    /**
     * マスター SQL ファイルをマスター・データ・ディレクトリにコピーする
     */
    private function copyMasters()
    {
        if (count($this->masterSqlFiles) > 0) {
            echo 'マスター SQL ファイル ... コピー開始 ...' . "\n\n";
            $masterDir = MASTERS_DIR . DIRECTORY_SEPARATOR . $this->yearMonth;
            if (!mkdir($masterDir)) {
                fputs(STDERR, "Failed to make the master directory [$masterDir]\n");
                exit(-1);
            }
            foreach ($this->masterSqlFiles as $src) {
                $srcPath = $this->dataDir . DIRECTORY_SEPARATOR . WORK_SUB_DIR . DIRECTORY_SEPARATOR . $src;
                $dstPath = $masterDir . DIRECTORY_SEPARATOR . $src;
                if (!copy($srcPath, $dstPath)) {
                    fputs(STDERR, "Failed to copy a file [$srcPath] to [$dstPath]\n");
                    exit(-1);
                }
            }
            echo 'マスター SQL ファイル ... コピー完了 !!' . "\n\n";
        }
    }

    /**
     * 更新用 SQL を一つにまとめたファイルを更新データ・ディレクトリに作成する
     */
    private function mergeUpdates()
    {
        if (count($this->updateSqlFilePaths) > 0) {
            echo '更新用単一 SQL ファイル ... 作成開始 ...' . "\n\n";
            $dstFileName = UPDATES_DIR . DIRECTORY_SEPARATOR . "update_" . $this->yearMonth . ".sql";
            if (!unlink($dstFileName)) {
                fputs(STDERR, "Failed to unlink the master updating file [$dstFileName]\n");
                exit(-1);
            }

            foreach ($this->updateSqlFilePaths as $src) {
                if (file_put_contents($dstFileName, file_get_contents($src), FILE_APPEND) === false) {
                    fputs(STDERR, "Failed to update the master updating file [$dstFileName]\n");
                    exit(-1);
                }
            }
            echo '更新用単一 SQL ファイル ... 作成完了 !!' . "\n\n";
        }
    }
}
