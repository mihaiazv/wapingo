<?php
spl_autoload_register(function ($class) {
    $prefix = 'Addons\\KeywordRouting\\';
    if (strpos($class, $prefix) !== 0) { return; }
    $path = __DIR__.'/../'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    if (is_file($path)) { require $path; }
});