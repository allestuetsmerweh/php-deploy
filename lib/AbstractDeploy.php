<?php

namespace PhpDeploy;

abstract class AbstractDeploy {
    protected $local_build_folder_path;
    protected $local_zip_path;
    protected $flysystem_filesystem;
    protected $remote_public_random_deploy_dirname;

    abstract public function getRemotePublicPath();

    abstract public function getRemotePublicUrl();

    abstract public function getRemotePrivatePath();

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
        $real_build_path = realpath($build_path).'/';
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
        $directory = new \RecursiveDirectoryIterator($real_build_path);
        $iterator = new \RecursiveIteratorIterator($directory);
        foreach ($iterator as $item) {
            $filename = $item->getFileName();
            if ($filename !== '.' && $filename !== '..') {
                $real_path = $item->getRealPath();
                $relative_path = substr($real_path, strlen($real_build_path));
                $zip->addFile($real_path, $relative_path);
            }
        }
        $zip->close();
    }

    public function deploy() {
        $local_zip_path = $this->getLocalZipPath();
        $remote_zip_path = $this->getRemoteZipPath();
        $local_script_path = __DIR__.'/RemoteDeployBootstrap.php';
        $remote_script_path = $this->getRemoteScriptPath();
        $remote_deploy_path = $this->getRemoteDeployPath();
        $remote_public_path = $this->getRemotePublicPath();
        $remote_fs = $this->getFlysystemFilesystemSingleton();

        try {
            $remote_fs->createDirectory(dirname($remote_zip_path));
        } catch (FilesystemException | UnableToCreateDirectory $exception) {
            // ignore
        }
        $local_zip_stream = fopen($local_zip_path, 'r');
        $remote_fs->writeStream($remote_zip_path, $local_zip_stream);
        fclose($local_zip_stream);
        $local_script_contents = file_get_contents($local_script_path);
        $remote_script_contents = str_replace(
            ['%%%DEPLOY_PATH_OVERRIDE%%%', '%%%PUBLIC_PATH_OVERRIDE%%%'],
            [$remote_deploy_path, $remote_public_path],
            $local_script_contents,
        );
        $remote_fs->write($remote_script_path, $remote_script_contents);

        $url = $this->getRemotePublicUrl();

        $deploy_out = file_get_contents($url);
        echo $deploy_out;
    }

    private function getFlysystemFilesystemSingleton() {
        if ($this->flysystem_filesystem === null) {
            $this->flysystem_filesystem = $this->getFlysystemFilesystem();
        }
        return $this->flysystem_filesystem;
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
        $private_path = $this->getRemotePrivatePath();
        $deploy_dirname = $this->getRemoteDeployDirname();
        return "{$private_path}/{$deploy_dirname}/";
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
            throw new Exception("Could not find a random directory to deploy to!");
        }
        return $this->remote_public_random_deploy_dirname;
    }

    protected function getRandomPathComponent() {
        $base64_string = base64_encode(openssl_random_pseudo_bytes(18));
        return str_replace(['+', '/', '='], ['-', '_', ''], $base64_string);
    }
}
