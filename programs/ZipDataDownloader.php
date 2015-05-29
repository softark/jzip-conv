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

    const FILE_EXT = '.zip';

    /**
     * @var string  対象の年月
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
     */
    public function __construct($yearMonth, $mode)
    {
        $this->yearMonth = $yearMonth;
        $this->mode = $mode;
        $this->dataDir = DATA_DIR . DIRECTORY_SEPARATOR . $yearMonth;
    }

    /**
     * ダウンロード実行
     */
    public function download()
    {
        echo "Downloading data files ...\n";
        $this->prepareDataDir();
        if ($this->mode === 'diff' || $this->mode === 'full') {
            $this->downloadDataFile(self::SOURCE_URL, self::ADD_DATA_PREFIX . $this->yearMonth);
            $this->downloadDataFile(self::SOURCE_URL, self::DEL_DATA_PREFIX . $this->yearMonth);
            $this->downloadDataFile(self::JG_SOURCE_URL, self::JG_ADD_DATA_PREFIX . $this->yearMonth);
            $this->downloadDataFile(self::JG_SOURCE_URL, self::JG_DEL_DATA_PREFIX . $this->yearMonth);
        }
        if ($this->mode === 'all' || $this->mode === 'full') {
            $this->downloadDataFile(self::SOURCE_URL, self::KEN_ALL_DATA);
            $this->downloadDataFile(self::JG_SOURCE_URL, self::JG_ALL_DATA);
        }
        echo "done.\n\n";
    }

    private function prepareDataDir()
    {
        if (!file_exists($this->dataDir)) {
            if (!mkdir($this->dataDir)) {
                fputs(STDERR, "Failed to make the data directory [{$this->dataDir}]\n");
                exit(-1);
            }
        }
    }

    private function downloadDataFile($baseUrl, $file)
    {
        $fileName = $file . self::FILE_EXT;
        echo "$file : downloading ... ";
        if (!copy($baseUrl . $fileName, $this->dataDir . DIRECTORY_SEPARATOR . $fileName)) {
            fputs(STDERR, "Failed to download the data file [$fileName]\n");
            exit(-1);
        }

        echo "extracting ... ";
        $za = new ZipArchive;
        if (!$za->open($this->dataDir . DIRECTORY_SEPARATOR . $fileName)) {
            fputs(STDERR, "Failed to extract the data from [$fileName]\n");
            exit(-1);
        }
        $za->extractTo($this->dataDir);
        $za->close();

        unlink($this->dataDir . DIRECTORY_SEPARATOR . $fileName);
        echo "done.\n";
    }

}