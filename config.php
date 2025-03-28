<?php

$env = parse_ini_file(__DIR__ . '/.env');


define('ENCRYPTION_KEY', $env['ENCRYPTION_KEY']);
?>

