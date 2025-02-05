<?php

// Menampilkan error untuk debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

class JsonSync
{
    private $jsonFile = 'api.json';
    private $conn;

    public function __construct()
    {
        // Koneksi ke database
        $this->conn = new mysqli("localhost", "root", "", "maximo");

        if ($this->conn->connect_error) {
            $this->sendErrorResponse("Koneksi gagal: " . $this->conn->connect_error);
        }
    }

    public function syncToDatabase()
    {
        // Cek apakah file JSON ada dan dapat dibaca
        if (!file_exists($this->jsonFile) || !is_readable($this->jsonFile)) {
            $this->sendErrorResponse("File JSON tidak ditemukan atau tidak dapat dibaca");
        }

        // Ambil data dari file JSON
        $jsonContent = file_get_contents($this->jsonFile);
        $data = json_decode($jsonContent, true);

        // Validasi parsing JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendErrorResponse("Error parsing JSON: " . json_last_error_msg());
        }

        // Mulai transaksi
        $this->conn->autocommit(false);

        // Persiapkan statement SQL untuk INSERT/UPDATE
        $stmt = $this->conn->prepare("
            INSERT INTO po_table 
            (PONUM, PODESC, ORDERDATE, STATUS, STATUSDATE, RECEIPTS, POLINENUM, 
            DESCRIPTION, ITEMNUM, LINETYPE, ORDERQTY, ORDERUNIT, REQUESTEDBY) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            PODESC = VALUES(PODESC),
            ORDERDATE = VALUES(ORDERDATE),
            STATUS = VALUES(STATUS),
            STATUSDATE = VALUES(STATUSDATE),
            RECEIPTS = VALUES(RECEIPTS),
            DESCRIPTION = VALUES(DESCRIPTION),
            ITEMNUM = VALUES(ITEMNUM),
            LINETYPE = VALUES(LINETYPE),
            ORDERQTY = VALUES(ORDERQTY),
            ORDERUNIT = VALUES(ORDERUNIT),
            REQUESTEDBY = VALUES(REQUESTEDBY)
        ");

        // Periksa apakah statement berhasil disiapkan
        if (!$stmt) {
            $this->conn->rollback();
            $this->sendErrorResponse("Error saat mempersiapkan statement: " . $this->conn->error);
        }

        $count = 0;
        foreach ($data as $row) {
            // Validasi: PONUM dan POLINENUM wajib ada
            if (empty($row['PONUM']) || empty($row['POLINENUM'])) {
                $this->conn->rollback();
                $this->sendErrorResponse("Data tidak lengkap: PONUM atau POLINENUM hilang pada baris " . json_encode($row));
            }

            // Bind data dari row JSON ke variabel
            $ponum = $row['PONUM'];
            $polinenum = $row['POLINENUM'];
            $poDesc = isset($row['PODESC']) ? $row['PODESC'] : "";
            $orderDate = isset($row['ORDERDATE']) && !empty($row['ORDERDATE']) ? $row['ORDERDATE'] : null;
            $status = isset($row['STATUS']) ? $row['STATUS'] : "";
            $statusDate = isset($row['STATUSDATE']) && !empty($row['STATUSDATE']) ? $row['STATUSDATE'] : null;
            $receipts = isset($row['RECEIPTS']) ? $row['RECEIPTS'] : "";
            $description = isset($row['DESCRIPTION']) ? $row['DESCRIPTION'] : "";
            $itemNum = isset($row['ITEMNUM']) ? $row['ITEMNUM'] : "";
            $lineType = isset($row['LINETYPE']) ? $row['LINETYPE'] : "";
            $orderQty = isset($row['ORDERQTY']) ? $row['ORDERQTY'] : 0;
            $orderUnit = isset($row['ORDERUNIT']) ? $row['ORDERUNIT'] : "";
            $requestedBy = isset($row['REQUESTEDBY']) ? $row['REQUESTEDBY'] : "";

            // Jika nilai datetime kosong, gunakan NULL
            if ($orderDate === "") {
                $orderDate = null;
            }
            if ($statusDate === "") {
                $statusDate = null;
            }

            // Bind parameter menggunakan variabel
            $stmt->bind_param(
                "ssssssssissss",
                $ponum,
                $poDesc,
                $orderDate,
                $status,
                $statusDate,
                $receipts,
                $polinenum,
                $description,
                $itemNum,
                $lineType,
                $orderQty,
                $orderUnit,
                $requestedBy
            );

            // Eksekusi query, jika gagal rollback
            if (!$stmt->execute()) {
                $this->conn->rollback();
                $this->sendErrorResponse("Error saat eksekusi query: " . $stmt->error);
            }

            $count++;
        }

        // Commit transaksi jika sukses
        $this->conn->commit();

        // Response sukses
        echo json_encode([
            "success" => true,
            "message" => "Data berhasil disinkronkan",
            "rows_synced" => $count
        ]);
    }

    private function sendErrorResponse($message)
    {
        echo json_encode(["error" => $message]);
        exit;
    }

    public function __destruct()
    {
        $this->conn->close();
    }
}

// Memulai sinkronisasi data
$sync = new JsonSync();
$sync->syncToDatabase();
