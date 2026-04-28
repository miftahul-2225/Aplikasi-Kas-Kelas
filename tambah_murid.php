<?php
include 'config/koneksi.php';
if (isset($_POST['simpan'])) {
    $nisn = $_POST['nisn'];
    $nama = $_POST['nama'];
    $kelas = $_POST['kelas'];
    $status = $_POST['status'];
    $alamat = $_POST['alamat'];

    $query = "INSERT INTO tb_siswa (nisn, nama, kelas, status, alamat) VALUES ('$nisn', '$nama', '$kelas', '$status', '$alamat')";
    mysqli_query($koneksi_db, $query);

    header("Location: datamurid.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Tambah Siswa</title>
     <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
</head>
<body>
    <div class="container mt-5 d-flex justify-content-center align-items-center">
        <div class="card p-4 shadow" style="max-width: 630px; width: 100%;">
            <h2 class="mb-4 text-center">Form Tambah Siswa</h2>
            <form action="" method="POST">
                <div class="mb-3">
                    <label for="nisn" class="form-label">NISN</label>
                    <input type="number" class="form-control" id="nisn" name="nisn" required>
                </div>
                <div class="mb-3">
                    <label for="nama" class="form-label">Nama Siswa</label>
                    <input type="text" class="form-control" id="nama" name="nama" required>
                </div>
                <div class="mb-3">
                    <label for="kelas" class="form-label">Kelas</label>
                    <input type="text" class="form-control" id="kelas" name="kelas" required>
                </div>
                <div class="mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>                    
                </div>
                <div class="mb-3">
                    <label for="alamat" class="form-label">Alamat</label>
                    <textarea class="form-control" id="alamat" name="alamat" rows="3" required></textarea>
                </div>
                <div>
                <input type="submit" value="Simpan" name="simpan" class="btn btn-primary mb-3">
                <input type="button" value="Kembali" class="btn btn-secondary mb-3" onclick="window.location.href='datamurid.php'">
                </div>
            </form>
        </div>
    </div>
</body>
</html>