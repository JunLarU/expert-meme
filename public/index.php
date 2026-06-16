<?php
date_default_timezone_set('America/Mexico_City');
require_once dirname(__DIR__) . '/vendor/autoload.php';

// if (!function_exists('csrf_field')) {
//     exit('csrf_field NO está cargado');
// }
Whis\App::bootstrap(dirname(__DIR__))->run();
