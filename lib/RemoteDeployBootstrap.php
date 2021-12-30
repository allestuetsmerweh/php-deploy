<?php

namespace PhpDeploy;

/**
 * Deploy code on hoster.
 *
 * Premise: CI has connected to the hoster and uploaded this file and
 * the ZIP file (containing all code) to the public directory and has started an
 * HTTP connection to invoke this file.
 */
class RemoteDeployBootstrap {
    const DEPLOY_PATH_OVERRIDE = '%%%DEPLOY_PATH_OVERRIDE%%%';
    const PUBLIC_PATH_OVERRIDE = '%%%PUBLIC_PATH_OVERRIDE%%%';

    public function run() {
        try {
            // Constants
            $date = $this->getDateString();
            $public_deploy_path = $this->getPublicDeployPath();
            $public_path = $this->getPublicPath();
            $php_path = "{$public_deploy_path}/deploy.php";
            $zip_path = "{$public_deploy_path}/deploy.zip";
            $unzip_path = "{$public_deploy_path}/unzip/";
            $base_index = strpos($public_deploy_path, "/{$public_path}/");
            if ($base_index === false) {
                throw new \Exception("Did not find the public path ({$public_path}) in {$public_deploy_path}");
            }
            $base_path = substr($public_deploy_path, 0, $base_index);
            $deploy_path = $base_path.'/'.$this->getDeployPath();
            $destination_path = "{$deploy_path}/{$date}";
            $current_link_path = "{$deploy_path}/current";

            // Unzip the uploaded file with all the code to be deployed.
            if (!is_dir($unzip_path)) {
                mkdir($unzip_path, 0777, true);
            }
            $zip = new \ZipArchive();
            $zip->open($zip_path);
            $zip->extractTo($unzip_path);
            $zip->close();

            unlink($zip_path);

            // Move the code to the appropriate destination.
            $has_successfully_renamed = rename($unzip_path, $destination_path);
            if (!$has_successfully_renamed) {
                // @codeCoverageIgnoreStart
                // Reason: Hard to test!
                throw new \Exception("Could not rename {$unzip_path} to {$destination_path}");
                // @codeCoverageIgnoreEnd
            }

            // Redirect current link to the new deployment.
            if (is_link($current_link_path)) {
                unlink($current_link_path);
            }
            $has_successfully_symlinked = symlink($destination_path, $current_link_path);
            if (!$has_successfully_symlinked) {
                // @codeCoverageIgnoreStart
                // Reason: Hard to test!
                throw new \Exception("Could not symlink {$current_link_path} to {$destination_path}");
                // @codeCoverageIgnoreEnd
            }

            // Clean up.
            if (is_file($php_path)) {
                unlink($php_path);
            }
            rmdir(dirname($php_path));

            echo "deploy:SUCCESS";
        } catch (\Throwable $th) {
            // Keep the zip (for debugging purposes).
            if (is_file($zip_path)) {
                rename($zip_path, "{$public_deploy_path}/invalid_deploy_{$date}.zip");
            }
            if (is_file($php_path)) {
                unlink($php_path);
            }
            throw $th;
        }
    }

    protected function getDateString() {
        return date('Y-m-d_H_i_s');
    }

    protected function getDeployPath() {
        return $this->getOverrideOrDefault(self::DEPLOY_PATH_OVERRIDE, '');
    }

    protected function getPublicPath() {
        return $this->getOverrideOrDefault(self::PUBLIC_PATH_OVERRIDE, '');
    }

    protected function getPublicDeployPath() {
        return __DIR__;
    }

    protected function getOverrideOrDefault($override, $default) {
        $is_overridden = substr($override, 0, 3) !== '%%%';
        if ($is_overridden) {
            return $override;
        }
        return $default;
    }
}

if ($_SERVER['SCRIPT_FILENAME'] === realpath(__FILE__)) {
    $remote_deploy_bootstrap = new RemoteDeployBootstrap();
    try {
        $remote_deploy_bootstrap->run();
    } catch (\Throwable $th) {
        echo "deploy:ERROR:{$th->getMessage()}";
    }
}
