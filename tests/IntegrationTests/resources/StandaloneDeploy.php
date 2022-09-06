<?php

class Deploy {
    public function install($public_path) {
        $private_path = __DIR__;
        copy("{$private_path}/test.txt", "{$public_path}/index.txt");
    }
}
