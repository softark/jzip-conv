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
    showUsage();
}

function showNotAvailableError()
{
    fputs(STDERR, "Zip data not available for the requested period.\n\n");
    showUsage();
}

function showUsage()
{
    fputs(STDERR, "Usage: php zipconv.php <DownloadMode> <YearAndMonth>\n");
    fputs(STDERR, "  <DownloadMode> should be 'diff', 'all' or 'both' ( 'both' = 'diff' + 'all') ... defaults to 'diff'\n");
    fputs(STDERR, "  <YearAndMonth> should be in the format of 'YYMM' ... defaults to the latest available period\n");
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


/** @var $operationMode string 操作モード */
// 引数省略時は "diff"
$operationMode = 'diff';

// 引数から操作モードを取得
if (isset($argv[1])) {
    $operationMode = $argv[1];
    if ($operationMode !== 'diff' && $operationMode !== 'all' && $operationMode !== 'both') {
        showSyntaxError();
        exit(-1);
    }
}

// 引数から年月を取得
if (isset($argv[2])) {
    $ym = $argv[2];
    if (!preg_match('/^[12]\d[01]\d$/', $ym)) {
        showSyntaxError();
        exit(-1);
    }
    $year = intval(substr($ym, 0, 2));
    $month = intval(substr($ym, 2, 2));
    if ($month < 1 || $month > 12) {
        showSyntaxError();
        exit(-1);
    }
    if ($year * 100 + $month > intval($yearMonth)) {
        showNotAvailableError();
        exit(-1);
    }
    $yearMonth = $ym;
}

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

try {
    $downLoader = new ZipDataDownloader($yearMonth, $operationMode);
    $downLoader->download();

    $converter = new ZipDataConverter($yearMonth, $operationMode);
    $converter->runConversion();

    echo "\nCompleted.\n";
} catch (Exception $e) {
    fprintf(STDERR, "\n\n");
    fprintf(STDERR, $e->getMessage());
}
