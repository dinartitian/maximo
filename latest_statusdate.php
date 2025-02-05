<?php

// Koneksi database
$conn = new mysqli("localhost", "root", "", "maximo");

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Ambil STATUSDATE terbaru dan format ke Y-m-d
$query = "SELECT DATE(STATUSDATE) as status_date FROM po_table ORDER BY STATUSDATE DESC LIMIT 1";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $latestDate = $row['status_date'];
} else {
    $latestDate = date('Y-m-d'); // Default ke hari ini jika kosong
}

$conn->close();

// Redirect ke URL dengan format yang diinginkan
header("Location: http://localhost/maximo-coba/$latestDate");
exit;
