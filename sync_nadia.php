<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

// Koneksi ke database
$conn = new mysqli("localhost", "root", "", "maximo");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Koneksi gagal: " . $conn->connect_error]);
    exit;
}

// Ambil statusdate terbaru dari database
$sql = "SELECT MAX(statusdate) AS last_date FROM po_table";
$result = $conn->query($sql);

$last_date = '2024-01-01';

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if (!empty($row['last_date'])) {
        $last_date = date('Y-m-d', strtotime($row['last_date']));
    }
}

// Ambil data dari API
$apiUrl = "https://sim.tjbservices.com/plugins/po_asset_nr/api_proxy.php?date=" . urlencode($last_date);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || $response === false) {
    http_response_code(500);
    echo json_encode(["error" => "Gagal mengambil data dari API. HTTP Code: $httpCode"]);
    exit;
}

$data = json_decode($response, true);
if (!is_array($data)) {
    http_response_code(500);
    echo json_encode(["error" => "Data API tidak valid atau kosong."]);
    exit;
}

if (empty($data)) {
    echo json_encode(["message" => "Tidak ada data baru dari API sejak $last_date."]);
    exit;
}

// Nonaktifkan auto-commit untuk transaksi
$conn->autocommit(false);

$stmt = $conn->prepare("INSERT INTO po_table 
    (PONUM, POLINENUM, PODESC, ORDERDATE, STATUS, STATUSDATE, RECEIPTS, DESCRIPTION, ITEMNUM, LINETYPE, ORDERQTY, ORDERUNIT, REQUESTEDBY) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
    ON DUPLICATE KEY UPDATE 
        PODESC = VALUES(PODESC), ORDERDATE = VALUES(ORDERDATE), STATUS = VALUES(STATUS), STATUSDATE = VALUES(STATUSDATE), 
        RECEIPTS = VALUES(RECEIPTS), DESCRIPTION = VALUES(DESCRIPTION), ITEMNUM = VALUES(ITEMNUM), 
        LINETYPE = VALUES(LINETYPE), ORDERQTY = VALUES(ORDERQTY), ORDERUNIT = VALUES(ORDERUNIT), REQUESTEDBY = VALUES(REQUESTEDBY)");

if (!$stmt) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["error" => "Gagal menyiapkan statement: " . $conn->error]);
    exit;
}

$count = 0;
foreach ($data as $item) {
    if (!isset($item['PONUM'], $item['POLINENUM'], $item['STATUSDATE'])) {
        continue;
    }

    // Gunakan isset() untuk PHP 5.6
    $ponum = isset($item['PONUM']) ? $item['PONUM'] : "";
    $polinenum = isset($item['POLINENUM']) ? $item['POLINENUM'] : "";
    $poDesc = isset($item['PODESC']) ? $item['PODESC'] : "";
    $orderDate = isset($item['ORDERDATE']) ? $item['ORDERDATE'] : null;
    $status = isset($item['STATUS']) ? $item['STATUS'] : "";
    $statusDate = isset($item['STATUSDATE']) ? $item['STATUSDATE'] : null;
    $receipts = isset($item['RECEIPTS']) ? $item['RECEIPTS'] : "";
    $description = isset($item['DESCRIPTION']) ? $item['DESCRIPTION'] : "";
    $itemNum = isset($item['ITEMNUM']) ? $item['ITEMNUM'] : "";
    $lineType = isset($item['LINETYPE']) ? $item['LINETYPE'] : "";
    $orderQty = isset($item['ORDERQTY']) ? $item['ORDERQTY'] : 0;
    $orderUnit = isset($item['ORDERUNIT']) ? $item['ORDERUNIT'] : "";
    $requestedBy = isset($item['REQUESTEDBY']) ? $item['REQUESTEDBY'] : "";

    // Binding parameter
    error_log("ITEMNUM before insert: " . $itemNum);
    // Debug: Insert manual ke database tanpa prepared statement
    $query = "INSERT INTO po_table (ITEMNUM) VALUES ('$itemNum')";
    if (!$conn->query($query)) {
        error_log("Error manual insert: " . $conn->error);
    } else {
        error_log("Manual insert success: $itemNum");
    }


    $stmt->bind_param(
        "sssssssssssss",
        $ponum,
        $polinenum,
        $poDesc,
        $orderDate,
        $status,
        $statusDate,
        $receipts,
        $description,
        $itemNum,
        $lineType,
        $orderQty,
        $orderUnit,
        $requestedBy
    );

    if (!$stmt->execute()) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(["error" => "Gagal menyimpan data: " . $stmt->error]);
        exit;
    }

    $count++;
}

// Commit transaksi
$conn->commit();

// ** Respon ke frontend **
echo json_encode([
    "success" => true,
    "message" => "Data berhasil disinkronisasi",
    "rows_synced" => $count,
    "latest_status_date" => $last_date
]);



$stmt->close();
$conn->close();
