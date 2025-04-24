# checkFileHash 說明

## 目的

掃描指定目錄下所有檔案並產生 hash 值，並與前次掃描做比較偵測檔案是否變動

## 檔案說明
|檔案| 說明|
|--|--|
|main.php|執行主程式|
|class/HashChecker.php|主要元件|
|hashfile.txt|做為標準參考檔|
|hashfile.txt.<date('YmdHis')>|歷史標準參考檔|
|new_hashfile.txt|每次執行的 hash file |
|report.txt|每次掃描產出的結果報告|
|reports/report*.txt|歷史掃描報告保留處|

## 執行方法

1. 打開 main.php 修改以下內容為你的掃描目錄跟排除目錄
```php
define('TARGET_DIR', 'C:\xampp\htdocs\wordpress\wp-content\plugins\demo-plugin\\');
define('DEF_EXCLUDE', 'log\\,assets\\json\\');
```

2. 打開命令列下 ```php main.php``` 執行即可


  !!! info 詳細設定 與 命令列可用參數直接參考程式內容

## 流程概述

1. 每次執行會將檔案與 hash 結果存在 new_hashfile.txt
2. 初次執行會詢問是否將 new_hashfile.txt 轉為 hashfile.txt 作為初始標準
3. 往後執行都會透過 new_hashfile.txt 與 hashfile.txt 比對檔案是否異動
4. 每次行後都會產生報告檔到 reports/report*.txt 並產生連結檔案或複製一份到 report.txt
5. 有異動時會詢問是否將 new_hashfile.txt 覆蓋 hashfile.txt 作為下次標準
   1. 舊的 hashfile.txt 會備份到 hashfile.txt.<date('YmdHis')>

## 補充

main.php 亦可透過 Web Service 呼叫，只需在 new HashChecker() 時改變最末參數 $outputMode 為 'html' 或 'json' 即可，但程序僅提供比對及產生報告不會對 hashfile.txt 進行覆蓋
