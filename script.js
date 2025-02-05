document.addEventListener("DOMContentLoaded", function () {
    initializeFilters();
    setInterval(checkForUpdates, 5000);
});

function initializeFilters() {
    document.getElementById("searchInput").addEventListener("keyup", function() {
        filterGlobal(this.value);
    });

    const columnFilters = document.querySelectorAll(".column-filter");
    columnFilters.forEach(filter => {
        filter.addEventListener("keyup", function() {
            filterColumn(this.getAttribute("data-index"), this.value);
        });
    });
}

function filterGlobal(searchValue) {
    const filter = searchValue.toUpperCase();
    const table = document.getElementById("dataTable");
    const rows = table.getElementsByTagName("tr");

    for (let i = 1; i < rows.length; i++) {
        const cells = rows[i].getElementsByTagName("td");
        let rowVisible = false;

        for (let j = 0; j < cells.length; j++) {
            const cell = cells[j];
            if (cell) {
                const textValue = cell.textContent || cell.innerText;
                if (textValue.toUpperCase().indexOf(filter) > -1) {
                    rowVisible = true;
                    break;
                }
            }
        }
        
        rows[i].style.display = rowVisible ? "" : "none";
    }
}

function filterColumn(columnIndex, searchValue) {
    const filter = searchValue.toUpperCase();
    const table = document.getElementById("dataTable");
    const rows = table.getElementsByTagName("tr");

    for (let i = 1; i < rows.length; i++) {
        const cell = rows[i].getElementsByTagName("td")[columnIndex];
        if (cell) {
            let textValue = cell.textContent || cell.innerText;

            if (textValue.toUpperCase().indexOf(filter) > -1) {
                rows[i].style.display = "";
            } else {
                rows[i].style.display = "none";
            }
        }
    }
}

function checkForUpdates() {
    fetch("check_updates.php")
        .then(response => response.json())
        .then(result => {
            if (result.updated) {
                syncData();
            }
        })
        .catch(error => console.error("Error checking for updates:", error));
}

function syncData() {
    fetch("http://localhost/maximo-coba/sync.php")
        .then(response => response.json())
        .then(response => {
            if (response.success) {
                console.log("Data berhasil disinkronkan.");
            } else {
                console.error("Gagal melakukan sinkronisasi.");
            }
        })
        .catch(error => console.error("Error syncing data:", error));
}

function updateRegistered(ponum, polinenum, value, currentStatus) {
    if (currentStatus === '1') {
        alert("Status sudah 'Yes' dan tidak bisa diubah.");
        return;
    }

    if (value === '1' && !confirm("Yakin ingin mengubah status menjadi 'Yes'? Perubahan ini permanen.")) {
        document.getElementById(`registerSelect-${ponum}-${polinenum}`).value = currentStatus;
        return;
    }

    fetch("http://localhost/maximo-coba/api.php", {
        method: "PUT",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({ 
            PONUM: ponum, 
            POLINENUM: polinenum, 
            REGISTERED: value 
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.message === "Update berhasil") {
            alert("Status berhasil diubah.");
            location.reload();
        } else {
            alert("Gagal mengubah status.");
        }
    })
    .catch(error => {
        alert("Kesalahan saat mengubah status.");
        console.error("Error:", error);
    });
}

function exportToExcel() {
    let table = document.getElementById("dataTable");
    let wb = XLSX.utils.book_new();
    let ws = XLSX.utils.table_to_sheet(table);

    let range = XLSX.utils.decode_range(ws["!ref"]);
    let registeredColIndex = null;

    for (let C = range.s.c; C <= range.e.c; C++) {
        let cellAddress = XLSX.utils.encode_cell({ r: range.s.r, c: C });
        if (ws[cellAddress] && ws[cellAddress].v === "REGISTERED") {
            registeredColIndex = C;
            break;
        }
    }

    if (registeredColIndex !== null) {
        for (let R = range.s.r + 1; R <= range.e.r; R++) {
            let cellAddress = XLSX.utils.encode_cell({ r: R, c: registeredColIndex });
            if (ws[cellAddress]) {
                let cellValue = ws[cellAddress].v;
                if (cellValue === "No") {
                    ws[cellAddress].v = "";
                }
            }
        }
    }

    XLSX.utils.book_append_sheet(wb, ws, "Maximo_PO");
    XLSX.writeFile(wb, "Maximo_PO.xlsx");
}

function createChart(ctx) {
    // Ambil data dari backend (chart_data.php)
    fetch('chart_data.php')
        .then(response => response.json())
        .then(data => {
            const registeredCount = data.registered;
            const notRegisteredCount = data.not_registered;

            // Membuat chart
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['Registered', 'Not Registered'],
                    datasets: [{
                        data: [registeredCount, notRegisteredCount],
                        backgroundColor: ['#4CAF50', '#FF6347'],
                        borderColor: ['#fff', '#fff'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += context.parsed + ' (' + 
                                             (context.parsed / (registeredCount + notRegisteredCount) * 100).toFixed(2) + '%)';
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error fetching chart data:', error);
        });
}

function toggleChart() {
    const chartModal = document.getElementById('chartModal');
    chartModal.classList.toggle('hidden');
    if (!chartModal.classList.contains('hidden')) {
        const ctx = document.getElementById('myChart').getContext('2d');
        createChart(ctx);
    }
}

function closeChart() {
    const chartModal = document.getElementById('chartModal');
    chartModal.classList.add('hidden');
}

// Menambahkan event listener untuk tombol tutup
document.getElementById('closeChartButton').addEventListener('click', closeChart);
