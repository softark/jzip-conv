<?php

/**
 * Class ZipDataDownloader
 */
class ZipDataDownloader
{
    /**
     * 郵便番号データ
     */
    const SOURCE_URL = 'http://www.post.japanpost.jp/zipcode/dl/kogaki/zip/';
    const KEN_ALL_DATA = 'ken_all';
    const ADD_DATA_PREFIX = 'add_';
    const DEL_DATA_PREFIX = 'del_';

    /**
     * 個別事業所データ
     */
    const JG_SOURCE_URL = 'http://www.post.japanpost.jp/zipcode/dl/jigyosyo/zip/';
    const JG_ALL_DATA = 'jigyosyo';
    const JG_ADD_DATA_PREFIX = 'jadd';
    const JG_DEL_DATA_PREFIX = 'jdel';

    /**
     * ファイル拡張子
     */
    const FILE_EXT = '.zip';

    /**
     * ダウンロード・モード
     */
    const DOWNLOAD_DIFF = 'diff';
    const DOWNLOAD_ALL = 'all';
    const DOWNLOAD_BOTH = 'both';

    /**
     * @var string  対象の年月
     * YYMM 形式
     */
    private $yearMonth;

    /**
     * @var string  ダウンロード・モード
     */
    private $mode;

    /**
     * @var string データ・ディレクトリ
     */
    private $dataDir;

    /**
     * コンストラクタ
     * @param string $yearMonth 年月
     * @param string $mode ダウンロード・モード
     * @param string $dir データ・ディレクトリ
     */
    public function __construct($yearMonth, $mode, $dir)
    {
        if (!preg_match('/(\d\d)([01]\d)/', $yearMonth, $matches)) {
            throw  new Exception('Invalid parameter specified for year and month.');
        }
        $m = intval($matches[2]);
        if ($m < 1 || $m > 12 ) {
            throw  new Exception('Invalid parameter specified for month.');
        }
        $this->yearMonth = $yearMonth;

        if ($mode != self::DOWNLOAD_DIFF && $mode != self::DOWNLOAD_ALL && $mode != self::DOWNLOAD_BOTH) {
            throw  new Exception('Invalid parameter specified for mode.');
        }
        $this->mode = $mode;

        if (!file_exists($dir)) {
            throw  new Exception('Invalid parameter specified for directory.');
        }
        $this->dataDir = $dir . DIRECTORY_SEPARATOR . $yearMonth;
    }

    /**
     * ダウンロード実行
     */
    public function download()
    {
        echo "Downloading data files ...\n";
        $this->prepareDataDir();
        if ($this->mode == self::DOWNLOAD_DIFF || $this->mode == self::DOWNLOAD_BOTH) {
            $this->downloadDataFile(self::SOURCE_URL, self::ADD_DATA_PREFIX . $this->yearMonth);
            $this->downloadDataFile(self::SOURCE_URL, self::DEL_DATA_PREFIX . $this->yearMonth);
            $this->downloadDataFile(self::JG_SOURCE_URL, self::JG_ADD_DATA_PREFIX . $this->yearMonth);
            $this->downloadDataFile(self::JG_SOURCE_URL, self::JG_DEL_DATA_PREFIX . $this->yearMonth);
        }
        if ($this->mode === self::DOWNLOAD_ALL || $this->mode === self::DOWNLOAD_BOTH) {
            $this->downloadDataFile(self::SOURCE_URL, self::KEN_ALL_DATA);
            $this->downloadDataFile(self::JG_SOURCE_URL, self::JG_ALL_DATA);
        }
        echo "done.\n\n";
    }

    /**
     * データディレクトリを準備する
     */
    private function prepareDataDir()
    {
        if (!file_exists($this->dataDir)) {
            if (!mkdir($this->dataDir)) {
                throw  new Exception("Failed to make the data directory [{$this->dataDir}]");
            }
        }
    }

    /**
     * ダウンロードを実行する
     * @param $srcUrl string ソース URL
     * @param $file string ファイル名（拡張子なし）
     */
    private function downloadDataFile($srcUrl, $file)
    {
        $fileName = $file . self::FILE_EXT;
        $filePath = $this->dataDir . DIRECTORY_SEPARATOR . $fileName;
        echo "$file : downloading ... ";
        if (!copy($srcUrl . $fileName, $filePath)) {
            throw new Exception("Failed to download the data file [$fileName]");
        }

        echo "extracting ... ";
        $za = new ZipArchive;
        if (!$za->open($filePath)) {
            throw new Exception("Failed to extract the data file [$fileName]");
        }
        $za->extractTo($this->dataDir);
        $za->close();

        @unlink($this->dataDir . DIRECTORY_SEPARATOR . $fileName);
        echo "done.\n";
    }

}