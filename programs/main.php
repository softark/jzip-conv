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

// INSERT 文の行数
const LINES_PER_SQL = 40;

// 1 SQL ファイルあたりの行数 ... アップロード可能なファイル・サイズに合わせて調節
// 31000 ... 約 7 MB - 7.5 MB
const LINES_PER_SQL_FILE = 31000;

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

/** @var $yearMonth string 年月 */
$yearMonth = '1312';

$converter = new ZipDataConverter($yearMonth);
$converter->runConversion();
