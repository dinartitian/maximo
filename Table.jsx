import { useState, useEffect } from "react";
import axios from "axios";
import * as XLSX from "xlsx";

const Table = () => {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [filters, setFilters] = useState({
    PONUM: "",
    PODESC: "",
    ORDERDATE: "",
    STATUS: "",
    RECEIPTS: "",
    POLINENUM: "",
    DESCRIPTION: "",
    ITEMNUM: "",
    LINETYPE: "",
    ORDERQTY: "",
    ORDERUNIT: "",
    REQUESTEDBY: "",
  });
  const [searchQuery, setSearchQuery] = useState("");

  // Ambil data dari database saat komponen pertama kali di-render
  useEffect(() => {
    fetchDataFromDatabase(); // Ambil data langsung begitu komponen dimuat
  }, []);

  // Fungsi untuk mengambil data dari database
  const fetchDataFromDatabase = () => {
    axios
      .get("http://localhost/maximo-real/api.php")
      .then((response) => {
        setData(response.data); // Update data di state
        setLoading(false);
      })
      .catch((error) => {
        setError(error);
        setLoading(false);
        console.error("Error fetching data from database:", error);
      });
  };

  // Fungsi untuk menangani perubahan nilai "REGISTERED" dan update ke database
  const handleRegisteredChange = (ponum, polinenum, value) => {
    if (value === 0) return; // Jangan izinkan perubahan "REGISTERED" kembali ke 0

    const updatedRow = { PONUM: ponum, POLINENUM: polinenum, REGISTERED: value };

    // Kirim update ke database
    axios
      .put("http://localhost/maximo-real/api.php", updatedRow)
      .then((response) => {
        console.log("Data updated:", response.data);
        fetchDataFromDatabase(); // Ambil data lagi setelah update
      })
      .catch((error) => {
        console.error("Error updating REGISTERED:", error);
      });

    // Update data di state lokal tanpa mereset filter
    setData((prevData) =>
      prevData.map((row) =>
        row.PONUM === ponum && row.POLINENUM === polinenum
          ? { ...row, REGISTERED: value }
          : row
      )
    );
  };

  // Filter data berdasarkan query pencarian dan filter kolom
  const filteredData = data.filter((row) => {
    const searchText = searchQuery.toLowerCase();
    const matchesSearchQuery = Object.keys(row).some((key) =>
      String(row[key]).toLowerCase().includes(searchText)
    );

    const matchesColumnFilters = Object.keys(filters).every((column) => {
      if (!filters[column]) return true;
      return String(row[column] || "")
        .toLowerCase()
        .includes(filters[column].toLowerCase());
    });

    return matchesSearchQuery && matchesColumnFilters;
  });

  // Fungsi untuk mengekspor data ke Excel
  const handleExportToExcel = () => {
    if (filteredData.length === 0) {
      alert("Tidak ada data untuk diekspor.");
      return;
    }

    const worksheet = XLSX.utils.json_to_sheet(filteredData);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "Data");
    XLSX.writeFile(workbook, "data-tabel.xlsx");
  };

  // Fungsi untuk menangani perubahan input pencarian per kolom
  const handleSearchChange = (e, column) => {
    setFilters({
      ...filters,
      [column]: e.target.value,
    });
  };

  // Menangani loading dan error
  if (loading) return <div className="text-center py-8">Loading...</div>;
  if (error) return <div className="text-center py-8 text-red-500">Error: {error.message}</div>;

  return (
    <div className="overflow-x-auto bg-blue-50 p-6 rounded-lg shadow-lg">
      {/* Input Pencarian */}
      <div className="mb-4 flex justify-between items-center">
        <input
          type="text"
          placeholder="Cari..."
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          className="px-6 py-3 border rounded-lg text-sm border-blue-300 focus:ring-2 focus:ring-blue-300 focus:outline-none w-1/3"
        />
        <button
          onClick={handleExportToExcel}
          className="px-6 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400"
        >
          Download Excel
        </button>
      </div>

      {/* Tabel Data */}
      <table className="min-w-full table-auto border-collapse bg-white shadow-sm rounded-lg">
        <thead className="bg-blue-100 text-blue-800">
          <tr>
            {[
              "PONUM", "PODESC", "ORDERDATE", "STATUS", "RECEIPTS", "POLINENUM",
              "DESCRIPTION", "ITEMNUM", "LINETYPE", "ORDERQTY", "ORDERUNIT", "REQUESTEDBY", "REGISTERED"
            ].map((header) => (
              <th key={header} className="px-6 py-3 border-b text-left"> {/* Rata kiri untuk header */}
                {header}
              </th>
            ))}
          </tr>
          <tr className="bg-blue-200">
            {Object.keys(filters).map(
              (column) =>
                column !== "REGISTERED" && (
                  <td key={column} className="px-6 py-3 text-left"> {/* Rata kiri untuk filter */}
                    <input
                      type="text"
                      value={filters[column]}
                      onChange={(e) => handleSearchChange(e, column)}
                      placeholder={`Cari ${column}`}
                      className="w-full px-4 py-2 border border-blue-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200"
                    />
                  </td>
                )
            )}
            <td className="px-6 py-3"></td>
          </tr>
        </thead>
        <tbody className="text-blue-800">
          {filteredData.map((row) => (
            <tr key={`${row.PONUM}-${row.POLINENUM}`} className="hover:bg-blue-50">
              {Object.keys(filters).map((key) =>
                key !== "REGISTERED" ? (
                  <td key={key} className="border px-6 py-3 text-left"> {/* Rata kiri untuk isi tabel */}
                    {row[key]}
                  </td>
                ) : null
              )}
              <td className="border px-6 py-3 text-center">
                <select
                  value={row.REGISTERED}
                  onChange={(e) => handleRegisteredChange(row.PONUM, row.POLINENUM, parseInt(e.target.value))}
                  className="border border-blue-300 rounded-lg px-2 py-1"
                  disabled={row.REGISTERED === 1} // Disable jika REGISTERED sudah 1
                >
                  <option value={1}>1</option>
                  <option value={0}>0</option>
                </select>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};

export default Table;
