<?php

namespace PhpDeploy;

abstract class AbstractDeploy implements \Psr\Log\LoggerAwareInterface {
    public const MAX_DEPLOY_SCRIPT_ATTEMPTS = 3;

    protected $local_build_folder_path;
    protected $local_zip_path;
    protected $flysystem_filesystem;
    protected $remote_public_random_deploy_dirname;

    public function injectRemoteLogger($remote_logger) {
        $remote_logger_wrapper = new RemoteDeployLoggerWrapper($remote_logger);
        $this->logger = $remote_logger_wrapper;
    }

    public function getArgs() {
        return [
            'remote_public_random_deploy_dirname' => $this->remote_public_random_deploy_dirname,
        ];
    }

    public function injectArgs($args) {
        $this->remote_public_random_deploy_dirname =
            $args['remote_public_random_deploy_dirname'] ?? null;
    }

    abstract public function getRemotePublicPath();

    abstract public function getRemotePublicUrl();

    abstract public function getRemotePrivatePath();

    public function buildAndDeploy() {
        $this->build();
        $result = $this->deploy();
        $this->afterDeploy($result);
    }

    protected function afterDeploy($result) {
    }

    abstract public function install($public_path);

    public function build() {
        $this->logger->info("Build...");
        $build_path = $this->getLocalBuildFolderPath();
        if (!is_dir($build_path)) {
            mkdir($build_path, 0755, true);
        }
        $this->logger->info("Populate build folder...");
        $this->populateFolder();
        $this->logger->info("Zip build folder...");
        $this->zipFolder();
        $this->logger->info("Build done.");
    }

    abstract protected function populateFolder();

    private function zipFolder() {
        $build_path = $this->getLocalBuildFolderPath();
        $real_build_path = realpath($build_path).'/';
        $zip_path = $this->getLocalZipPath();
        $this->logger->info("Zipping build folder...");
        $zip = new \ZipArchive();
        $res = $zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if (!$res) {
            // @codeCoverageIgnoreStart
            // Reason: Cannot provoke this case!
            throw new \Exception("Could not create ZIP file");
            // @codeCoverageIgnoreEnd
        }
        $directory = new \RecursiveDirectoryIterator($real_build_path);
        $iterator = new \RecursiveIteratorIterator($directory);
        foreach ($iterator as $item) {
            $filename = $item->getFileName();
            if ($filename !== '.' && $filename !== '..') {
                $real_path = $item->getRealPath();
                if ($real_path && is_file($real_path)) {
                    $relative_path = substr($real_path, strlen($real_build_path));
                    $zip->addFile($real_path, $relative_path);
                }
            }
        }
        $zip->close();
        $this->logger->info("Zipping done.");
    }

    public function deploy() {
        $this->logger->info("Deploy...");
        $local_zip_path = $this->getLocalZipPath();
        $remote_zip_path = $this->getRemoteZipPath();
        $local_script_path = __DIR__.'/RemoteDeployBootstrap.php';
        $remote_script_path = $this->getRemoteScriptPath();
        $remote_deploy_path = $this->getRemoteDeployPath();
        $remote_public_path = $this->getRemotePublicPath();
        $remote_fs = $this->getFlysystemFilesystemSingleton();

        $zip_size = filesize($local_zip_path);
        $pretty_zip_size = $zip_size ? $this->humanFileSize($zip_size) : '? bytes';
        $this->logger->info("Upload ({$pretty_zip_size})...");
        try {
            $remote_fs->createDirectory(dirname($remote_zip_path));
        } catch (\Throwable $th) {
            // ignore
        }
        $local_zip_stream = fopen($local_zip_path, 'r');
        $remote_fs->writeStream($remote_zip_path, $local_zip_stream);
        fclose($local_zip_stream);
        $local_script_contents = file_get_contents($local_script_path);
        $remote_script_contents = str_replace(
            ['%%%DEPLOY_PATH_OVERRIDE%%%', '%%%PUBLIC_PATH_OVERRIDE%%%', '%%%ARGS_OVERRIDE%%%'],
            [$remote_deploy_path, $remote_public_path, json_encode($this->getArgs())],
            $local_script_contents,
        );
        $remote_fs->write($remote_script_path, $remote_script_contents);
        $this->logger->info("Upload done.");

        $base_url = $this->getRemotePublicUrl();
        $deploy_dirname = $this->getRemotePublicRandomDeployDirname();
        $url = "{$base_url}/{$deploy_dirname}/deploy.php";

        $this->logger->info("Running deploy script ({$url})...");
        $deploy_out = $this->invokeDeployScript($url);
        $deploy_response = json_decode($deploy_out, true);
        $remote_logs = $deploy_response['log'] ?? [];
        foreach ($remote_logs as $remote_log) {
            $level = $remote_log['level'];
            $message = $this->getRemoteLogMessage($remote_log);
            $context = $remote_log['context'] ?? [];
            $this->logger->log($level, $message, $context);
        }
        $is_success = $deploy_response['success'] ?? false;
        if (!$is_success) {
            throw new \Exception("Deployment failed: {$deploy_out}");
        }
        $result = $deploy_response['result'] ?? null;
        $json_result = json_encode($result);
        $this->logger->info("Deploy done with result: {$json_result}");
        return $result;
    }

