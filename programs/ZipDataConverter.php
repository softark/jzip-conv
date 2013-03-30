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
     * コンストラクタ
     * @param $yearMonth 年月
     */
    public function __construct($yearMonth)
    {
        $this->yearMonth = $yearMonth;
    }

    /**
     * 変換実行
     */
    public function runConversion()
    {
        /** @var $yearMonth string 対象の年月 */
        $yearMonth = $this->yearMonth;
        /** @var $dataDir string データ・ディレクトリ */
        $dataDir = DATA_DIR . DIRECTORY_SEPARATOR . $yearMonth;
        /** @var $updateSqlFilePaths 更新用 SQL ファイル名 */
        $masterSqlFiles = array();
        /** @var $updateSqlFiles 更新用 SQL ファイル名 */
        $updateSqlFilePaths = array();

        // 全県データがあれば処理する
        $zipDataAll = new ZipData($dataDir, 'KEN_ALL');
        if ($zipDataAll->hasRawCsvFile()) {
            echo '郵便番号データ(全県データ) ... 変換開始 ...' . "\n\n";
            $zipDataAll->normalizeCsvData();
            $zipDataAll->updateKanaDic();
            $zipDataAll->createInsertSqlFiles();
            $masterSqlFiles = array_merge($masterSqlFiles, $zipDataAll->getSqlFileNames());
            echo '郵便番号データ(全県データ) ... 変換完了 !!' . "\n\n";
        } else {
            echo '郵便番号データ(全県データ) ... なし' . "\n\n";
        }

        // 削除用データがあれば処理する
        $zipDataDelete = new ZipData($dataDir, 'DEL_' . $yearMonth);
        if ($zipDataDelete->hasRawCsvFile()) {
            echo '郵便番号データ((削除分) ... 変換開始 ...' . "\n\n";
            $zipDataDelete->normalizeCsvData();
            $zipDataDelete->updateKanaDic();
            $zipDataDelete->createDeleteSqlFiles();
            $updateSqlFilePaths = array_merge($updateSqlFilePaths, $zipDataDelete->getSqlFilePaths());
            echo '郵便番号データ(削除分) ... 変換完了 !!' . "\n\n";
        } else {
            echo '郵便番号データ(削除分) ... なし' . "\n\n";
        }

        // 追加用データがあれば処理する
        $zipDataAdd = new ZipData($dataDir, 'ADD_' . $yearMonth);
        if ($zipDataAdd->hasRawCsvFile()) {
            echo '郵便番号データ((追加分) ... 変換開始 ...' . "\n\n";
            $zipDataAdd->normalizeCsvData();
            $zipDataAdd->updateKanaDic();
            $zipDataAdd->createInsertSqlFiles();
            $updateSqlFilePaths = array_merge($updateSqlFilePaths, $zipDataAdd->getSqlFilePaths());
            echo '郵便番号データ(追加分) ... 変換完了 !!' . "\n\n";
        } else {
            echo '郵便番号データ(追加分) ... なし' . "\n\n";
        }

        // 事業所データがあれば処理する
        $zipBizDataAll = new ZipBizData($dataDir, 'JIGYOSYO');
        if ($zipBizDataAll->hasRawCsvFile()) {
            echo '大口事業所個別番号データ(全データ) ... 変換開始 ...' . "\n\n";
            $zipBizDataAll->normalizeCsvData();
            $zipBizDataAll->createInsertSqlFiles();
            $masterSqlFiles = array_merge($masterSqlFiles, $zipBizDataAll->getSqlFileNames());
            echo '大口事業所個別番号データ(全データ) ... 変換完了 !!' . "\n\n";
        } else {
            echo '大口事業所個別番号データ(全データ) ... なし' . "\n\n";
        }

        // 事業所削除用データがあれば処理する
        $zipBizDataDelete = new ZipBizData($dataDir, 'JDEL' . $yearMonth);
        if ($zipBizDataDelete->hasRawCsvFile()) {
            echo '大口事業所個別番号データ(削除分) ... 変換開始 ...' . "\n\n";
            $zipBizDataDelete->normalizeCsvData();
            $zipBizDataDelete->createDeleteSqlFiles();
            $updateSqlFilePaths = array_merge($updateSqlFilePaths, $zipBizDataDelete->getSqlFilePaths());
            echo '大口事業所個別番号データ(削除分) ... 変換完了 !!' . "\n\n";
        } else {
            echo '大口事業所個別番号データ(削除分) ... なし' . "\n\n";
        }

        // 事業所追加用データがあれば処理する
        $zipBizDataAdd = new ZipBizData($dataDir, 'JADD' . $yearMonth);
        if ($zipBizDataAdd->hasRawCsvFile()) {
            echo '大口事業所個別番号データ(追加分) ... 変換開始 ...' . "\n\n";
            $zipBizDataAdd->normalizeCsvData();
            $zipBizDataAdd->createInsertSqlFiles();
            $updateSqlFilePaths = array_merge($updateSqlFilePaths, $zipBizDataAdd->getSqlFilePaths());
            echo '大口事業所個別番号データ(追加分) ... 変換完了 !!' . "\n\n";
        } else {
            echo '大口事業所個別番号データ(追加分) ... なし' . "\n\n";
        }

        // マスター SQL ファイルをマスター・データ・ディレクトリにコピーする
        if (count($masterSqlFiles) > 0) {
            echo 'マスター SQL ファイル ... コピー開始 ...' . "\n\n";
            $masterDir = MASTERS_DIR . DIRECTORY_SEPARATOR . $yearMonth;
            @mkdir($masterDir);
            foreach($masterSqlFiles as $src) {
                $srcPath = $dataDir . DIRECTORY_SEPARATOR . WORK_SUB_DIR . DIRECTORY_SEPARATOR . $src;
                $dstPath = $masterDir . DIRECTORY_SEPARATOR . $src;
                copy($srcPath, $dstPath);
            }
            echo 'マスター SQL ファイル ... コピー完了 !!' . "\n\n";
        }

        // 更新用 SQL を一つにまとめたファイルを更新データ・ディレクトリに作成する
        if (count($updateSqlFilePaths) > 0) {
            echo '更新用単一 SQL ファイル ... 作成開始 ...' . "\n\n";
            $dstFileName = UPDATES_DIR . DIRECTORY_SEPARATOR . "update_" . $yearMonth . ".sql";
            @unlink($dstFileName);
            foreach ($updateSqlFilePaths as $src) {
                file_put_contents($dstFileName, file_get_contents($src), FILE_APPEND);
            }
            echo '更新用単一 SQL ファイル ... 作成完了 !!' . "\n\n";
        }
    }
}
