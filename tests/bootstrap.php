<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Load environment variables
if (method_exists(Dotenv::class, 'bootEnv')) {
    $envFile = $_SERVER['APP_ENV'] ?? 'dev';
    
    // For tests, load .env.test
    if ($envFile === 'test') {
        (new Dotenv())->bootEnv(dirname(__DIR__).'/.env.test');
    } else {
        (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
    }
}

if ($_SERVER['APP_DEBUG'] ?? false) {
    umask(0000);
}
