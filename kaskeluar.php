<?php
require_once 'config/koneksi.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'bendahara') {
    header("Location: login.php");
    exit();
}

// ambil data kas keluar
$data = mysqli_query($koneksi_db, "
    SELECT t.*, s.nama_siswa 
    FROM tb_transaksi t
    LEFT JOIN tb_siswa s ON t.id_siswa = s.id_siswa
    WHERE t.jenis = 'pengeluaran'
    ORDER BY t.tanggal DESC
");

// total kas keluar
$totalKas = mysqli_fetch_assoc(mysqli_query($koneksi_db, "
    SELECT SUM(jumlah) as total FROM tb_transaksi WHERE jenis='pengeluaran'
"))['total'] ?? 0;

// jumlah transaksi
$totalTransaksi = mysqli_num_rows($data);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kas Kelas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
    .modal-content {
    border-radius: 16px;
    }

    .modal-header {
        padding-bottom: 0;
    }

    .modal-body input,
    .modal-body select,
    .modal-body textarea {
        border-radius: 10px;
    }
    </style>
</head>
<body class="bg-light">

<div class="d-flex">
    <!-- SIDEBAR -->
    <aside class="bg-white border-end p-3 d-flex flex-column position-sticky top-0 vh-100" style="width:230px;">
        <!-- HEADER -->
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center"
                 style="width:44px; height:44px;">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <h5 class="text-primary mb-0">E Kas Seven</h5>
        </div>

        <!-- MENU -->
        <ul class="nav nav-pills flex-column gap-2 mb-auto">

            <li class="nav-item">
                <a href="dashboard.php" class="nav-link text-dark d-flex align-items-center gap-3 fs-5">
                    <i class="fa-solid fa-house"></i>
                    Dashboard
                </a>
            </li>

            <li class="nav-item">
                <a href="datamurid.php" class="nav-link text-dark d-flex align-items-center gap-3 fs-5">
                    <i class="fa-solid fa-users"></i>
                    Data Murid
                </a>
            </li>

            <li class="nav-item">
                <a href="kasmasuk.php" class="nav-link text-dark d-flex align-items-center gap-3 fs-5">
                    <i class="fa-solid fa-arrow-trend-up"></i>
                    Kas Masuk
                </a>
            </li>

            <li class="nav-item">
                <a href="kaskeluar.php" class="nav-link active d-flex align-items-center gap-3 fs-5">
                    <i class="fa-solid fa-arrow-trend-down"></i>
                    Kas Keluar
                </a>
            </li>

            <li class="nav-item">
                <a href="status.php" class="nav-link text-dark d-flex align-items-center gap-3 fs-5">
                    <i class="fa-regular fa-circle-check"></i>
                    Status Pembayaran
                </a>
            </li>

            <li class="nav-item">
                <a href="arus.php" class="nav-link text-dark d-flex align-items-center gap-3 fs-5">
                    <i class="fa-solid fa-chart-column"></i>
                    Arus Kas
                </a>
            </li>

            <li class="nav-item">
                <a href="laporan.php" class="nav-link text-dark d-flex align-items-center gap-3 fs-5">
                    <i class="fa-regular fa-file-lines"></i>
                    Laporan
                </a>
            </li>
        </ul>

        <!-- FOOTER BUTTON -->
        <div class="d-grid gap-3 mt-4">
            <button class="btn btn-primary rounded-3"
            data-bs-toggle="modal"
            data-bs-target="#modalTransaksi">
            <i class="fa-solid fa-plus me-2"></i>
            Tambah Transaksi
            </button>

            <a href="logout.php" 
            onclick="return confirm('Yakin ingin logout?')" 
            class="btn btn-danger rounded-3">
            <i class="fa-solid fa-right-from-bracket me-2"></i>
            Keluar
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-fill p-4">

        <!-- HEADER -->
        <div class="mb-4">
            <h2 class="fw-semibold">Kas Keluar</h2>
            <p class="text-muted mb-0">Riwayat pengeluaran kas kelas</p>
        </div>

        <!-- TOTAL KAS -->
        <div class="card border-danger bg-danger-subtle shadow-sm mb-4 rounded-4">
        <div class="card-body d-flex justify-content-between align-items-center">

        <div>
            <h2 class="text-danger fw-semibold">
            Rp <?= number_format($totalKas,0,',','.') ?>
            </h2>
            <small class="text-muted">
            <?= $totalTransaksi ?> transaksi pemasukan
            </small>
        </div>

        <div class="bg-danger text-white d-flex align-items-center justify-content-center rounded-4"
             style="width:80px; height:80px;">
            <i class="fa-solid fa-arrow-up fs-3"></i>
        </div>

    </div>
</div>

<!-- RIWAYAT -->
<div class="card shadow-sm rounded-4">
    <div class="card-body p-4">
        <h5 class="fw-semibold mb-4">Riwayat Kas Keluar</h5>

        <?php while($row = mysqli_fetch_assoc($data)) : ?>
            <div class="card border-0 shadow-sm mb-3 rounded-4">
                <div class="card-body d-flex justify-content-between align-items-center">

                    <div class="d-flex align-items-center gap-3">

                        <!-- ICON -->
                        <div class="bg-danger-subtle text-danger rounded-3 d-flex align-items-center justify-content-center"
                             style="width:48px; height:48px;">
                            <i class="fa-solid fa-arrow-down"></i>
                        </div>

                        <!-- INFO -->
                        <div>
                            <h6 class="mb-1">
                                Pengeluaran Kas
                            </h6>

                            <span class="badge bg-danger-subtle text-danger">
                                <?= $row['keterangan'] ?? 'Pengeluaran' ?>
                            </span>

                            <small class="text-muted ms-2">
                                <?= date('d M Y', strtotime($row['tanggal'])) ?>
                            </small>
                        </div>

                    </div>

                    <!-- NOMINAL -->
                    <div class="text-danger fw-semibold">
                        -Rp <?= number_format($row['jumlah'],0,',','.') ?>
                    </div>

                </div>
            </div>
        <?php endwhile; ?>

    </div>
</div>

</main>
</div>

<div class="modal fade" id="modalTransaksi" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4">

      <!-- HEADER -->
      <div class="modal-header border-0">
        <h5 class="modal-title fw-semibold">Tambah Transaksi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <!-- BODY -->
      
        <div class="modal-body">

    <!-- JENIS -->
    <div class="mb-3">
        <label>Jenis</label>
        <select name="jenis" class="form-control" required>
            <option value="">-- Pilih --</option>
            <option value="bayar">Pemasukan</option>
            <option value="pengeluaran">Pengeluaran</option>
        </select>
    </div>

    <!-- KETERANGAN -->
    <div class="mb-3">
        <label>Keterangan</label>
        <textarea name="keterangan" class="form-control" required></textarea>
    </div>

    <!-- JUMLAH -->
    <div class="mb-3">
        <label>Jumlah</label>
        <input type="number" name="jumlah" class="form-control" required>
    </div>

    <!-- TANGGAL -->
    <div class="mb-3">
        <label>Tanggal</label>
        <input type="date" name="tanggal" class="form-control" required>
    </div>

    <button type="submit" name="simpan" class="btn btn-primary">
        Simpan
    </button>
    </form>
    </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
