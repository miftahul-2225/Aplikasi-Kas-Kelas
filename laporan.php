<?php
require_once 'config/koneksi.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'bendahara') {
    header("Location: login.php");
    exit();
}

// filter bulanan
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

// total pemasukan
$q_masuk = mysqli_query($koneksi_db, "
    SELECT COALESCE(SUM(jumlah),0) as total 
    FROM tb_transaksi 
    WHERE jenis='bayar'
    AND MONTH(tanggal)='$bulan'
    AND YEAR(tanggal)='$tahun'
");
$total_masuk = mysqli_fetch_assoc($q_masuk)['total'];

// total pengeluaran
$q_keluar = mysqli_query($koneksi_db, "
    SELECT COALESCE(SUM(jumlah),0) as total 
    FROM tb_transaksi 
    WHERE jenis='keluar'
    AND MONTH(tanggal)='$bulan'
    AND YEAR(tanggal)='$tahun'
");
$total_keluar = mysqli_fetch_assoc($q_keluar)['total'];

// saldo
$saldo = $total_masuk - $total_keluar;

// jumlah siswa
$q_siswa = mysqli_query($koneksi_db, "SELECT COUNT(*) as total FROM tb_siswa");
$total_siswa = mysqli_fetch_assoc($q_siswa)['total'];

// target bulanan
$target_per_siswa = 10000;
$total_target = $total_siswa * $target_per_siswa;

// statys pembayaran siswa
$q_status = mysqli_query($koneksi_db, "
    SELECT 
        s.id_siswa,
        COALESCE(SUM(tr.jumlah),0) as bayar
    FROM tb_siswa s
    LEFT JOIN tb_transaksi tr 
        ON s.id_siswa = tr.id_siswa 
        AND tr.jenis='bayar'
        AND MONTH(tr.tanggal)='$bulan'
        AND YEAR(tr.tanggal)='$tahun'
    GROUP BY s.id_siswa
");

$lunas = 0;
$sebagian = 0;
$belum = 0;

while($row = mysqli_fetch_assoc($q_status)){
    if($row['bayar'] >= $target_per_siswa){
        $lunas++;
    } elseif($row['bayar'] > 0){
        $sebagian++;
    } else {
        $belum++;
    }
}

// total transaksi 
$q_total_tr = mysqli_query($koneksi_db, "
    SELECT COUNT(*) as total FROM tb_transaksi
    WHERE MONTH(tanggal)='$bulan'
    AND YEAR(tanggal)='$tahun'
");
$total_transaksi = mysqli_fetch_assoc($q_total_tr)['total'];

// jumlah kategori transaksi (bayar, keluar)
$q_kategori = mysqli_query($koneksi_db, "
    SELECT COUNT(DISTINCT keterangan) as total 
    FROM tb_transaksi
");
$total_kategori = mysqli_fetch_assoc($q_kategori)['total'];
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
                <a href="kaskeluar.php" class="nav-link text-dark d-flex align-items-center gap-3 fs-5">
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
                <a href="laporan.php" class="nav-link active d-flex align-items-center gap-3 fs-5">
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
            <h2 class="fw-semibold">Laporan data uang kas</h2>
            <p class="text-muted mb-0">Rekapan uang kas kelas </p>
        </div>
 
        <!-- DOWNLOAD LAPORAN -->
        <div class="p-4 mb-4 shadow-sm rounded-4 bg-primary">
            <div class="d-flex align-items-center gap-3 mb-3">
                    <div>
                        <div class="d-flex justify-content-between gap-3">
                        <h4 class="mb-0 text-light">
                            <i class="bi bi-cursor-fill text-light"></i> Kalender Pembayaran Kas
                        </h4>
                        <a href="export_laporan.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" 
                        class="btn btn-outline-light">
                        <i class="bi bi-download me-2"></i> Unduh Laporan
                        </a>
                        </div>
                        <p class="text-light mt-2">Laporan lengkap keuangan kelas periode bulan ini</p>
                        <h6 class="mb-0 text-light">
                            <i class="bi bi-calendar4-week"></i> Laporan ini dicetak pada tanggal
                            <?php echo date('d M Y'); ?>
                        </h6>
                    </div>
            </div>
        </div>

        <!-- STATISTIK  -->
        <div class="row justify-content-center g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-success-subtle text-success p-3 rounded-3">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div>
                        <div class="text-muted">Total Terkumpul</div>
                        <h5 class="text-success mb-0">
                        Rp <?= number_format($total_masuk,0,',','.') ?></h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary-subtle text-primary p-3 rounded-3">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <div class="text-muted">Target Bulanan</div>
                        <h5 class="text-primary mb-0">Rp <?= number_format($total_target,0,',','.') ?></h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-danger-subtle text-danger p-3 rounded-3">
                       <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <div>
                        <div class="text-muted">Tunggakan Bulanan</div>
                        <h5 class="text-danger mb-0">Rp <?= number_format(max($total_target - $total_masuk,0),0,',','.') ?></h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-3">
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card bg-light border-0 text-center p-3">
                    <h4><?= $total_siswa ?></h4>
                    <small>Total Siswa</small>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-success-subtle text-success text-center p-3">
                    <h4><?= $lunas ?></h4>
                    <small>Lunas</small>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-warning-subtle text-warning text-center p-3">
                    <h4><?= $sebagian ?></h4>
                    <small>Sebagian</small>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-danger-subtle text-danger text-center p-3">
                    <h4><?= $belum ?></h4>
                    <small>Belum Bayar</small>
                </div>
            </div>
        </div>
        </div>

        <!-- LAPORAN TRANSAKSI -->
        <div class="card border-0 shadow-sm rounded-4 p-3">
            <h5 class="mb-3">Transaksi Bulan Ini</h5>
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="card text-center p-3">
                        <h4><?= $total_transaksi ?></h4>
                        <small>Total Transaksi</small>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card text-success text-center p-3">
                        <h4><?= $total_masuk > 0 ? $lunas + $sebagian : 0 ?></h4>
                        <small>Pemasukan</small>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card text-danger text-center p-3">
                        <h4><?= $total_keluar > 0 ? 1 : 0 ?></h4>
                        <small>Pengeluaran</small>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card text-primary text-center p-3">
                        <h4><?= $total_kategori ?></h4>
                        <small>Kategori</small>
                    </div>
                </div>
            </div>
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
      <form method="POST" action="proses_transaksi.php">
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

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>>

</body>
</html>
