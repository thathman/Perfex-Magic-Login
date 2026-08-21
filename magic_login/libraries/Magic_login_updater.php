<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Magic_login_updater
{
    const REPOSITORY = 'thathman/Perfex-Magic-Login';
    const RELEASE_ASSET = 'magic_login.zip';
    const CHECKSUM_ASSET = 'magic_login.zip.sha256';

    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    public function latest_release($force = false)
    {
        $checkedAt = (int) get_option('magic_login_release_cache_checked_at');
        $cached = (string) get_option('magic_login_release_cache');

        if (!$force && $checkedAt > 0 && (time() - $checkedAt) < 900 && $cached !== '') {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return !empty($decoded['available']) ? $decoded : false;
            }
        }

        $response = $this->github_json('https://api.github.com/repos/' . self::REPOSITORY . '/releases/latest');
        update_option('magic_login_release_cache_checked_at', (string) time());

        if (!$response || empty($response['tag_name'])) {
            update_option('magic_login_release_cache', json_encode(['available' => false]));
            return false;
        }

        $tag = trim((string) $response['tag_name']);
        $version = ltrim($tag, 'vV');
        if ($version === '' || !preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
            return false;
        }

        if (!defined('MAGIC_LOGIN_VERSION') || version_compare($version, MAGIC_LOGIN_VERSION, '<=')) {
            update_option('magic_login_release_cache', json_encode([
                'available' => false,
                'version'   => $version,
            ]));
            return false;
        }

        $zipUrl = '';
        $shaUrl = '';
        foreach (isset($response['assets']) && is_array($response['assets']) ? $response['assets'] : [] as $asset) {
            if (!isset($asset['name'], $asset['browser_download_url'])) {
                continue;
            }
            if ($asset['name'] === self::RELEASE_ASSET) {
                $zipUrl = (string) $asset['browser_download_url'];
            } elseif ($asset['name'] === self::CHECKSUM_ASSET) {
                $shaUrl = (string) $asset['browser_download_url'];
            }
        }

        $data = [
            'available' => true,
            'version'   => $version,
            'tag'       => $tag,
            'changelog' => isset($response['html_url']) ? (string) $response['html_url'] : '',
            'zip_url'   => $zipUrl,
            'sha_url'   => $shaUrl,
            'published' => isset($response['published_at']) ? (string) $response['published_at'] : '',
        ];

        update_option('magic_login_release_cache', json_encode($data));
        return $data;
    }

    public function install_latest($automatic = false)
    {
        $release = $this->latest_release(true);
        if (!$release) {
            return ['ok' => false, 'message' => 'No newer Magic Login release is available.'];
        }

        return $this->install_release($release, $automatic);
    }

    public function install_release(array $release, $automatic = false)
    {
        if (empty($release['version']) || version_compare($release['version'], MAGIC_LOGIN_VERSION, '<=')) {
            return ['ok' => false, 'message' => 'The release version is not newer than the installed files.'];
        }

        if (empty($release['zip_url']) || empty($release['sha_url'])) {
            return ['ok' => false, 'message' => 'Release is missing the required package or SHA-256 checksum asset.'];
        }

        if (!class_exists('ZipArchive')) {
            return ['ok' => false, 'message' => 'PHP ZipArchive extension is required for automatic updates.'];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'PHP cURL extension is required for automatic updates.'];
        }

        $modulePath = rtrim(module_dir_path('magic_login'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!is_dir($modulePath) || !is_writable($modulePath)) {
            return ['ok' => false, 'message' => 'Magic Login module directory is not writable.'];
        }

        try {
            $randomPart = bin2hex(random_bytes(8));
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Unable to initialize a secure update workspace.'];
        }

        $workDir = rtrim(TEMP_FOLDER, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'magic-login-update-' . $randomPart . DIRECTORY_SEPARATOR;
        if (!$this->ensure_directory($workDir)) {
            return ['ok' => false, 'message' => 'Unable to create update working directory.'];
        }

        $zipPath = $workDir . self::RELEASE_ASSET;
        $shaPath = $workDir . self::CHECKSUM_ASSET;
        $extractPath = $workDir . 'extract' . DIRECTORY_SEPARATOR;
        $backupPath = '';
        $updateId = 0;
        $migrationStarted = false;
        $installedVersion = defined('MAGIC_LOGIN_VERSION') ? MAGIC_LOGIN_VERSION : 'unknown';
        $packageVersion = (string) $release['version'];
        $actualHash = '';

        try {
            if (!$this->download($release['zip_url'], $zipPath) || !$this->download($release['sha_url'], $shaPath)) {
                throw new RuntimeException('Unable to download one or more release assets.');
            }

            $checksumText = trim((string) file_get_contents($shaPath));
            if (!preg_match('/\b([a-f0-9]{64})\b/i', $checksumText, $match)) {
                throw new RuntimeException('Release checksum file is invalid.');
            }

            $actualHash = hash_file('sha256', $zipPath);
            if (!$actualHash || !hash_equals(strtolower($match[1]), strtolower($actualHash))) {
                throw new RuntimeException('Release package checksum verification failed.');
            }

            if (!$this->ensure_directory($extractPath)) {
                throw new RuntimeException('Unable to create extraction directory.');
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('Unable to open release package.');
            }

            if (!$this->validate_archive($zip)) {
                $zip->close();
                throw new RuntimeException('Release archive contains an unsafe or invalid path.');
            }

            if (!$zip->extractTo($extractPath)) {
                $zip->close();
                throw new RuntimeException('Unable to extract release package.');
            }
            $zip->close();

            $newModulePath = $extractPath . 'magic_login' . DIRECTORY_SEPARATOR;
            $initFile = $newModulePath . 'magic_login.php';
            if (!is_file($initFile)) {
                throw new RuntimeException('Release does not contain a valid Magic Login module.');
            }

            $packageVersion = $this->read_module_version($initFile);
            if ($packageVersion !== (string) $release['version']) {
                throw new RuntimeException('Package version does not match the GitHub release tag.');
            }

            $manifest = $this->read_manifest($newModulePath . 'update_manifest.json');
            if (!$manifest || empty($manifest['version']) || (string) $manifest['version'] !== $packageVersion) {
                throw new RuntimeException('Release manifest is missing or does not match the package version.');
            }
            if ($automatic && empty($manifest['auto_update_safe'])) {
                throw new RuntimeException('This release requires manual update approval.');
            }

            $installedRow = $this->CI->db
                ->select('installed_version')
                ->where('module_name', 'magic_login')
                ->get(db_prefix() . 'modules')
                ->row();
            $installedVersion = $installedRow ? (string) $installedRow->installed_version : MAGIC_LOGIN_VERSION;

            $this->validate_migration_chain(
                $newModulePath . 'migrations' . DIRECTORY_SEPARATOR,
                $installedVersion,
                $packageVersion
            );

            $this->ensure_update_history_table();
            $updateId = $this->start_update_log(
                $installedVersion,
                $packageVersion,
                isset($release['tag']) ? (string) $release['tag'] : 'v' . $packageVersion,
                $actualHash,
                (bool) $automatic
            );

            $backupPath = $this->create_backup($modulePath, $installedVersion);
            if (!$backupPath) {
                throw new RuntimeException('Unable to create module backup.');
            }
            $this->update_update_log($updateId, ['backup_path' => $backupPath]);

            if (!$this->replace_directory($newModulePath, $modulePath)) {
                $restored = $this->restore_backup($backupPath, $modulePath);
                throw new RuntimeException(
                    $restored
                        ? 'Unable to replace module files. The previous files were restored.'
                        : 'Unable to replace module files and automatic file restoration failed. Backup retained at ' . $backupPath . '.'
                );
            }

            // From this point forward no automatic file rollback is permitted.
            // Database migrations can contain DDL that MySQL cannot reliably roll back.
            $migrationStarted = true;
            $migrationResult = $this->run_perfex_migrations($packageVersion);
            if ($migrationResult !== true) {
                throw new RuntimeException(
                    'Database migration failed: ' . $migrationResult
                    . '. The module backup was retained at ' . $backupPath
                    . '. Do not retry the update until the database state has been reviewed.'
                );
            }

            update_option('magic_login_release_cache_checked_at', '0');
            update_option('magic_login_release_cache', '');
            update_option('magic_login_last_update_status', 'Updated to ' . $packageVersion . ' at ' . date('Y-m-d H:i:s'));
            $this->finish_update_log($updateId, 'success', null);
            $this->prune_backups();
            $this->remove_directory($workDir);

            return [
                'ok'      => true,
                'message' => 'Magic Login updated successfully to v' . $packageVersion . '.',
                'version' => $packageVersion,
                'backup'  => $backupPath,
            ];
        } catch (Throwable $e) {
            $message = $e->getMessage();
            if ($migrationStarted && $backupPath !== '' && strpos($message, $backupPath) === false) {
                $message .= ' Backup retained at ' . $backupPath . '. Automatic rollback was intentionally not attempted after database migration began.';
            }

            update_option('magic_login_last_update_status', 'Update failed at ' . date('Y-m-d H:i:s') . ': ' . $message);
            $this->finish_update_log($updateId, 'failed', $message);
            log_message('error', 'Magic Login update failed: ' . $message);
            $this->remove_directory($workDir);

            return [
                'ok'      => false,
                'message' => $message,
                'backup'  => $backupPath,
            ];
        }
    }

    protected function github_json($url)
    {
        if (!function_exists('curl_init')) {
            return false;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/vnd.github+json',
                'X-GitHub-Api-Version: 2022-11-28',
            ],
            CURLOPT_USERAGENT      => 'Perfex-Magic-Login/' . (defined('MAGIC_LOGIN_VERSION') ? MAGIC_LOGIN_VERSION : 'unknown'),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $error !== '' || $status < 200 || $status >= 300) {
            return false;
        }

        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : false;
    }

    protected function download($url, $destination)
    {
        if (!$this->is_github_release_url($url)) {
            return false;
        }

        $fp = fopen($destination, 'wb');
        if (!$fp) {
            return false;
        }

        $ch = curl_init($url);
        $options = [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERAGENT      => 'Perfex-Magic-Login/' . MAGIC_LOGIN_VERSION,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if (defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
            $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
        }
        curl_setopt_array($ch, $options);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$ok || $status < 200 || $status >= 300 || !is_file($destination) || filesize($destination) < 1) {
            @unlink($destination);
            return false;
        }

        return true;
    }

    protected function is_github_release_url($url)
    {
        $parts = parse_url((string) $url);
        if (!$parts || strtolower(isset($parts['scheme']) ? $parts['scheme'] : '') !== 'https') {
            return false;
        }

        $host = strtolower(isset($parts['host']) ? $parts['host'] : '');
        return $host === 'github.com' || substr($host, -18) === '.githubusercontent.com';
    }

    protected function validate_archive(ZipArchive $zip)
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if ($name === '' || $name[0] === '/' || strpos($name, '../') !== false || strpos($name, "\0") !== false) {
                return false;
            }
            if ($name !== 'magic_login/' && strpos($name, 'magic_login/') !== 0) {
                return false;
            }
        }

        return true;
    }

    protected function read_module_version($file)
    {
        $contents = (string) file_get_contents($file);
        if (preg_match('/^Version:\s*(.+)$/mi', $contents, $match)) {
            return trim($match[1]);
        }
        return '';
    }

    protected function read_manifest($path)
    {
        if (!is_file($path)) {
            return false;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : false;
    }

    protected function validate_migration_chain($migrationPath, $fromVersion, $toVersion)
    {
        $from = (int) str_replace('.', '', $fromVersion);
        $to = (int) str_replace('.', '', $toVersion);
        if ($to <= $from) {
            return true;
        }

        $available = [];
        foreach (glob(rtrim($migrationPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*_*.php') as $file) {
            if (preg_match('/^(\d{3})_/', basename($file), $match)) {
                $available[(int) $match[1]] = true;
            }
        }

        $pending = array_keys(array_filter($available, function ($unused, $number) use ($from, $to) {
            return $number > $from && $number <= $to;
        }, ARRAY_FILTER_USE_BOTH));
        sort($pending, SORT_NUMERIC);

        if (empty($pending) || end($pending) !== $to) {
            throw new RuntimeException('Release does not contain the migration for target version ' . $to . '.');
        }

        for ($i = 1; $i < count($pending); $i++) {
            if (($pending[$i] - $pending[$i - 1]) !== 1) {
                throw new RuntimeException('Release contains a non-contiguous Perfex migration chain.');
            }
        }

        return true;
    }

    protected function run_perfex_migrations($targetVersion)
    {
        if (!class_exists('App_module_migration', false)) {
            require_once APPPATH . 'libraries/App_module_migration.php';
        }

        $migration = new class('magic_login') extends App_module_migration {
            public function set_magic_login_target($version)
            {
                $this->_migration_version = str_replace('.', '', (string) $version);
            }
        };

        $migration->set_magic_login_target($targetVersion);
        $result = $migration->to_latest();
        return $result === false ? $migration->error_string() : true;
    }

    protected function ensure_update_history_table()
    {
        $table = db_prefix() . 'magic_login_updates';
        if ($this->CI->db->table_exists($table)) {
            return true;
        }

        $this->CI->db->query('CREATE TABLE `' . $table . "` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `from_version` VARCHAR(32) NOT NULL,
            `to_version` VARCHAR(32) NOT NULL,
            `release_tag` VARCHAR(64) NULL,
            `checksum` CHAR(64) NULL,
            `automatic` TINYINT(1) NOT NULL DEFAULT 0,
            `status` VARCHAR(20) NOT NULL DEFAULT 'started',
            `error_message` TEXT NULL,
            `backup_path` VARCHAR(500) NULL,
            `started_at` DATETIME NOT NULL,
            `completed_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `status` (`status`),
            KEY `started_at` (`started_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=" . $this->CI->db->char_set . ';');

        return $this->CI->db->table_exists($table);
    }

    protected function start_update_log($fromVersion, $toVersion, $releaseTag, $checksum, $automatic)
    {
        $table = db_prefix() . 'magic_login_updates';
        if (!$this->CI->db->table_exists($table)) {
            return 0;
        }

        $this->CI->db->insert($table, [
            'from_version' => (string) $fromVersion,
            'to_version'   => (string) $toVersion,
            'release_tag'  => (string) $releaseTag,
            'checksum'     => (string) $checksum,
            'automatic'    => $automatic ? 1 : 0,
            'status'       => 'started',
            'started_at'   => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->CI->db->insert_id();
    }

    protected function update_update_log($id, array $data)
    {
        if ((int) $id < 1 || empty($data)) {
            return;
        }

        $table = db_prefix() . 'magic_login_updates';
        if (!$this->CI->db->table_exists($table)) {
            return;
        }

        $this->CI->db->where('id', (int) $id)->update($table, $data);
    }

    protected function finish_update_log($id, $status, $errorMessage = null)
    {
        if ((int) $id < 1) {
            return;
        }

        $this->update_update_log((int) $id, [
            'status'        => (string) $status,
            'error_message' => $errorMessage !== null ? (string) $errorMessage : null,
            'completed_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    protected function create_backup($modulePath, $version)
    {
        $backupDir = rtrim(TEMP_FOLDER, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'magic-login-backups' . DIRECTORY_SEPARATOR;
        if (!$this->ensure_directory($backupDir)) {
            return false;
        }

        $path = $backupDir . 'magic_login-' . preg_replace('/[^0-9A-Za-z._-]/', '-', $version) . '-' . date('Ymd-His') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($modulePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($modulePath));
            $archiveName = 'magic_login/' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            if ($item->isDir()) {
                $zip->addEmptyDir(rtrim($archiveName, '/'));
            } else {
                $zip->addFile($item->getPathname(), $archiveName);
            }
        }

        $zip->close();
        return is_file($path) ? $path : false;
    }

    protected function restore_backup($backupPath, $modulePath)
    {
        if (!is_file($backupPath)) {
            return false;
        }

        $this->remove_directory($modulePath);
        $zip = new ZipArchive();
        if ($zip->open($backupPath) !== true) {
            return false;
        }
        $ok = $zip->extractTo(APP_MODULES_PATH);
        $zip->close();
        return $ok;
    }

    protected function replace_directory($source, $destination)
    {
        $this->remove_directory($destination);
        if (!$this->ensure_directory($destination)) {
            return false;
        }
        return $this->copy_directory($source, $destination);
    }

    protected function copy_directory($source, $destination)
    {
        if (!$this->ensure_directory($destination)) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $destination . substr($item->getPathname(), strlen($source));
            if ($item->isDir()) {
                if (!$this->ensure_directory($target)) {
                    return false;
                }
            } else {
                if (!@copy($item->getPathname(), $target)) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function ensure_directory($path)
    {
        return is_dir($path) || @mkdir($path, defined('APP_CHMOD_DIR') ? APP_CHMOD_DIR : 0755, true);
    }

    protected function remove_directory($path)
    {
        if (!is_dir($path)) {
            return true;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        return @rmdir($path);
    }

    protected function prune_backups()
    {
        $backupDir = rtrim(TEMP_FOLDER, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'magic-login-backups' . DIRECTORY_SEPARATOR;
        $files = glob($backupDir . 'magic_login-*.zip');
        if (!$files || count($files) <= 3) {
            return;
        }

        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        foreach (array_slice($files, 3) as $file) {
            @unlink($file);
        }
    }
}
