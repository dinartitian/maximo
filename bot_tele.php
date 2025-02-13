<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Script dimulai...\n";

set_time_limit(0);  // Tidak ada batas waktu eksekusi

$apiToken = "7791071098:AAHqOCYFRqH9CTMJsBbWXdWgiR8rwh0M5e4";  // Token API Telegram
$chatId = "-4688094394";  // Chat ID grup atau user Telegram

// Koneksi ke database
$dbHost = "localhost";
$dbUsername = "root";
$dbPassword = "";
$dbName = "maximo";

$conn = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Database terhubung!\n";

// Query untuk mengambil data yang mungkin berubah
$sql = "SELECT ponum, polinenum, statusdate, status, registereddate, notif_sent, notif_hash FROM po_table";
$result = $conn->query($sql);

// Cek apakah query berhasil
if ($result === false) {
    die("Query failed: " . $conn->error);
}

echo "Query berhasil dijalankan! Ditemukan " . $result->num_rows . " data.\n";

$updates = []; // Array untuk menyimpan perubahan

while ($row = $result->fetch_assoc()) {
    $ponum = $row["ponum"];
    $polinenum = $row["polinenum"];
    $statusDate = $row["statusdate"] ?: "-"; // Jika kosong, beri tanda "-"
    $status = isset($row["status"]) ? $row["status"] : "N/A";
    $registeredDate = $row["registereddate"] ?: "-";
    $oldHash = $row["notif_hash"];
   

    // Buat hash baru
    $newHash = md5($ponum . $polinenum . $statusDate . $status . $registeredDate);

    if ($newHash !== $oldHash) {
        // Tambahkan perubahan ke dalam array
        $updates[] = " 📌 *PO:* $ponum\n*Polinum:* $polinenum\n*Status Date:* $statusDate\n*Status:* $status\n*Register Date:* $registeredDate\n---";


        // Update database agar tidak mengirim notifikasi berulang
        $updateSql = "UPDATE po_table SET notif_sent = 1, notif_hash = '$newHash' WHERE ponum = '$ponum' AND polinenum = '$polinenum'";
        $conn->query($updateSql);
    }
}

// **Kirim notifikasi hanya jika ada perubahan**
if (!empty($updates)) {
    echo "Mengirim satu notifikasi dengan ringkasan perubahan...\n";

    // Gabungkan semua perubahan dengan pemisah "\n\n"
    $message = "📢 uul *Update Purchase Orders:*\n\n" . implode("\n\n", $updates);
    
    // Kirim pesan ke Telegram dengan format Markdown
    $sendMessageUrl = "https://api.telegram.org/bot$apiToken/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];

    $options = [
        'http' => [
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
        ],
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($sendMessageUrl, false, $context);

    if ($response !== false) {
        echo "Notifikasi ringkasan terkirim!\n";
    } else {
        echo "Error sending summary message to Telegram.\n";
    }
} else {
    echo "Tidak ada perubahan yang perlu dikirim.\n";
}

file_put_contents("C:/xampp/htdocs/maximo-coba-tele/log.txt", date("Y-m-d H:i:s") . " - Script dijalankan\n", FILE_APPEND);

$conn->close();
echo "Script selesai!\n";

?>
