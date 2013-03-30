jzip-conv
=========

Japanese ZIP data converter (日本の郵便番号データのコンバータ)

概要
----

[日本郵便(郵便事業株式会社)](http://www.post.japanpost.jp/index.html) が提供している [郵便番号データ](http://www.post.japanpost.jp/zipcode/download.html) から、
MySQL のインポート用 SQL ファイルを生成するプログラム。

元データ（日本郵便の CSV データ）は、よく知られているように、プログラマの目から見て、非常に不細工な仕様に基づいたデータになっている。
この CSV データを、何も考えずに、単純にデータベースにインポートすると、酷い目にあうことになる。

当プログラムは、この元データを可能な限り合理的なデータに変換して、MySQL のデータベースに格納するためのものである。

言語および実行環境
------------------

+ PHP 5.X (CLI)
+ Windows

ディレクトリとファイル
--------------------

    + programs                   ... プログラム
        main.php                 ... メインの実行ファイル
        ZipDataConverter.php     ... コンバータ
        ZipDataCommon.php        ... 郵便番号データ(基底)
        ZipData                  ... 郵便番号データ
        ZipBizData               ... 大口事業所個別番号データ
        KanaDic                  ... 振り仮名辞書

    + sqls                       ... SQL
        zip_data_init.sql        ... 初期テーブル作成
        zip_data_flag_update.sql ... フラグ更新

    + kana_dics                  ... 振り仮名辞書
        pref.csv                 ... 都道府県名辞書データ
        town.csv                 ... 市区町村名辞書データ
        block.csv                ... 町域名辞書データ

    + data                       ... ソース・データ、作業ディレクトリ
        + 1303                   ... 2013年3月
            + work               ... 作業ディレクトリ
        + 1304                   ... 2013年4月
            + work               ... 作業ディレクトリ
        + YYMM                   ... 一般に、20YY年MM月
            + work               ... 作業ディレクトリ

    + outputs                    ... 出力ディレクトリ
        + masters                ... マスター SQL
            + 1303               ... 2013年3月
                KEN_ALL-01.sql   ... 全国データ1
                KEN_ALL-02.sql   ... 全国データ2
                KEN_ALL-03.sql   ... 全国データ3
                KEN_ALL-04.sql   ... 全国データ4
                JIGYOSYO-01.sql  ... 個別事業所データ1
            + 1304               ... 2013年3月
            + YYMM               ... 一般に20YY年MM月
        + updates                ... 更新用 SQL
            update_1303.sql      ... 2013年3月
            update_1304.sql      ... 2013年3月
            update_YYMM.sql      ... 一般に20YY年MM月

プログラムの使い方 (1) 初期データ作成
-----------------------------------

1. データベースの初期化  
    sqls/zip_data_init.sql を DB にインポートして、空っぽのテーブルを作成する
2. 郵便番号データ
    1. 最新の ken_all.lzh または ken_all.zip を入手する
    2. KEN_ALL.CSV を解凍して、data/YYMM ディレクトリに配置する
3. 大口事業所個別番号データ
    1. 最新の jigyosyo.lzh または jigyosyo.zip を入手する
    2. JIGYOSYO.CSV を解凍して、data/YYMM ディレクトリに配置する
4. data/YYMM ディレクトリを指定して(*1)、programs/main.php を実行する
5. outputs/masters/YYMM ディレクトリに生成された全ての SQL を DB にインポートする
6. フラグの更新(*2)  
    sqls/zip_data_flag_update.sql を DB にインポートして、フラグを更新する

+ (*1) 「data/YYMM ディレクトリの指定」は、programs/main.php を直接書き換えて行う(手抜きでごめん)。
+ (*2) 「フラグの更新」は、データをインポートした場合に、必ず実行する必要がある。

プログラムの使い方 (2) 月次データ更新
-----------------------------------

1. 郵便番号データ : 削除データ
    1. del_YYMM.lzh または del_YYMM.zip を入手する
    2. DEL_YYMM.CSV を解凍して、data/YYMM ディレクトリに配置する
2. 郵便番号データ : 追加データ
    1. add_YYMM.lzh または add_YYMM.zip を入手する
    2. ADD_YYMM.CSV を解凍して、data/YYMM ディレクトリに配置する
3. 大口事業所個別番号データ : 削除データ
    1. jdelYYMM.lzh または jdelYYMM.zip を入手する
    2. JDELYYMM.CSV を解凍して、data/YYMM ディレクトリに配置する
4. 大口事業所個別番号データ : 追加データ
    1. jaddYYMM.lzh または jaddYYMM.zip を入手する
    2. JADDYYMM.CSV を解凍して、data/YYMM ディレクトリに配置する
5. data/YYMM ディレクトリを指定して(*1)、programs/main.php を実行する
6. outputs/updates ディレクトリに生成された updates-YYMM.sql を DB にインポートする
7. フラグの更新(*2)  
    sqls/zip_data_flag_update.sql を DB にインポートして、フラグを更新する

+ (*1) 「data/YYMM ディレクトリの指定」は、programs/main.php を直接書き換えて行う(手抜きでごめん)。
+ (*2) 「フラグの更新」は、データをインポートした場合に、必ず実行する必要がある。

月次データ更新をするためには、初期データ作成後、その前の月まで、一度も欠かさずに
月次データ更新をしていなければならない。

データ変換の要点
----------------

+ 複数行にわたって記述されているデータを一行にまとめる。
+ そのままでは扱いづらい「町域」のデータを分析して、block と street という二つのデータに分割する。
+ 大口事業所個別番号のデータについて、可能な限り、「都道府県名」「市区町村名」「町域名」の「振り仮名」を補完する。
+ 「同一の町域が複数の郵便番号を持つ場合」および「同一の郵便番号が複数の町域にまたがる場合」のフラグについて、間違いを修正するスクリプトを提供。

生成される MySQL データベース・テーブルの使い方
---------------------------------------------

+ テーブル構造については、sqls/zip_data_init.sql を参照。
+ コンバート時に、「町域」が、block と street に分離されていることに注意。
+ street および street_kana の表示には注意が必要である。
    + 複数の地名が '、' で連結されている場合がある。
    + さらに、各地名は、「」 または <> に囲まれた「A、B、...、Xを除く」という複数の例外を含む場合がある。
    + street_kana がデータとして提供されていない場合がある(特に京都市の市街地)。
    + 要するに、street および street_kana は、そのまま使用すべき「住所」ではなくて、「住所」と郵便番号の対応関係を説明する「但し書き」として表示すべきものである。
+ MySQL のテーブル・タイプは、このデータでは、 MyISAM がお奨め。
    + InnoDB でも作ってみたが、検索に要する時間が目に見えて長かった。

使用例
------

ここで作成した MySQL の郵便番号データ・テーブルの使用例として、以下のものを挙げる。

+ [郵便番号案内@softark.net] (http://tools.softark.net/zipdata)

リンク
------

先人の業績として参照すべきものをいくつか挙げる。

+ [郵便番号データの落とし穴](http://www.f3.dion.ne.jp/~element/msaccess/AcTipsKenAllCsv.html)
+ [ 郵便番号データは自分で加工しない](http://d.hatena.ne.jp/dayflower/20100929/1285744153)

2011-05-01 初稿 / 2013-03-30 更新
