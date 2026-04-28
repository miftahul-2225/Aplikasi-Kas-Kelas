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
                <a href="datamurid.php" class="nav-link active d-flex align-items-center gap-3 fs-5">
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
            <h2 class="fw-semibold">Data Murid</h2>
            <p class="text-muted mb-0">Daftar data murid kelas</p>
        </div>

        <!-- ACTION BUTTON -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">

        <!-- Search -->
        <form method="GET">
        <div class="input-group" style="max-width: 280px;">
        <span class="input-group-text bg-white">
            <i class="fa-solid fa-magnifying-glass"></i>
        </span>
        <input 
            type="text" 
            name="cari"
            class="form-control" 
            placeholder="Cari murid..."
            value="<?= $_GET['cari'] ?? '' ?>">
        <button class="btn btn-primary">Cari</button>
        </div>
        </form>

        <!-- Button -->
        <!-- <a href="tambah_murid.php" class="btn btn-success d-flex align-items-center">
            <i class="fa-solid fa-plus me-2"></i>
            Tambah Murid
        </a> -->
        </div>

        <h6 class="text-secondary mt-2 mb-3 fw-bold">
        Total <?= $total ?> Murid Terdaftar
        </h6>

        <!-- CONTENT -->
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-4">Daftar Murid</h5>
                <table class="table table-bordered text-center">
                    <thead>
                        <tr>
                            <th scope="col">No</th>
                            <th scope="col">Nama</th>
                            <th scope="col">NISN</th>
                            <th scope="col">Kelas</th>
                            <th scope="col">Alamat</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $no = 1; ?>
                    <?php while($row = mysqli_fetch_assoc($data)) : ?>
                        <tr>
                            <th><?= $no++ ?></th>
                            <td><?= $row['nama_siswa'] ?></td>
                            <td><?= $row['id_siswa'] ?></td>
                            <td><?= $row['kelas'] ?></td>
                            <td><?= $row['alamat'] ?></td>
                            <td><?= $row['status'] ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>