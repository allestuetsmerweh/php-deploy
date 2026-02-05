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
    public string $DEPLOY_PATH_OVERRIDE = '%%%DEPLOY_PATH_OVERRIDE%%%';
    public string $PUBLIC_PATH_OVERRIDE = '%%%PUBLIC_PATH_OVERRIDE%%%';
    public string $ARGS_OVERRIDE = '%%%ARGS_OVERRIDE%%%';

    public RemoteDeployLogger $logger;

    public string $install_path;
    public string $php_path;
    public string $zip_path;
    public string $invalid_zip_path;
    public string $deploy_path;
    public string $candidate_path;
    public string $live_path;
    public string $previous_path;
    public string $invalid_candidate_path;
    public string $residual_candidate_path;

    public function __construct(RemoteDeployLogger $logger) {
        $this->logger = $logger;
    }

    /** @return array<string> */
    public function run(): array {
        try {
            $this->initialize();
            $this->maybeArchiveResidualCandidate();
            $this->unzipCandidate();
            $this->putCandidateLive();
            $this->cleanUp();
            $result = $this->install();
            $this->logger->info('Done.');
            return $result;
        } catch (\Throwable $th) {
            $this->cleanUp();
            throw $th;
        }
    }

    protected function initialize(): void {
        $this->logger->info('Initialize...');
        $date = $this->getDateString();
        $public_deploy_path = $this->getPublicDeployPath();
        $this->install_path = "{$this->getBasePath()}/{$this->getPublicPath()}";
        $this->php_path = "{$public_deploy_path}/deploy.php";
        $this->zip_path = "{$public_deploy_path}/deploy.zip";
        $this->invalid_zip_path = "{$public_deploy_path}/invalid_deploy_{$date}.zip";
        $this->deploy_path = "{$this->getBasePath()}/{$this->getDeployPath()}";
        $error_log_path = "{$this->deploy_path}/deploy_errors.log";
        $this->candidate_path = "{$this->deploy_path}/candidate";
        $this->live_path = "{$this->deploy_path}/live";
        $this->previous_path = "{$this->deploy_path}/previous";
        $this->invalid_candidate_path = "{$this->deploy_path}/invalid_candidate_{$date}";
        $this->residual_candidate_path = "{$this->deploy_path}/residual_candidate_{$date}";

        ini_set('log_errors', 1);
        ini_set('error_log', $error_log_path);
        error_reporting(E_ALL);

        $this->logger->info('Run some checks...');
        if (!is_dir($this->deploy_path)) {
            throw new \Exception("Deploy path ({$this->deploy_path}) does not exist");
        }
    }

    protected function maybeArchiveResidualCandidate(): void {
        if (is_dir($this->candidate_path)) {
            $this->logger->info('A previous deployment failed. Save residual candidate...');
            if (!rename($this->candidate_path, $this->residual_candidate_path)) {
                // @codeCoverageIgnoreStart
                // Reason: Hard to test!
                throw new \Exception("Could not rename {$this->candidate_path} to {$this->residual_candidate_path}");
                // @codeCoverageIgnoreEnd
            }
        }
    }

    protected function unzipCandidate(): void {
        $this->logger->info('Unzip the uploaded file to candidate directory...');
        mkdir($this->candidate_path);
        $zip = new \ZipArchive();
        $zip->open($this->zip_path);
        $zip->extractTo($this->candidate_path);
        $zip->close();

        $this->logger->info('Remove the zip file...');
        unlink($this->zip_path);
    }

    protected function putCandidateLive(): void {
        $this->logger->info('Put the candidate live...');
        if (is_dir($this->previous_path)) {
            $this->removeRecursive($this->previous_path);
        }
        if (is_dir($this->live_path)) {
            if (!rename($this->live_path, $this->previous_path)) {
                // @codeCoverageIgnoreStart
                // Reason: Hard to test!
                throw new \Exception("Could not rename {$this->live_path} to {$this->previous_path}");
                // @codeCoverageIgnoreEnd
            }
        }
        if (!rename($this->candidate_path, $this->live_path)) {
            // @codeCoverageIgnoreStart
            // Reason: Hard to test!
            throw new \Exception("Could not rename {$this->live_path} to {$this->previous_path}");
            // @codeCoverageIgnoreEnd
        }
    }

    protected function cleanUp(): void {
        $this->logger->info('Clean up...');
        // Keep the zip (for debugging purposes).
        if (isset($this->zip_path) && is_file($this->zip_path) && isset($this->invalid_zip_path)) {
            rename($this->zip_path, $this->invalid_zip_path);
        }
        if (isset($this->php_path) && is_file($this->php_path)) {
            unlink($this->php_path);
            rmdir(dirname($this->php_path));
        }
        if (isset($this->candidate_path) && is_dir($this->candidate_path) && isset($this->invalid_candidate_path)) {
            rename($this->candidate_path, $this->invalid_candidate_path);
        }
    }

    /** @return array<string> */
    protected function install(): array {
        $this->logger->info('Install...');
        $install_script_path = "{$this->live_path}/Deploy.php";
        if (!is_file($install_script_path)) {
            throw new \Exception("Deploy.php not found");
        }
        require_once $install_script_path;
        // @phpstan-ignore-next-line function.alreadyNarrowedType
        if (!class_exists('\Deploy') || !method_exists('\Deploy', 'install')) {
            // @codeCoverageIgnoreStart
            // Reason: Hard to test!
            throw new \Exception("Class Deploy is not defined in Deploy.php");
            // @codeCoverageIgnoreEnd
        }
        $deploy = new \Deploy();
        // @phpstan-ignore-next-line function.alreadyNarrowedType
        if (method_exists('\Deploy', 'injectRemoteLogger')) {
            $deploy->injectRemoteLogger($this->logger);
        }
        // @phpstan-ignore-next-line function.alreadyNarrowedType
        if (method_exists('\Deploy', 'injectArgs')) {
            $deploy->injectArgs($this->getArgs());
        }
        return $deploy->install($this->install_path);
    }

    // This file needs to be dependency-free!
    protected function removeRecursive(string $path): void {
        if (is_dir($path)) {
            $entries = scandir($path);
            if ($entries) {
                foreach ($entries as $entry) {
                    if ($entry !== '.' && $entry !== '..') {
                        $entry_path = "{$path}/{$entry}";
                        $this->removeRecursive($entry_path);
                    }
                }
            }
            rmdir($path);
        } elseif (is_link($path)) {
            unlink($path);
        } elseif (is_file($path)) {
            unlink($path);
        }
    }

    protected function getDateString(): string {
        return date('Y-m-d_H_i_s');
    }

    protected function getBasePath(): string {
        $public_deploy_path = $this->getPublicDeployPath();
        $public_path = $this->getPublicPath();
        $base_index = strpos($public_deploy_path, "/{$public_path}/");
        if ($base_index === false) {
            throw new \Exception("Did not find the public path ({$public_path}) in {$public_deploy_path}");
        }
        return substr($public_deploy_path, 0, $base_index);
    }

    protected function getDeployPath(): string {
        return $this->getOverrideOrDefault($this->DEPLOY_PATH_OVERRIDE, '');
    }

    protected function getPublicPath(): string {
        return $this->getOverrideOrDefault($this->PUBLIC_PATH_OVERRIDE, '');
    }

    /** @return array<string, mixed> */
    protected function getArgs(): array {
        $args_json = $this->getOverrideOrDefault($this->ARGS_OVERRIDE, '{}');
        return json_decode($args_json, true) ?? [];
    }

    protected function getPublicDeployPath(): string {
        return __DIR__;
    }

    protected function getOverrideOrDefault(string $override, string $default): string {
        $is_overridden = substr($override, 0, 3) !== '%%%';
        if ($is_overridden) {
            return $override;
        }
        return $default;
    }
}

class RemoteDeployLogger {
    /** @var array<array{level: string, timestamp: float, message: string|\Stringable, context: array<mixed>}> */
    public array $messages = [];

    /** @param array<mixed> $context */
    public function log(string $level, string|\Stringable $message, array $context = []): void {
        $this->messages[] = [
            'level' => $level,
            'timestamp' => microtime(true),
            'message' => $message,
            'context' => $context,
        ];
    }

    /** @param array<mixed> $context */
    public function info(string|\Stringable $message, array $context = []): void {
        $this->log('info', $message, $context);
    }

    /**
     * Fallback function for log levels that are not explicitly implemented.
     *
     * @param array{0: string|\Stringable, 1: ?array<mixed>} $arguments
     */
    public function __call(string $name, array $arguments): void {
        $this->log($name, $arguments[0], $arguments[1] ?? []);
    }
}

if ($_SERVER['SCRIPT_FILENAME'] === realpath(__FILE__)) {
    try {
        set_time_limit(4000);
        ignore_user_abort(true);

        $logger = new RemoteDeployLogger();
        $remote_deploy_bootstrap = new RemoteDeployBootstrap($logger);
        $result = $remote_deploy_bootstrap->run();
        echo json_encode([
            'success' => true,
            'result' => $result,
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
