<?php

require __DIR__.'/../vendor/autoload.php';

$root = $argv[1] ?? throw new InvalidArgumentException('Provide an isolated fixture directory.');
if (($argv[2] ?? '') === '--verify') {
    $tags = \theaddresstechnology\DDD\Modules\ViteTags::render($root.'/public/modules', 'blog', 'resources/assets/js/app.js');
    if (!str_contains($tags, '<script') || !str_contains($tags, '<link rel="stylesheet"')) {
        throw new RuntimeException('Built module JS/CSS did not resolve from the generated manifest.');
    }
    echo "Built module JavaScript and CSS resolve correctly.\n";
} else {
    (new \theaddresstechnology\DDD\Modules\Scaffolder($root.'/src/Domain'))->make('Blog', false, false, false);
}
