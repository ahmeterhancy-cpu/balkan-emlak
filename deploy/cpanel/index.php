<?php

/*
 * public_html/index.php — cPanel yerleşimi için giriş dosyası.
 *
 * Laravel'in tamamı public_html/balkan_app/ altında, web kökünde yalnız
 * public/ içeriği ve bu dosya var. balkan_app/.htaccess klasörü web'e
 * tamamen kapatır (deploy her seferinde yeniden koyar); o dosya olmazsa
 * .env dışarıdan okunabilir — kurulumdan sonra doğrulayın (DEPLOY.md).
 *
 * public_path() bu klasörü gösterir: deploy, yolunu balkan_app/.public_yolu
 * dosyasına yazar ve bootstrap/app.php onu okur.
 *
 * Laravel'in kendi public/index.php'si olduğu gibi duruyor; yerel
 * geliştirme onu kullanır.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$app_base = __DIR__.'/balkan_app';

// Bakım kipi (php artisan down)
if (file_exists($maintenance = $app_base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $app_base.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $app_base.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
