<?php
// Koneksi database
$conn = new mysqli("localhost", "root", "", "maximo");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ambil data dari database
$query = "SELECT * FROM po_table";
$result = $conn->query($query);
$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

// Fungsi untuk mengurutkan berdasarkan REGISTERED (yang "Tidak" di atas) dan ORDERDATE (terbaru di atas)
usort($data, function ($a, $b) {
    if ($a['REGISTERED'] == 0 && $b['REGISTERED'] != 0) {
        return -1;
    } elseif ($a['REGISTERED'] != 0 && $b['REGISTERED'] == 0) {
        return 1;
    }

    $dateA = strtotime($a['ORDERDATE']);
    $dateB = strtotime($b['ORDERDATE']);

    return $dateB - $dateA;
});

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maximo PO</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold text-blue-800 mb-6 text-center">Maximo PO from GL Asset</h1>

        <!-- Pencarian Global -->
<div class="mb-4 flex justify-between items-center">
    <input type="text" id="searchInput" placeholder="Cari..." class="px-4 py-2 border rounded-lg text-sm border-blue-300 w-1/3">
    <div class="flex gap-2"> <!-- Tambahkan div dengan flex dan gap -->
        <button onclick="exportToExcel()" class="px-4 py-2 bg-blue-500 text-white rounded-lg">Download Excel</button>
        <button onclick="toggleChart()" class="px-4 py-2 bg-green-500 text-white rounded-lg">Tampilkan Chart</button>
    </div>
</div>

        <!-- Modal Chart -->
        <div id="chartModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex justify-center items-center z-50">
            <div class="bg-white p-4 rounded-lg w-96">
                <h2 class="text-xl font-bold text-center mb-4">Statistik Registered</h2>
                <canvas id="myChart" width="300" height="300"></canvas>
                <button onclick="closeChart()" class="mt-4 px-4 py-2 bg-red-500 text-white rounded-lg w-full">Tutup</button>
            </div>
        </div>

        <!-- Tabel Data -->
        <table class="min-w-full table-auto bg-white shadow-sm rounded-lg" id="dataTable">
            <thead class="bg-blue-100 text-blue-800">
                <tr>
                    <?php
                    $columns = ['PONUM', 'PODESC', 'ORDERDATE', 'STATUS', 'STATUSDATE', 'RECEIPTS', 'POLINENUM', 'DESCRIPTION', 'ITEMNUM', 'LINETYPE', 'ORDERQTY', 'ORDERUNIT', 'REQUESTEDBY', 'REGISTERED', 'REGISTEREDDATE'];

                    foreach ($columns as $index => $column) : ?>
                        <th class="px-1 py-1 border-b text-center text-xs">
                            <?php echo $column; ?>
                            <?php if ($column !== 'REGISTERED') : ?>
                                <input type="text" class="column-filter w-full px-1 py-1 border rounded text-xs" data-index="<?php echo $index; ?>" placeholder="Cari...">
                            <?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="text-blue-800">
                <?php foreach ($data as $row) : ?>
                    <tr class="hover:bg-blue-50">
                        <?php foreach ($columns as $column) : ?>
                            <td class="border px-1 py-1 text-left text-xs <?php echo ($column === 'REGISTERED') ? 'text-center' : ''; ?>">
                                <?php
                                if ($column === 'ITEMNUM' && $row[$column] == '0') {
                                    echo '';
                                } elseif ($column === 'REGISTERED') {
                                    if ($row['REGISTERED'] == 1) {
                                        echo '<span class="text-green-600 font-semibold">Yes</span>';
                                    } else {
                                        echo '<select id="registerSelect-' . $row['PONUM'] . '-' . $row['POLINENUM'] . '" 
                                                onchange="updateRegistered(\'' . $row['PONUM'] . '\', \'' . $row['POLINENUM'] . '\', this.value, ' . $row['REGISTERED'] . ')" 
                                                class="border border-blue-300 rounded-lg px-1 py-1">
                                                <option value="0" ' . ($row['REGISTERED'] == 0 ? 'selected' : '') . '>No</option>
                                                <option value="1">Yes</option>
                                            </select>';
                                    }
                                } else {
                                    echo isset($row[$column]) ? htmlspecialchars($row[$column]) : '';
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script src="script.js"></script>
</body>
</html>
