<?php

/*
 |  PHP'nin yerlesik sunucusu icin yonlendirici.
 |  Gercek dosya varsa onu sun, yoksa istegi Laravel'e devret.
 |  Kullanim: php -S 127.0.0.1:8144 -t public server.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

require_once __DIR__.'/public/index.php';
