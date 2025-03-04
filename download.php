<?php
$config = require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Akses ditolak!");
}

$fileUrl = $config['product']['download_url'];

echo json_encode(["download_url" => $fileUrl]);
exit;