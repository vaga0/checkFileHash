<?php

class HashChecker {
	const NEW_LINE = (PHP_SAPI === 'cli') ? PHP_EOL : "<br>";
	
	private $outputMode; // "cli", "json", "html"
	private $jsonOutputCollection;

	private $targetDir;							// 偵測目錄
	private $hashFilePath;					// 標準 hashfile
	private $input_reportFilePath;	// 原始比對報告檔
	private $reportFilePath;				// 比對報告檔
	private $input_exclude;					// 原始排除相對路徑
	private $exclude;								// 排除路徑
	private $excludeFiles;					// 過程中被排除的檔案


	public function __construct($dir, $hashFile, $reportFile, $exclude = [], $outputMode = 'cli') {
		$this->targetDir = realpath($dir) . DIRECTORY_SEPARATOR;
		$this->hashFilePath = $hashFile;
		$this->input_reportFilePath = $reportFile;
		$this->reportFilePath = 'reports'. DIRECTORY_SEPARATOR 
		. pathinfo($this->input_reportFilePath, PATHINFO_FILENAME) 
    . '-' . date("Y-m-d.His") 
    . '.' . pathinfo($this->input_reportFilePath, PATHINFO_EXTENSION);

		$this->input_exclude = $exclude;
		$this->exclude = array_map(function ($path) {
			return $this->targetDir . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
		}, $exclude);
		$this->excludeFiles = [];
		$this->outputMode = $outputMode;
		$this->jsonOutputCollection = [];
	}

	private function output($message, $type = 'info') {
		switch ($this->outputMode) {
			case 'cli':
				echo $message . self::NEW_LINE;
				break;
			case 'json':
				$this->jsonOutput($message, $type);
				break;
			case 'html':
				echo "<p>$message</p>";
				break;
		}
	}

	private function jsonOutput($message, $type) {
		if ($this->outputMode === 'json') {
			$this->jsonOutputCollection[] = ['type' => $type, 'message' => $message];
		}
	}

	public function run() {
		$oldHashFile = file_exists($this->hashFilePath) ? $this->hashFilePath : null;
		$newHashFile = 'new_hashfile.txt';

		$files = $this->scanDirectory($this->targetDir);
		$this->saveHashToFile($files, $newHashFile);

		if ($oldHashFile) {
			$changes = $this->compareHashFiles($oldHashFile, $newHashFile);
			$this->generateReport($changes);
			// echo "Report generated at {$this->reportFilePath}" . self::NEW_LINE;
			$this->output("Report generated at {$this->reportFilePath}");

			// 刪除舊的 link（如果存在）
			if (file_exists($this->input_reportFilePath) || is_link($this->input_reportFilePath)) {
				unlink($this->input_reportFilePath);
			}

			// 嘗試建立硬連結
			if (!link($this->reportFilePath, $this->input_reportFilePath)) {
				// echo "Failed to create hard link!" . self::NEW_LINE;
				$this->output("Failed to create hard link!", "error");
			} else {
				// echo "Created hard link: $this->input_reportFilePath → $this->reportFilePath" . self::NEW_LINE;
				$this->output("Created hard link: $this->input_reportFilePath → $this->reportFilePath", "success");
			}

			if (!empty($changes['added']) || !empty($changes['removed']) || !empty($changes['modified'])) {
				// echo "【有異動】";
				$this->output("【有異動】");
        $this->confirmAndReplaceHashFile($oldHashFile, $newHashFile);
			}
		} else {
			// echo "【首次產生】";
			$this->output("【首次產生】");
			$this->confirmAndReplaceHashFile(null, $newHashFile);
		}

		if (($this->outputMode === 'json') && !empty($this->jsonOutputCollection)) {
			header('Content-Type: application/json');
			echo json_encode($this->jsonOutputCollection, JSON_UNESCAPED_UNICODE);
		}
	}

	private function confirmAndReplaceHashFile($oldHashFile, $newHashFile) {
		if (PHP_SAPI === 'cli') {
			// echo "是否將此次 hashfile 做為新的標準？ (y/N): ";
			$this->output("是否將此次 hashfile 做為新的標準？ (y/N): ");
			$update = trim(fgets(STDIN));
			if ($update === 'y') {
				if ($oldHashFile) {
					rename($oldHashFile, $oldHashFile . '.' . date('YmdHis'));
				}
				rename($newHashFile, $this->hashFilePath);
			}
		}
	}

