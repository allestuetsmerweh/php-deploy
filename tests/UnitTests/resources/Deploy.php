<?php

class Deploy {
    public function install($public_path) {
        file_put_contents(__DIR__.'/installed_to.txt', $public_path);
    }
}
