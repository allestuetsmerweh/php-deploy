<?php

namespace PhpDeploy;

abstract class AbstractDeploy {
    protected $local_build_folder_path = null;
    protected $local_zip_path = null;

    abstract public function getRemotePublicPath();
    abstract public function getRemotePublicUrl();

    public function buildAndDeploy() {
        $this->build();
        $this->deploy();
    }

    public function build() {
        $build_path = $this->getLocalBuildFolderPath();
        if (!is_dir($build_path)) {
            mkdir($build_path, 0755, true);
        }
        $this->populateFolder();
        $this->zipFolder();
    }

    abstract protected function populateFolder();

    private function zipFolder() {
        $build_path = $this->getLocalBuildFolderPath();
        $real_build_path = realpath($build_path);
        $zip_path = $this->getLocalZipPath();
        if (!is_dir(dirname($zip_path))) {
            mkdir(dirname($zip_path), 0755, true);
        }
        $zip = new \ZipArchive();
        $res = $zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if (!$res) {
            // @codeCoverageIgnoreStart
            // Reason: Cannot provoke this case!
            throw new \Exception("Could not create ZIP file");
            // @codeCoverageIgnoreEnd
        }
        $zip->addGlob("{$real_build_path}/*", GLOB_BRACE, ['remove_path' => "{$real_build_path}/"]);
        $zip->close();
    }

    public function deploy() {
        $local_zip_path = $this->getLocalZipPath();
        $remote_zip_path = $this->getRemoteZipPath();
        $local_script_path = __DIR__.'/remote_deploy.php';
        $remote_script_path = $this->getRemoteScriptPath();
        $remote_fs = $this->getFlysystemFilesystem();
        try {
            $remote_fs->createDirectory(dirname($remote_zip_path));
        } catch (FilesystemException | UnableToCreateDirectory $exception) {
            // ignore
        }        
        $local_zip_stream = fopen($local_zip_path, 'r');
        $remote_fs->writeStream($remote_zip_path, $local_zip_stream);
        fclose($local_zip_stream);
        $local_script_stream = fopen($local_script_path, 'r');
        $remote_fs->writeStream($remote_script_path, $local_script_stream);
        fclose($local_script_stream);
    }

    abstract protected function getFlysystemFilesystem();

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
        return sys_get_temp_dir();
    }

    public function getRemoteDeployPath() {
        return 'deploy/';
    }

    public function getRemoteZipPath() {
        $public_path = $this->getRemotePublicPath();
        $deploy_path = $this->getRemoteDeployPath();
        return "{$public_path}/{$deploy_path}/deploy.zip";
    }

    public function getRemoteScriptPath() {
        $public_path = $this->getRemotePublicPath();
        $deploy_path = $this->getRemoteDeployPath();
        return "{$public_path}/{$deploy_path}/deploy.php";
    }

    protected function getRandomPathComponent() {
        $base64_string = base64_encode(openssl_random_pseudo_bytes(18));
        return str_replace(['+', '/', '='], ['-', '_', ''], $base64_string);
    }
}