	private function scanDirectory($dir) {
		$files = [];
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

		foreach ($iterator as $file) {
			if ($file->getFilename() === '.' || $file->getFilename() === '..') {
				continue;
			}

			$pathname = realpath($file->getPathname());

			foreach ($this->exclude as $excludedPath) {
				if (strpos($pathname, $excludedPath) === 0) {
					$this->excludeFiles[] = $file->getPathname();
					// echo "排除檔案: $pathname" . self::NEW_LINE;
					$this->output("排除檔案: $pathname");
					continue 2;
				}
			}

			if (!$file->isDir()) {
				$files[] = $file->getPathname();
			}
		}

		return $files;
	}

	private function saveHashToFile($files, $hashFilePath) {
		$hashFileContent = '';
		foreach ($files as $file) {
			$hash = hash_file('sha256', $file);
			$relativePath = str_replace($this->targetDir, '', $file);
			$hashFileContent .= $relativePath . ' ' . $hash . PHP_EOL;
		}

		file_put_contents($hashFilePath, $hashFileContent);
	}

	private function compareHashFiles($oldHashFile, $newHashFile) {
		$oldHashes = file($oldHashFile, FILE_IGNORE_NEW_LINES);
		$newHashes = file($newHashFile, FILE_IGNORE_NEW_LINES);

		$oldHashMap = [];
		foreach ($oldHashes as $line) {
			list($file, $hash) = explode(' ', $line, 2);
			$oldHashMap[$file] = $hash;
		}

		$newHashMap = [];
		foreach ($newHashes as $line) {
			list($file, $hash) = explode(' ', $line, 2);
			$newHashMap[$file] = $hash;
		}

		$added = array_diff_key($newHashMap, $oldHashMap);
		$removed = array_diff_key($oldHashMap, $newHashMap);
		$modified = [];

		foreach ($newHashMap as $file => $hash) {
			if (isset($oldHashMap[$file]) && $oldHashMap[$file] !== $hash) {
				$modified[$file] = ['old' => $oldHashMap[$file], 'new' => $hash];
			}
		}

		return ['added' => $added, 'removed' => $removed, 'modified' => $modified];
	}

	private function generateReport($changes) {
		$report = '';					// 新增、移除、修改的檔案
		$exclude_files = '';	// 排除的檔案

		if (!empty($changes['added'])) {
			$report .= "Added Files:\n";
			foreach ($changes['added'] as $file => $hash) {
				$report .= "  $file\n";
			}
		}

		if (!empty($changes['removed'])) {
			$report .= "Removed Files:\n";
			foreach ($changes['removed'] as $file => $hash) {
				$report .= "  $file\n";
			}
		}

		if (!empty($changes['modified'])) {
			$report .= "Modified Files:\n";
			foreach ($changes['modified'] as $file => $hashes) {
				$report .= "  $file (Old Hash: {$hashes['old']}, New Hash: {$hashes['new']})\n";
			}
		}

		// add header info
		$head = '====================================' . PHP_EOL;
		$head .= 'Create Date: ' . date('Y-m-d H:i:s', time()) . PHP_EOL;
		$head .= 'Directory: ' . $this->targetDir . PHP_EOL;
		$head .= 'Exclude: ' . implode(",", $this->input_exclude) . PHP_EOL;
		$head .= '====================================' . PHP_EOL . PHP_EOL;

		if ($report === '') $report = "No changes detected.\n\n";

		if (!empty($this->excludeFiles)) {
			$exclude_files = "Exclude Files:\n";
			foreach($this->excludeFiles as $file) {
				$exclude_files .= "  $file\n";
			}
		}

		// 檢查目錄是否存在，如果不存在則創建
		$directory = dirname($this->reportFilePath);

		// 如果目錄不存在，則創建它
		if (!is_dir($directory)) {
				mkdir($directory, 0744, true);  // 0777 是可讀、可寫、可執行，`true` 代表遞迴創建父目錄
		}

		file_put_contents($this->reportFilePath, $head . $report . $exclude_files);
	}
}
