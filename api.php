<?php

// Set header agar responnya JSON dan mengizinkan akses dari mana saja
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET");
header("Access-Control-Allow-Headers: Content-Type");

// Buat koneksi ke database MySQL
$conn = new mysqli("localhost", "root", "", "maximo");

// Cek koneksi
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Koneksi gagal: " . $conn->connect_error]);
    exit;
}

// Handle request GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Query untuk mendapatkan tanggal terbaru dari STATUSDATE
    $dateQuery = "SELECT MAX(DATE(STATUSDATE)) AS latest_statusdate FROM po_table";
    $resultDate = $conn->query($dateQuery);

    if ($resultDate) {
        $rowDate = $resultDate->fetch_assoc();
        $latestStatusDate = $rowDate['latest_statusdate'];

        if (!$latestStatusDate) {
            echo json_encode(["message" => "Data tidak ditemukan."]);
            exit;
        }

        // Query untuk mengambil data berdasarkan tanggal terbaru
        $query = "SELECT * FROM po_table WHERE DATE(STATUSDATE) = ? ORDER BY STATUSDATE ASC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $latestStatusDate);
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        if (empty($data)) {
            echo json_encode(["message" => "Data tidak ditemukan untuk tanggal terbaru."]);
        } else {
            echo json_encode($data);
        }

        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Gagal mendapatkan tanggal terbaru."]);
    }
}

// Handle request PUT (Update status REGISTERED)
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $jsonInput = file_get_contents("php://input");
    $data = json_decode($jsonInput, true);

    if (isset($data['PONUM'], $data['POLINENUM'], $data['REGISTERED'])) {
        $ponum = $conn->real_escape_string($data['PONUM']);
        $polinenum = $conn->real_escape_string($data['POLINENUM']);
        $registered = (int) $data['REGISTERED'];

        // Validasi nilai REGISTERED
        if ($registered !== 0 && $registered !== 1) {
            http_response_code(400);
            echo json_encode(["error" => "Nilai REGISTERED salah. Harus 0 atau 1."]);
            exit;
        }

        // Cek status REGISTERED saat ini
        $checkQuery = "SELECT REGISTERED FROM po_table WHERE PONUM = ? AND POLINENUM = ?";
        $stmtCheck = $conn->prepare($checkQuery);
        $stmtCheck->bind_param("ss", $ponum, $polinenum);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        $rowCheck = $resultCheck->fetch_assoc();

        if (!$rowCheck) {
            http_response_code(404);
            echo json_encode(["error" => "Data tidak ditemukan."]);
            exit;
        }

        // Jika status REGISTERED saat ini berbeda, lakukan update
        if ((int) $rowCheck['REGISTERED'] !== $registered) {
            if ($registered === 1) {
                // Jika mengubah ke 'Yes' (1), set REGISTEREDDATE ke NOW
                $query = "UPDATE po_table SET REGISTERED = ?, REGISTEREDDATE = NOW() WHERE PONUM = ? AND POLINENUM = ?";
            } else {
                // Jika mengubah ke 'No' (0), set REGISTEREDDATE ke NULL
                $query = "UPDATE po_table SET REGISTERED = ?, REGISTEREDDATE = NULL WHERE PONUM = ? AND POLINENUM = ?";
            }

            // Prepare query untuk update
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iss", $registered, $ponum, $polinenum);

            if ($stmt->execute()) {
                echo json_encode(["message" => "Update berhasil"]);
            } else {
                http_response_code(500);
                echo json_encode(["error" => "Update gagal. Coba lagi nanti."]);
            }

            $stmt->close();
        } else {
            echo json_encode(["message" => "Tidak ada perubahan yang dilakukan."]);
        }

        $stmtCheck->close();
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Data yang diperlukan (PONUM, POLINENUM, REGISTERED) belum lengkap."]);
    }
}

// Tutup koneksi
$conn->close();
