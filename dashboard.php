

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

    /* MOBILE */
    @media (max-width: 768px) {
        main {
        margin-left: 0 !important; /* HAPUS geseran */
        }
        .card {
            border-radius: 12px;
        }
    }

    /* SIDEBAR */
    #sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    height: 100vh;
    display: flex;
    flex-direction: column;
    z-index: 1000;
    background: white;
    border-right: 1px solid #e9ecef;
    }

    #sidebar .offcanvas-body {
        padding: 1rem;
        height: 100%;
    }

    .nav-link {
    padding: 10px 14px;
    border-radius: 10px;
    }

    .nav-pills .nav-link.active {
        background-color: #0d6efd;
    }

    .nav-pills .nav-link:hover {
        cursor: pointer;
    }

    /* MAIN */
    main {
    margin-left: 250px;
    height: 100vh;
    overflow-y: auto;
    overflow-x: hidden;
    transition: all 0.3s ease;
    }
    /* OPTIONAL (biar lebih rapi) */
    body {
        overflow-x: hidden;
    }
    </style>
</head>
<body class="bg-light">
<!-- BUTTON MOBILE -->
<button class="btn btn-primary d-md-none m-3"
        data-bs-toggle="offcanvas"
        data-bs-target="#sidebar">
    ☰ Menu
</button>

<div>

    <!-- SIDEBAR -->
    <div id="sidebar"
     class="offcanvas-md offcanvas-start d-flex flex-column bg-white border-end"
     tabindex="-1"
     >
        
        <!-- HEADER -->
        <div class="offcanvas-header d-md-none me-1">
        <div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center"
                 style="width:44px; height:44px;">
                <i class="fa-solid fa-graduation-cap"></i>
        </div>
        <h5 class="text-primary mb-0">E Kas Seven</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>

        <div class="offcanvas-body d-flex flex-column px-3 py-4">

        <!-- HEADER DESKTOP -->
        <div class="d-none d-md-flex align-items-center gap-3 mb-4 px-3">
            <div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center"
                style="width:44px; height:44px;">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <h5 class="text-primary mb-0">E Kas Seven</h5>
        </div>

        <!-- MENU -->
        <ul class="nav nav-pills flex-column gap-2 mb-auto">

            <li class="nav-item">
                <a href="dashboard.php" class="nav-link active d-flex align-items-center gap-3 fs-5"
                data-bs-dismiss="offcanvas">
                    <i class="fa-solid fa-house"></i>
                    Dashboard
                </a>
            </li>

            <li class="nav-item">
                <a href="datamurid.php" class="nav-link text-dark d-flex align-items-center gap-3 fs-5" >
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
                <a href="laporan.php" class="nav-link text-dark d-flex align-items-center gap-3 fs-5">
                    <i class="fa-regular fa-file-lines"></i>
                    Laporan
                </a>
            </li>
        </ul>

        <!-- FOOTER BUTTON -->
        <div class="d-grid gap-3 mt-auto pt-4">
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

        </div>
    </div>

    <!-- MAIN CONTENT -->
    <main class="flex-fill p-3 p-md-4">

        <!-- HEADER -->
        <div class="mb-4">
            <h2 class="fw-semibold">Dashboard Bendahara</h2>
            <p class="text-muted mb-0">Ringkasan saldo dan aktivitas kas kelas</p>
        </div>

        <!-- SALDO -->
        <div class="card text-white bg-primary mb-4 shadow-sm">
            <div class="card-body">
                <h2>Saldo Kas Kelas</h2>
                <h3>Rp <?= number_format($saldo,0,',','.') ?></h3>
            </div>
        </div>

        <!-- SUMMARY -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <small class="text-muted">Pemasukan</small>
                        <h3 class="text-success mt-2">
                        Rp <?= number_format($pemasukan,0,',','.') ?>
                        </h3>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <small class="text-muted">Pengeluaran</small>
                        <h3 class="text-danger mt-2">
                        Rp <?= number_format($pengeluaran,0,',','.') ?>
                        </h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- TRANSAKSI -->
        <h4 class="mb-3">Transaksi Terbaru</h4>
        <?php while($row = mysqli_fetch_assoc($transaksi)) : ?>
        <div class="card mb-3 shadow-sm">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            
            <div>
                <strong>
                    <?= $row['jenis'] == 'bayar' 
                        ? 'Iuran Kas - ' . $row['nama_siswa'] 
                        : 'Pengeluaran Kas' ?>
                </strong>

                <div class="text-muted small">
                    <?= $row['keterangan'] ?> • 
                    <?= date('d M Y', strtotime($row['tanggal'])) ?>
                </div>
            </div>

            <span class="<?= $row['jenis']=='bayar' ? 'text-success' : 'text-danger' ?> fw-semibold">
                <?= $row['jenis']=='bayar' ? '+' : '-' ?>
                <?= number_format($row['jumlah'],0,',','.') ?>
            </span>

            </div>
        </div>
        <?php endwhile; ?>
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

<script>


</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>