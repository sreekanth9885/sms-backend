<?php

$env = parse_ini_file(__DIR__ . '/../.env');

foreach ($env as $key => $value) {
    $_ENV[$key] = $value;
}
