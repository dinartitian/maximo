<?php

// Koneksi database
$conn = new mysqli("localhost", "root", "", "maximo");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Query untuk menghitung jumlah Registered dan Not Registered
$query = "SELECT REGISTERED, COUNT(*) as count FROM po_table GROUP BY REGISTERED";
$result = $conn->query($query);

$data = [
    'registered' => 0,
    'not_registered' => 0
];

// Ambil data dari query
while ($row = $result->fetch_assoc()) {
    if ($row['REGISTERED'] == 1) {
        $data['registered'] = $row['count'];
    } else {
        $data['not_registered'] = $row['count'];
    }
}

$conn->close();

// Mengembalikan data dalam format JSON
echo json_encode($data);
