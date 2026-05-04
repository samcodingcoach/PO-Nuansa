<?php
/**
 * File: bootstrap.php
 */

// Zona Waktu Wajib (Sangat krusial untuk X-Api-Timestamp Accurate)
date_default_timezone_set("Asia/Makassar");

// Autoload / Require Config dan Classes
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/utils.php';
require_once __DIR__ . '/classes/AccurateAPI.php';

?>