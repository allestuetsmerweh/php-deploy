<?php

class Deploy {
    protected ?string $remote_public_random_deploy_dirname = null;

    /** @param array<string, string> $args */
    public function injectArgs(array $args): void {
        $this->remote_public_random_deploy_dirname =
            $args['remote_public_random_deploy_dirname'] ?? null;
    }

    /** @return array<string, string> */
    public function install(string $public_path): array {
        $private_path = __DIR__;
        copy("{$private_path}/test.txt", "{$public_path}/index.txt");
        $is_match = (bool) preg_match('/^[\S]{24}$/', $this->remote_public_random_deploy_dirname ?? '');
        file_put_contents("{$public_path}/index.log", "args_copied_correctly={$is_match}");
        return [
            'file' => basename(__FILE__),
            'result' => 'standalone-deploy-result',
        ];
    }
}
