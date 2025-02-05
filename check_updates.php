<?php

header('Content-Type: application/json');

// Logika untuk memeriksa apakah ada pembaruan pada data
// Misalnya, kita cek berdasarkan timestamp atau data terakhir diperbarui
// Ganti dengan logika yang sesuai dengan database atau file yang digunakan

$lastUpdatedTimestamp = filemtime('api.json');  // Contoh jika menggunakan file api.json

// Contoh logika untuk cek pembaruan
$lastChecked = isset($_GET['lastChecked']) ? $_GET['lastChecked'] : 0;

$response = [
    "updated" => $lastUpdatedTimestamp > $lastChecked
];

echo json_encode($response);
