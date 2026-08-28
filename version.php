<?php
defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026072301;        // Fecha YYYYMMDDXX
$plugin->requires  = 2022041900;        // Moodle 4.x
$plugin->component = 'theme_saec';      // Nombre del componente
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';
$plugin->dependencies = [
    'theme_boost' => 2022041900,
];