    protected function humanFileSize($size, $unit = "") {
        if ((!$unit && $size >= 1 << 30) || $unit == "GB") {
            return number_format($size / (1 << 30), 2, ".", "'")." GB";
        }
        if ((!$unit && $size >= 1 << 20) || $unit == "MB") {
            return number_format($size / (1 << 20), 2, ".", "'")." MB";
        }
        if ((!$unit && $size >= 1 << 10) || $unit == "KB") {
            return number_format($size / (1 << 10), 2, ".", "'")." KB";
        }
        return number_format($size, 0, ".", "'")." bytes";
    }

    private function invokeDeployScript($url) {
        $errors = [];
        for ($i = 0; $i < self::MAX_DEPLOY_SCRIPT_ATTEMPTS; $i++) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            $result = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            if (!$errno) {
                return $result;
            }
            $errors[] = "{$error} ({$errno})";
        }
        throw new \Exception("Error invoking deploy script: ".implode(' / ', $errors));
    }

    private function getFlysystemFilesystemSingleton() {
        if ($this->flysystem_filesystem === null) {
            $this->flysystem_filesystem = $this->getFlysystemFilesystem();
        }
        return $this->flysystem_filesystem;
    }

    abstract protected function getFlysystemFilesystem();

    protected function getRemoteLogMessage($entry) {
        $date = date('Y-m-d H:i:s.v', $entry['timestamp']);
        $message = $entry['message'];
        return "remote> {$date} {$message}";
    }

    public function getLocalBuildFolderPath() {
        if ($this->local_build_folder_path !== null) {
            return $this->local_build_folder_path;
        }
        $tmp_dir = $this->getLocalTmpDir();
        do {
            $random = $this->getRandomPathComponent();
            $this->local_build_folder_path = "{$tmp_dir}/{$random}/";
        } while (is_dir($this->local_build_folder_path));
        return $this->local_build_folder_path;
    }

    public function getLocalZipPath() {
        if ($this->local_zip_path !== null) {
            return $this->local_zip_path;
        }
        $tmp_dir = $this->getLocalTmpDir();
        do {
            $random = $this->getRandomPathComponent();
            $this->local_zip_path = "{$tmp_dir}/{$random}.zip";
        } while (is_dir($this->local_zip_path));
        return $this->local_zip_path;
    }

    protected function getLocalTmpDir() {
        // @codeCoverageIgnoreStart
        // Reason: Useless to test this!
        return sys_get_temp_dir();
        // @codeCoverageIgnoreEnd
    }

    public function getRemoteDeployPath() {
        $private_path = $this->getRemotePrivatePath();
        $deploy_dirname = $this->getRemoteDeployDirname();
        return "{$private_path}/{$deploy_dirname}";
    }

    public function getRemoteDeployDirname() {
        return 'deploy';
    }

    public function getRemoteZipPath() {
        $public_path = $this->getRemotePublicPath();
        $deploy_dirname = $this->getRemotePublicRandomDeployDirname();
        return "{$public_path}/{$deploy_dirname}/deploy.zip";
    }

    public function getRemoteScriptPath() {
        $public_path = $this->getRemotePublicPath();
        $deploy_dirname = $this->getRemotePublicRandomDeployDirname();
        return "{$public_path}/{$deploy_dirname}/deploy.php";
    }

    protected function getRemotePublicRandomDeployDirname() {
        if (!$this->remote_public_random_deploy_dirname) {
            $public_path = $this->getRemotePublicPath();
            $remote_fs = $this->getFlysystemFilesystemSingleton();

            $existing_entries = [];
            $listing = $remote_fs->listContents($public_path);
            foreach ($listing as $item) {
                $entry_name = basename($item->path());
                $existing_entries[$entry_name] = true;
            }

            for ($i = 0; $i < 10; $i++) {
                $random_dirname = $this->getRandomPathComponent();
                $already_exists = $existing_entries[$random_dirname] ?? false;
                if (!$already_exists) {
                    $this->remote_public_random_deploy_dirname = $random_dirname;
                    break;
                }
            }
        }
        if (!$this->remote_public_random_deploy_dirname) {
            // This should realistically never happen!
            throw new \Exception("Could not find a random directory to deploy to!");
        }
        return $this->remote_public_random_deploy_dirname;
    }

    protected function getRandomPathComponent() {
        $base64_string = base64_encode(openssl_random_pseudo_bytes(18));
        return str_replace(['+', '/', '='], ['-', '_', ''], $base64_string);
    }
}
