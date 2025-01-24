import Table from "./components/Table"; 
import Button from "./components/Button"; 
import './index.css';
import './App.css';

const App = () => {
  // menangani aksi tombol
  const handleAddNewPO = () => {
    console.log("Add New PO clicked");
    //menambahkan logika untuk membuka form atau halaman baru untuk menambah PO baru
  };

  return (
    <div className="container mx-auto p-4">
      <header className="text-center mb-4">
        <h1 className="text-3xl font-semibold text-blue-600">Maximo PO</h1>
      </header>
      
      {/* Tombol untuk menambahkan PO baru */}
      <Button text="Add New PO" onClick={handleAddNewPO} />

      {/* Tabel untuk menampilkan data PO */}
      <Table />
    </div>
  );
};

export default App;
