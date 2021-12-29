<?php
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

    public static function run() {
        // Constants
        $date = date('Y-m-d_H_i_s');
        $php_path = __DIR__.'/deploy.php';
        $zip_path = __DIR__.'/deploy.zip';
        $unzip_path = __DIR__.'/unzip/';
        $base_path = substr(__DIR__, 0, strpos(__DIR__, self::getPublicPath()));
        $deploy_path = $base_path . self::getDeployPath();
        $destination_path = "{$deploy_path}/{$date}";
        $current_link_path = "{$deploy_path}/current";

        // Unzip the uploaded file with all the code to be deployed.
        $zip = new ZipArchive();
        $res = $zip->open($zip_path);
        if (!$res) {
            // Keep the zip (for debugging purposes).
            rename($zip_path, "./invalid_deploy_{$date}.zip");
            unlink($php_path);
            http_response_code(500);
            exit("Could not unzip deploy.zip\n");
        }
        if (!is_dir($unzip_path)) {
            mkdir($unzip_path, 0777, true);
        }
        $zip->extractTo($unzip_path);
        $zip->close();
        unlink($zip_path);

        // Move the code to the appropriate destination.
        rename($unzip_path, $destination_path);

        // Redirect current link to the new deployment.
        unlink($current_link_path);
        symlink($destination_path, $current_link_path);

        // Clean up.
        rmdir($unzip_path);
        unlink($php_path);
        rmdir(dirname($php_path));

        echo "deploy:SUCCESS";
    }

    protected static function getDeployPath() {
        return self::getOverrideOrDefault(self::DEPLOY_PATH_OVERRIDE, '');
    }

    protected static function getPublicPath() {
        return self::getOverrideOrDefault(self::PUBLIC_PATH_OVERRIDE, '');
    }

    private static function getOverrideOrDefault($override, $default) {
        $is_overridden = substr($override, 0, 3) !== '%%%';
        if ($is_overridden) {
            return $override;
        }
        return $default;
    }
}

if ($_SERVER['SCRIPT_FILENAME'] === realpath(__FILE__)) {
    RemoteDeployBootstrap::run();
}
