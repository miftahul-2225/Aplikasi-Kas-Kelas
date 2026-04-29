<?php
require_once 'config/koneksi.php';
 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'bendahara') {
    header("Location: login.php");
    exit();
}
 
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');
 
$q_masuk = mysqli_query($koneksi_db, "
    SELECT COALESCE(SUM(jumlah),0) as total 
    FROM tb_transaksi 
    WHERE jenis='bayar'
    AND MONTH(tanggal)='$bulan'
    AND YEAR(tanggal)='$tahun'
");
$total_masuk = mysqli_fetch_assoc($q_masuk)['total'];
 
$q_keluar = mysqli_query($koneksi_db, "
    SELECT COALESCE(SUM(jumlah),0) as total 
    FROM tb_transaksi 
    WHERE jenis='keluar'
    AND MONTH(tanggal)='$bulan'
    AND YEAR(tanggal)='$tahun'
");
$total_keluar = mysqli_fetch_assoc($q_keluar)['total'];
 
$saldo = $total_masuk - $total_keluar;
 
$q_siswa     = mysqli_query($koneksi_db, "SELECT COUNT(*) as total FROM tb_siswa");
$total_siswa = mysqli_fetch_assoc($q_siswa)['total'];
 
$target_per_siswa  = 10000;
$total_target      = $total_siswa * $target_per_siswa;
$tunggakan         = max(0, $total_target - $total_masuk);
 
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
 
$lunas = 0; $sebagian = 0; $belum = 0;
while ($row = mysqli_fetch_assoc($q_status)) {
    if ($row['bayar'] >= $target_per_siswa)     $lunas++;
    elseif ($row['bayar'] > 0)                  $sebagian++;
    else                                         $belum++;
}
 
$q_total_tr    = mysqli_query($koneksi_db, "SELECT COUNT(*) as total FROM tb_transaksi WHERE MONTH(tanggal)='$bulan' AND YEAR(tanggal)='$tahun'");
$total_transaksi = mysqli_fetch_assoc($q_total_tr)['total'];
 
$q_kategori    = mysqli_query($koneksi_db, "SELECT COUNT(DISTINCT keterangan) as total FROM tb_transaksi");
$total_kategori = mysqli_fetch_assoc($q_kategori)['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan - Kas Kelas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --sb-full : 220px;
            --sb-mini : 64px;
            --accent  : #0d6efd;
            --ease    : 0.25s ease;
        }
 
        body { background: #f4f6fb; margin: 0; }
 
        /* ══ SIDEBAR ══ */
        #sidebar {
            position: fixed; top: 0; left: 0;
            width: var(--sb-full); height: 100vh;
            background: #fff; border-right: 1px solid #e8eaf0;
            display: flex; flex-direction: column;
            z-index: 1040; overflow: hidden;
            transition: width var(--ease);
        }
        #sidebar.mini { width: var(--sb-mini); }
 
        .sb-brand {
            display: flex; align-items: center; gap: 10px;
            padding: 18px 13px 14px; white-space: nowrap;
            border-bottom: 1px solid #f0f2f7; min-height: 64px;
        }
        .sb-logo {
            width: 36px; height: 36px; background: #e8f0fe;
            border-radius: 10px; display: flex; align-items: center;
            justify-content: center; color: var(--accent); flex-shrink: 0;
        }
        .sb-title {
            font-weight: 700; font-size: 14px; color: var(--accent);
            transition: opacity var(--ease), width var(--ease); overflow: hidden;
        }
        #sidebar.mini .sb-title { opacity: 0; width: 0; }
 
        .sb-toggle {
            position: absolute; top: 20px; right: -8px;
            width: 26px; height: 26px; background: #fff;
            border: 1px solid #dde2ee; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 10px; color: #666;
            box-shadow: 0 2px 6px rgba(0,0,0,.09);
            transition: top var(--ease), right var(--ease), transform var(--ease);
            z-index: 10;
        }
        #sidebar.mini .sb-toggle { top: 56px; right: 4px; transform: rotate(180deg); }
 
        .sb-nav { flex: 1; padding: 10px 8px; overflow-y: auto; overflow-x: hidden; }
        .sb-nav .nav-link {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 10px; border-radius: 10px;
            color: #555; font-weight: 500; font-size: 14px;
            white-space: nowrap; text-decoration: none;
            transition: background .15s, color .15s; position: relative;
        }
        .sb-nav .nav-link:hover  { background: #f0f4ff; color: var(--accent); }
        .sb-nav .nav-link.active { background: var(--accent); color: #fff; }
 
        .nav-icon { font-size: 15px; width: 20px; text-align: center; flex-shrink: 0; }
        .nav-label { transition: opacity var(--ease); }
        #sidebar.mini .nav-label { opacity: 0; pointer-events: none; }
 
        #sidebar.mini .nav-link::after {
            content: attr(data-tip);
            position: absolute; left: calc(var(--sb-mini) - 4px);
            background: #1a1a2e; color: #fff;
            font-size: 12px; padding: 5px 10px; border-radius: 6px;
            white-space: nowrap; opacity: 0; pointer-events: none;
            transition: opacity .15s; z-index: 999;
        }
        #sidebar.mini .nav-link:hover::after { opacity: 1; }
 
        .sb-footer {
            padding: 10px 8px; border-top: 1px solid #f0f2f7;
            display: flex; flex-direction: column; gap: 6px;
        }
        .sb-btn {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border-radius: 10px;
            font-size: 13px; font-weight: 500; border: none; cursor: pointer;
            text-decoration: none; white-space: nowrap; overflow: hidden;
        }
        .sb-btn.primary { background: var(--accent); color: #fff; }
        .sb-btn.primary:hover { background: #1557b0; }
        .sb-btn.danger  { background: #fdecea; color: #c62828; }
        .sb-btn.danger:hover { background: #fcd5d1; }
        .sb-btn-icon { font-size: 14px; width: 20px; text-align: center; flex-shrink: 0; }
        .sb-btn-label { transition: opacity var(--ease); }
        #sidebar.mini .sb-btn-label { opacity: 0; pointer-events: none; }
 
        /* ══ MAIN ══ */
        #main { margin-left: var(--sb-full); min-height: 100vh; padding: 28px; transition: margin-left var(--ease); }
        #main.expanded { margin-left: var(--sb-mini); }
 
        /* ══ MOBILE ══ */
        #mobile-btn {
            display: none; position: fixed; top: 14px; left: 14px; z-index: 1060;
            background: var(--accent); border: none; color: #fff;
            width: 40px; height: 40px; border-radius: 10px;
            font-size: 16px; cursor: pointer; align-items: center; justify-content: center;
        }
        #overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.35); z-index: 1039; }
        #overlay.show { display: block; }
 
        @media (max-width: 767.98px) {
            #mobile-btn { display: flex; }
            #sidebar { left: calc(-1 * var(--sb-full)); width: var(--sb-full) !important; transition: left var(--ease); }
            #sidebar.open { left: 0; }
            .sb-toggle { display: none; }
            #main { margin-left: 0 !important; padding: 72px 16px 20px; }
        }
    </style>
</head>
<body>
 
<!-- MOBILE BUTTON -->
<button id="mobile-btn" onclick="mobileOpen()">
    <i class="fa-solid fa-bars"></i>
</button>
<div id="overlay" onclick="mobileClose()"></div>
 
<!-- ══ SIDEBAR ══ -->
<div id="sidebar">
    <div class="sb-brand">
        <div class="sb-logo"><i class="fa-solid fa-graduation-cap"></i></div>
        <span class="sb-title">E Kas Seven</span>
    </div>
 
    <div class="sb-toggle" onclick="desktopToggle()">
        <i class="fa-solid fa-chevron-left"></i>
    </div>
 
    <nav class="sb-nav">
        <div class="nav-item mt-1">
            <a href="dashboard.php" class="nav-link" data-tip="Dashboard">
                <i class="nav-icon fa-solid fa-house"></i>
                <span class="nav-label">Dashboard</span>
            </a>
        </div>
        <div class="nav-item mt-2">
            <a href="datamurid.php" class="nav-link" data-tip="Data Murid">
                <i class="nav-icon fa-solid fa-users"></i>
                <span class="nav-label">Data Murid</span>
            </a>
        </div>
        <div class="nav-item mt-2">
            <a href="kasmasuk.php" class="nav-link" data-tip="Kas Masuk">
                <i class="nav-icon fa-solid fa-arrow-trend-up"></i>
                <span class="nav-label">Kas Masuk</span>
            </a>
        </div>
        <div class="nav-item mt-2">
            <a href="kaskeluar.php" class="nav-link" data-tip="Kas Keluar">
                <i class="nav-icon fa-solid fa-arrow-trend-down"></i>
                <span class="nav-label">Kas Keluar</span>
            </a>
        </div>
        <div class="nav-item mt-2">
            <a href="status.php" class="nav-link" data-tip="Status Pembayaran">
                <i class="nav-icon fa-regular fa-circle-check"></i>
                <span class="nav-label">Status Pembayaran</span>
            </a>
        </div>
        <div class="nav-item mt-2">
            <a href="arus.php" class="nav-link" data-tip="Arus Kas">
                <i class="nav-icon fa-solid fa-chart-column"></i>
                <span class="nav-label">Arus Kas</span>
            </a>
        </div>
        <div class="nav-item mt-2">
            <a href="laporan.php" class="nav-link active" data-tip="Laporan">
                <i class="nav-icon fa-regular fa-file-lines"></i>
                <span class="nav-label">Laporan</span>
            </a>
        </div>
    </nav>
 
    <div class="sb-footer">
        <button class="sb-btn primary"
                data-bs-toggle="modal"
                data-bs-target="#modalTransaksi">
            <span class="sb-btn-icon"><i class="fa-solid fa-plus"></i></span>
            <span class="sb-btn-label">Tambah Transaksi</span>
        </button>
        <a href="logout.php"
           onclick="return confirm('Yakin ingin logout?')"
           class="sb-btn danger">
            <span class="sb-btn-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
            <span class="sb-btn-label">Keluar</span>
        </a>
    </div>
</div>
 
<!-- ══ MAIN ══ -->
<main id="main">
 
    <!-- HEADER -->
    <div class="mb-4">
        <h4 class="fw-bold mb-1">Laporan Data Uang Kas</h4>
        <p class="text-muted small mb-0">Rekapan uang kas kelas</p>
    </div>
 
    <!-- BANNER DOWNLOAD -->
    <div class="card border-0 rounded-4 mb-4" style="background: linear-gradient(135deg, #1a73e8, #1557b0);">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <h5 class="text-white fw-semibold mb-1">
                        <i class="bi bi-file-earmark-text me-2"></i>Laporan Keuangan Kelas
                    </h5>
                    <p class="text-white opacity-75 small mb-2">
                        Laporan lengkap keuangan kelas periode bulan ini
                    </p>
                    <p class="text-white opacity-75 small mb-0">
                        <i class="bi bi-calendar4-week me-1"></i>
                        Dicetak pada <?= date('d M Y') ?>
                    </p>
                </div>
                <a href="export_laporan.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>"
                   class="btn btn-light rounded-3">
                    <i class="bi bi-download me-2"></i>Unduh Laporan
                </a>
            </div>
        </div>
    </div>
 
    <!-- STATISTIK — sama persis dengan arus.php -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border rounded-4 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:48px;height:48px;">
                        <i class="bi bi-graph-up-arrow fs-5"></i>
                    </div>
                    <div>
                        <p class="text-muted small mb-0">Total Terkumpul</p>
                        <h5 class="text-success fw-bold mb-0">
                            Rp <?= number_format($total_masuk, 0, ',', '.') ?>
                        </h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border rounded-4 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:48px;height:48px;">
                        <i class="bi bi-people fs-5"></i>
                    </div>
                    <div>
                        <p class="text-muted small mb-0">Target Bulanan</p>
                        <h5 class="text-primary fw-bold mb-0">
                            Rp <?= number_format($total_target, 0, ',', '.') ?>
                        </h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border rounded-4 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="bg-danger bg-opacity-10 text-danger rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:48px;height:48px;">
                        <i class="fa-solid fa-chart-line fs-5"></i>
                    </div>
                    <div>
                        <p class="text-muted small mb-0">Tunggakan Bulanan</p>
                        <h5 class="text-danger fw-bold mb-0">
                            Rp <?= number_format($tunggakan, 0, ',', '.') ?>
                        </h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
 
    <!-- STATUS SISWA -->
    <div class="card border rounded-4 mb-4">
        <div class="card-body p-4">
            <h6 class="fw-semibold mb-3">Status Pembayaran Siswa</h6>
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="card border-0 text-center rounded-3 p-3" style="background:#f8f9fa;">
                        <h4 class="fw-bold mb-0"><?= $total_siswa ?></h4>
                        <small class="text-muted">Total Siswa</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 text-center rounded-3 p-3" style="background:#d1fae5;">
                        <h4 class="fw-bold text-success mb-0"><?= $lunas ?></h4>
                        <small class="text-success">Lunas</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 text-center rounded-3 p-3" style="background:#fef9c3;">
                        <h4 class="fw-bold text-warning mb-0"><?= $sebagian ?></h4>
                        <small class="text-warning">Sebagian</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 text-center rounded-3 p-3" style="background:#fee2e2;">
                        <h4 class="fw-bold text-danger mb-0"><?= $belum ?></h4>
                        <small class="text-danger">Belum Bayar</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
 
    <!-- TRANSAKSI BULAN INI -->
    <div class="card border rounded-4">
        <div class="card-body p-4">
            <h6 class="fw-semibold mb-3">Transaksi Bulan Ini</h6>
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="card border rounded-3 text-center p-3">
                        <h4 class="fw-bold mb-0"><?= $total_transaksi ?></h4>
                        <small class="text-muted">Total Transaksi</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border rounded-3 text-center p-3">
                        <h4 class="fw-bold text-success mb-0">
                            <?= $total_masuk > 0 ? $lunas + $sebagian : 0 ?>
                        </h4>
                        <small class="text-success">Pemasukan</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border rounded-3 text-center p-3">
                        <h4 class="fw-bold text-danger mb-0">
                            <?= $total_keluar > 0 ? 1 : 0 ?>
                        </h4>
                        <small class="text-danger">Pengeluaran</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border rounded-3 text-center p-3">
                        <h4 class="fw-bold text-primary mb-0"><?= $total_kategori ?></h4>
                        <small class="text-primary">Kategori</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
 
</main>
 
<!-- ══ MODAL TRANSAKSI ══ -->
<div class="modal fade" id="modalTransaksi" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-semibold">Tambah Transaksi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="proses_transaksi.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Jenis</label>
                        <select name="jenis" class="form-select rounded-3" required>
                            <option value="">-- Pilih --</option>
                            <option value="bayar">Pemasukan</option>
                            <option value="pengeluaran">Pengeluaran</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea name="keterangan" class="form-control rounded-3" rows="2" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jumlah</label>
                        <input type="number" name="jumlah" class="form-control rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" name="tanggal" class="form-control rounded-3" required>
                    </div>
                    <button type="submit" name="simpan" class="btn btn-primary w-100 rounded-3">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
 
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function desktopToggle() {
        document.getElementById('sidebar').classList.toggle('mini');
        document.getElementById('main').classList.toggle('expanded');
    }
    function mobileOpen() {
        document.getElementById('sidebar').classList.add('open');
        document.getElementById('overlay').classList.add('show');
    }
    function mobileClose() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('overlay').classList.remove('show');
    }
    document.querySelectorAll('#sidebar .nav-link').forEach(link => {
        link.addEventListener('click', mobileClose);
    });
</script>
</body>
</html>