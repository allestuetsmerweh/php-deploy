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
    public $DEPLOY_PATH_OVERRIDE = '%%%DEPLOY_PATH_OVERRIDE%%%';
    public $PUBLIC_PATH_OVERRIDE = '%%%PUBLIC_PATH_OVERRIDE%%%';
    public $ARGS_OVERRIDE = '%%%ARGS_OVERRIDE%%%';

    public $logger;

    public function run() {
        if (!$this->logger) {
            throw new \Exception("RemoteDeployBootstrap::run needs a logger!");
        }
        try {
            $this->logger->info('Initialize...');
            $date = $this->getDateString();
            $public_deploy_path = $this->getPublicDeployPath();
            $public_path = $this->getPublicPath();
            $php_path = "{$public_deploy_path}/deploy.php";
            $zip_path = "{$public_deploy_path}/deploy.zip";
            $invalid_zip_path = "{$public_deploy_path}/invalid_deploy_{$date}.zip";
            $base_index = strpos($public_deploy_path, "/{$public_path}/");
            if ($base_index === false) {
                throw new \Exception("Did not find the public path ({$public_path}) in {$public_deploy_path}");
            }
            $base_path = substr($public_deploy_path, 0, $base_index);
            $deploy_path = $base_path.'/'.$this->getDeployPath();
            $candidate_path = "{$deploy_path}/candidate";
            $live_path = "{$deploy_path}/live";
            $previous_path = "{$deploy_path}/previous";
            $invalid_candidate_path = "{$deploy_path}/invalid_candidate_{$date}";
            $residual_candidate_path = "{$deploy_path}/residual_candidate_{$date}";
            $error_log_path = "{$deploy_path}/deploy_errors.log";

            ini_set('log_errors', 1);
            ini_set('error_log', $error_log_path);
            error_reporting(E_ALL);

            $this->logger->info('Run some checks...');
            if (!is_dir($deploy_path)) {
                throw new \Exception("Deploy path ({$deploy_path}) does not exist");
            }

            if (is_dir($candidate_path)) {
                $this->logger->info('A previous deployment failed. Save residual candidate...');
                if (!rename($candidate_path, $residual_candidate_path)) {
                    // @codeCoverageIgnoreStart
                    // Reason: Hard to test!
                    throw new \Exception("Could not rename {$candidate_path} to {$residual_candidate_path}");
                    // @codeCoverageIgnoreEnd
                }
            }

            $this->logger->info('Unzip the uploaded file to candidate directory...');
            mkdir($candidate_path);
            $zip = new \ZipArchive();
            $zip->open($zip_path);
            $zip->extractTo($candidate_path);
            $zip->close();

            $this->logger->info('Remove the zip file...');
            unlink($zip_path);

            $this->logger->info('Put the candidate live...');
            if (is_dir($previous_path)) {
                $this->remove_r($previous_path);
            }
            if (is_dir($live_path)) {
                if (!rename($live_path, $previous_path)) {
                    // @codeCoverageIgnoreStart
                    // Reason: Hard to test!
                    throw new \Exception("Could not rename {$live_path} to {$previous_path}");
                    // @codeCoverageIgnoreEnd
                }
            }
            if (!rename($candidate_path, $live_path)) {
                // @codeCoverageIgnoreStart
                // Reason: Hard to test!
                throw new \Exception("Could not rename {$live_path} to {$previous_path}");
                // @codeCoverageIgnoreEnd
            }

            $this->logger->info('Clean up...');
            if (is_file($php_path)) {
                unlink($php_path);
            }
            rmdir(dirname($php_path));

            $this->logger->info('Install...');
            $install_script_path = "{$live_path}/Deploy.php";
            if (!is_file($install_script_path)) {
                throw new \Exception("Deploy.php not found");
            }
            require_once $install_script_path;
            if (!class_exists('\Deploy') || !method_exists('\Deploy', 'install')) {
                // @codeCoverageIgnoreStart
                // Reason: Hard to test!
                throw new \Exception("Class Deploy is not defined in Deploy.php");
                // @codeCoverageIgnoreEnd
            }
            $deploy = new \Deploy();
            if (method_exists('\Deploy', 'injectRemoteLogger')) {
                $deploy->injectRemoteLogger($this->logger);
            }
            if (method_exists('\Deploy', 'injectArgs')) {
                $deploy->injectArgs($this->getArgs());
            }
            $install_path = $base_path.'/'.$this->getPublicPath();
            $deploy->install($install_path);
            $this->logger->info('Done.');
        } catch (\Throwable $th) {
            // Keep the zip (for debugging purposes).
            if (isset($zip_path) && is_file($zip_path) && isset($invalid_zip_path)) {
                rename($zip_path, $invalid_zip_path);
            }
            if (isset($php_path) && is_file($php_path)) {
                unlink($php_path);
            }
            if (isset($candidate_path) && is_dir($candidate_path) && isset($invalid_candidate_path)) {
                rename($candidate_path, $invalid_candidate_path);
            }
            throw $th;
        }
    }

    // This file needs to be dependency-free!
    protected function remove_r($path) {
        if (is_dir($path)) {
            $entries = scandir($path);
            foreach ($entries as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $entry_path = "{$path}/{$entry}";
                    $this->remove_r($entry_path);
                }
            }
            rmdir($path);
        } elseif (is_link($path)) {
            unlink($path);
        } elseif (is_file($path)) {
            unlink($path);
        }
    }

    protected function getDateString() {
        return date('Y-m-d_H_i_s');
    }

    protected function getDeployPath() {
        return $this->getOverrideOrDefault($this->DEPLOY_PATH_OVERRIDE, '');
    }

    protected function getPublicPath() {
        return $this->getOverrideOrDefault($this->PUBLIC_PATH_OVERRIDE, '');
    }

    protected function getArgs() {
        $args_json = $this->getOverrideOrDefault($this->ARGS_OVERRIDE, '{}');
        return json_decode($args_json, true) ?? [];
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

class RemoteDeployLogger {
    public $messages = [];

    public function __call($name, $arguments) {
        if (count($arguments) > 0) {
            $this->log($name, $arguments[0], $arguments[1] ?? []);
        }
    }

    public function log($level, string|\Stringable $message, array $context = []): void {
        $this->messages[] = [
            'level' => $level,
            'timestamp' => microtime(true),
            'message' => $message,
            'context' => $context,
        ];
    }
}

if ($_SERVER['SCRIPT_FILENAME'] === realpath(__FILE__)) {
    try {
        set_time_limit(4000);
        ignore_user_abort(true);

        $remote_deploy_bootstrap = new RemoteDeployBootstrap();
        $logger = new RemoteDeployLogger();
        $remote_deploy_bootstrap->logger = $logger;
        $remote_deploy_bootstrap->run();
        echo json_encode([
            'success' => true,
            'log' => $logger->messages ?? [],
        ]);
    } catch (\Throwable $th) {
        echo json_encode([
            'error' => [
                'type' => get_class($th),
                'message' => $th->getMessage(),
            ],
            'log' => $logger->messages ?? [],
        ]);
    }
}
