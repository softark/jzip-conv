<?php
/*
 * 郵便番号 CSV データ変換
 *
 * 日本郵便（郵便事業株式会社）が提供している 郵便番号データ から、
 * MySQL のインポート用 SQL ファイルを生成する
 *
 * LINES_PER_SQL_FILE, $yearMonth を適当に書き換えて実行する
 *
 */

function showSyntaxError()
{
    fputs(STDERR, "Invalid parameter(s).\n\n");
    fputs(STDERR, "Usage: php zipconv.php <YearAndMonth> <DownloadMode>\n");
    fputs(STDERR, "  <YearAndMonth> should be in the format of 'YYMM' ... defaults to the current year and month\n");
    fputs(STDERR, "  <DownloadMode> should be 'diff', 'all' or 'full' ... defaults to 'diff'\n");
}

/** @var $yearMonth string 年月 */
// 引数省略時は、現在の年月
$yearMonth = '';
$y = intval(substr(date('Y'), 2));
$m = intval(date('m'));
$d = intval(date('d'));
// 月末以外は、前の月を指定したとする
if ($d < 25) {
    $m--;
    if ($m < 1) {
        $m = 12;
        $y--;
    }
}
$yearMonth = sprintf('%02d%02d', $y, $m);


/** @var $downloadMode string ダウンロード・モード */
// 引数省略時は "diff"
$downloadMode = 'diff';

// 引数から年月を取得
if (isset($argv[1])) {
    $yearMonth = $argv[1];
    if (!preg_match('/^[12]\d[01]\d$/', $yearMonth)) {
        showSyntaxError();
        exit(-1);
    }
    $year = intval(substr($yearMonth, 0, 2));
    $month = intval(substr($yearMonth, 2, 2));
    if ($month < 1 || $month > 12) {
        showSyntaxError();
        exit(-1);
    }
    if (200000 + $year * 100 + $month > intval(date('Ym'))) {
        showSyntaxError();
        exit(-1);
    }
}

// 引数からダウンロード・モードを取得
if (isset($argv[2])) {
    $downloadMode = $argv[2];
    if ($downloadMode !== 'diff' && $downloadMode !== 'all' && $downloadMode !== 'full') {
        showSyntaxError();
        exit(-1);
    }
}

// INSERT 文の行数
const LINES_PER_SQL = 40;

// 1 SQL ファイルあたりの行数 ... アップロード可能なファイル・サイズに合わせて調節
// 31000 ... 約 7 MB - 7.5 MB
const LINES_PER_SQL_FILE = 20000;

// ベースになるデータ・ディレクトリ
define('DATA_DIR', ".." . DIRECTORY_SEPARATOR . "data");

// データ・ディレクトリの中のワーク・ディレクトリ
define('WORK_SUB_DIR', "work");

// フリカナ辞書のディレクトリ
define('KANA_DIC_DIR', ".." . DIRECTORY_SEPARATOR . "kana_dics");

// 最終データ出力ディレクトリ
define('OUTPUTS_DIR', ".." . DIRECTORY_SEPARATOR . "outputs");

// マスター・データ・ディレクトリ
define('MASTERS_DIR', OUTPUTS_DIR . DIRECTORY_SEPARATOR . "masters");

// 更新データ・ディレクトリ
define('UPDATES_DIR', OUTPUTS_DIR . DIRECTORY_SEPARATOR . "updates");

// データ・ダウンローダ・クラス
require_once('ZipDataDownloader.php');
// データ変換機能クラス
require_once('ZipDataConverter.php');
// 振り仮名辞書クラス
require_once('KanaDic.php');
// 郵便番号データ(共通)
require_once('ZipDataCommon.php');
// 郵便番号データ
require_once('ZipData.php');
// 大口事業所個別番号データ
require_once('ZipBizData.php');

$downLoader = new ZipDataDownloader($yearMonth, $downloadMode);
$downLoader->download();

$converter = new ZipDataConverter($yearMonth);
$converter->runConversion();
