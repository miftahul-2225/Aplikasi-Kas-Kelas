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

$q_periode = mysqli_query($koneksi_db, "
    SELECT * FROM tb_periode 
    WHERE status='aktif'
    AND tanggal_mulai <= LAST_DAY('$tahun-$bulan-01')
    AND tanggal_selesai >= '$tahun-$bulan-01'
    ORDER BY tanggal_mulai ASC
");

$total_target = 0;
while ($p = mysqli_fetch_assoc($q_periode)) {
    $total_target += ($p['target'] ?? 10000) * $total_siswa;
}

$target_per_siswa = $total_siswa > 0 ? ($total_target / $total_siswa) : 10000;
$tunggakan        = max(0, $total_target - $total_masuk);
 
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
    if ($row['bayar'] >= $target_per_siswa)  $lunas++;
    elseif ($row['bayar'] > 0)               $sebagian++;
    else                                      $belum++;
}
 
$q_total_tr = mysqli_query($koneksi_db, "
    SELECT COUNT(*) as total FROM tb_transaksi 
    WHERE MONTH(tanggal)='$bulan' AND YEAR(tanggal)='$tahun'
");
$total_transaksi = mysqli_fetch_assoc($q_total_tr)['total'];

$q_jml_masuk = mysqli_query($koneksi_db, "
    SELECT COUNT(*) as total FROM tb_transaksi 
    WHERE jenis='bayar' AND MONTH(tanggal)='$bulan' AND YEAR(tanggal)='$tahun'
");
$jml_transaksi_masuk = mysqli_fetch_assoc($q_jml_masuk)['total'];

$q_jml_keluar = mysqli_query($koneksi_db, "
    SELECT COUNT(*) as total FROM tb_transaksi 
    WHERE jenis='keluar' AND MONTH(tanggal)='$bulan' AND YEAR(tanggal)='$tahun'
");
$jml_transaksi_keluar = mysqli_fetch_assoc($q_jml_keluar)['total'];
 
$q_kategori = mysqli_query($koneksi_db, "SELECT COUNT(DISTINCT keterangan) as total FROM tb_transaksi");
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

        /* ══ BOTTOM NAVIGATION (Mobile Only) ══ */
        #bottom-nav {
            display: none;
            position: fixed; bottom: 0; left: 0; right: 0;
            height: 64px; background: #fff;
            border-top: 1px solid #e8eaf0; z-index: 1050;
            align-items: center; justify-content: space-around;
            padding: 0 4px; box-shadow: 0 -4px 16px rgba(0,0,0,0.07);
        }
        .bn-item {
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; gap: 3px; flex: 1; height: 100%;
            text-decoration: none; color: #aaa; font-weight: 500;
            transition: color .15s; padding: 6px 2px;
        }
        .bn-item.active { color: var(--accent); }
        .bn-item i { font-size: 19px; }
        .bn-item span { font-size: 9px; line-height: 1; }

        .bn-add {
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; gap: 3px; flex: 1; height: 100%;
            border: none; background: none; cursor: pointer; padding: 6px 2px;
        }
        .bn-add .bn-add-icon {
            width: 42px; height: 42px; background: var(--accent);
            border-radius: 50%; display: flex; align-items: center;
            justify-content: center; font-size: 18px; color: #fff;
            box-shadow: 0 4px 14px rgba(13,110,253,0.40);
            transition: transform .15s, box-shadow .15s;
        }
        .bn-add:active .bn-add-icon { transform: scale(0.92); box-shadow: 0 2px 6px rgba(13,110,253,0.3); }
        .bn-add .bn-add-label { font-size: 9px; color: #aaa; line-height: 1; }

        /* ══ MOBILE ══ */
        @media (max-width: 767.98px) {
            #sidebar    { display: none !important; }
            #mobile-btn { display: none !important; }
            #overlay    { display: none !important; }
            #main { margin-left: 0 !important; padding: 20px 16px 84px; }
            #bottom-nav { display: flex !important; }
        }
    </style>
</head>
<body>
 
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
        <button class="sb-btn primary" data-bs-toggle="modal" data-bs-target="#modalTransaksi">
            <span class="sb-btn-icon"><i class="fa-solid fa-plus"></i></span>
            <span class="sb-btn-label">Tambah Transaksi</span>
        </button>
        <a href="logout.php" onclick="return confirm('Yakin ingin logout?')" class="sb-btn danger">
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
 
    <!-- STATISTIK -->
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
                        <i class="bi bi-graph-down-arrow fs-5"></i>
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
 
   <!-- STATUS SISWA - Disesuaikan dengan struktur DB -->
    <div class="card border rounded-4 mb-4">
        <div class="card-body p-4">
            <h6 class="fw-semibold mb-3">Status Pembayaran Siswa</h6>

            <!-- TABS -->
            <ul class="nav nav-pills mb-3" id="statusTab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="pill"
                        data-bs-target="#paneRingkasan" type="button">
                        <i class="bi bi-calendar3 me-1"></i>Ringkasan Bulanan
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="tab-detail" data-bs-toggle="pill"
                        data-bs-target="#paneDetail" type="button">
                        <i class="bi bi-people me-1"></i>Detail Siswa
                    </button>
                </li>
            </ul>

            <div class="tab-content">

                <!-- ═══ TAB 1: RINGKASAN BULANAN ═══ -->
                <div class="tab-pane fade show active" id="paneRingkasan">

                    <!-- Summary kartu bulan aktif -->
                    <div class="row g-3 mb-4">
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

                    <!-- Tabel riwayat 12 bulan -->
                    <?php
                    $nama_bulan = [
                        '', 'Januari','Februari','Maret','April','Mei','Juni',
                        'Juli','Agustus','September','Oktober','November','Desember'
                    ];
                    $tahun_loop = $tahun; // pakai $tahun dari filter atas
                    ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Bulan</th>
                                    <th style="min-width:140px;">Progres</th>
                                    <th class="text-center text-success">Lunas</th>
                                    <th class="text-center text-warning">Sebagian</th>
                                    <th class="text-center text-danger">Belum</th>
                                    <th class="text-end">Terkumpul</th>
                                    <th class="text-end">Target</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php for ($b = 1; $b <= 12; $b++):
                                $is_current = ($b == $bulan && $tahun_loop == $tahun);

                                // Hitung target bulan ini dari tb_periode
                                $q_tp = mysqli_query($koneksi_db, "
                                    SELECT COALESCE(SUM(target), 0) as total_target
                                    FROM tb_periode
                                    WHERE status = 'aktif'
                                    AND tanggal_mulai <= LAST_DAY('$tahun_loop-$b-01')
                                    AND tanggal_selesai >= '$tahun_loop-$b-01'
                                ");
                                $target_row     = mysqli_fetch_assoc($q_tp);
                                $tgt_per_siswa_b = (int)($target_row['total_target'] > 0
                                    ? $target_row['total_target']
                                    : 10000);
                                $tgt_total_b     = $tgt_per_siswa_b * $total_siswa;

                                // Total bayar bulan ini
                                $q_mb = mysqli_query($koneksi_db, "
                                    SELECT COALESCE(SUM(jumlah), 0) as total
                                    FROM tb_transaksi
                                    WHERE jenis = 'bayar'
                                    AND MONTH(tanggal) = $b
                                    AND YEAR(tanggal)  = $tahun_loop
                                ");
                                $masuk_b = (int)mysqli_fetch_assoc($q_mb)['total'];

                                // Status per siswa bulan ini
                                // pakai nama_siswa sesuai kolom di tb_siswa
                                $q_sb = mysqli_query($koneksi_db, "
                                    SELECT s.id_siswa,
                                        COALESCE(SUM(tr.jumlah), 0) as bayar
                                    FROM tb_siswa s
                                    LEFT JOIN tb_transaksi tr
                                        ON s.id_siswa = tr.id_siswa
                                        AND tr.jenis  = 'bayar'
                                        AND MONTH(tr.tanggal) = $b
                                        AND YEAR(tr.tanggal)  = $tahun_loop
                                    WHERE s.status = 'aktif'
                                    GROUP BY s.id_siswa
                                ");
                                $l_b = 0; $sb_b = 0; $bl_b = 0;
                                while ($row_b = mysqli_fetch_assoc($q_sb)) {
                                    if ($row_b['bayar'] >= $tgt_per_siswa_b) $l_b++;
                                    elseif ($row_b['bayar'] > 0)             $sb_b++;
                                    else                                      $bl_b++;
                                }
                                $pct_b = $tgt_total_b > 0
                                    ? min(100, round($masuk_b / $tgt_total_b * 100))
                                    : 0;
                            ?>
                            <tr class="<?= $is_current ? 'table-primary' : '' ?>">
                                <td class="<?= $is_current ? 'fw-semibold' : '' ?>">
                                    <?= $nama_bulan[$b] ?> <?= $tahun_loop ?>
                                    <?php if ($is_current): ?>
                                        <span class="badge bg-primary ms-1" style="font-size:9px;">Aktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="progress rounded-pill mb-1" style="height:6px;">
                                        <div class="progress-bar <?= $pct_b >= 100 ? 'bg-success' : ($pct_b > 0 ? 'bg-warning' : 'bg-danger') ?>"
                                            style="width:<?= $pct_b ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?= $pct_b ?>%</small>
                                </td>
                                <td class="text-center fw-semibold text-success"><?= $l_b ?></td>
                                <td class="text-center fw-semibold text-warning"><?= $sb_b ?></td>
                                <td class="text-center fw-semibold text-danger"><?= $bl_b ?></td>
                                <td class="text-end text-nowrap">
                                    Rp <?= number_format($masuk_b, 0, ',', '.') ?>
                                </td>
                                <td class="text-end text-nowrap text-muted">
                                    Rp <?= number_format($tgt_total_b, 0, ',', '.') ?>
                                </td>
                            </tr>
                            <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div><!-- /paneRingkasan -->


                <!-- ═══ TAB 2: DETAIL SISWA ═══ -->
                <div class="tab-pane fade" id="paneDetail">

                    <!-- Ambil semua data siswa aktif + bayaran per bulan sekaligus -->
                    <?php
                    // Ambil siswa aktif saja
                    $q_all = mysqli_query($koneksi_db, "
                        SELECT id_siswa, nama_siswa, kelas
                        FROM tb_siswa
                        WHERE status = 'aktif'
                        ORDER BY nama_siswa ASC
                    ");
                    $arr_siswa = [];
                    while ($s = mysqli_fetch_assoc($q_all)) $arr_siswa[] = $s;

                    // Ambil semua pembayaran tahun ini sekaligus (1 query, efisien)
                    $q_pay = mysqli_query($koneksi_db, "
                        SELECT id_siswa,
                            MONTH(tanggal) as bln,
                            SUM(jumlah)    as total
                        FROM tb_transaksi
                        WHERE jenis = 'bayar'
                        AND YEAR(tanggal) = $tahun_loop
                        GROUP BY id_siswa, MONTH(tanggal)
                    ");
                    $pay_map = [];
                    while ($r = mysqli_fetch_assoc($q_pay))
                        $pay_map[$r['id_siswa']][(int)$r['bln']] = (int)$r['total'];

                    // Target per siswa per bulan (array 12 bulan)
                    $target_map = [];
                    for ($b = 1; $b <= 12; $b++) {
                        $q_tm = mysqli_query($koneksi_db, "
                            SELECT COALESCE(SUM(target), 0) as t
                            FROM tb_periode
                            WHERE status = 'aktif'
                            AND tanggal_mulai <= LAST_DAY('$tahun_loop-$b-01')
                            AND tanggal_selesai >= '$tahun_loop-$b-01'
                        ");
                        $tm = (int)mysqli_fetch_assoc($q_tm)['t'];
                        $target_map[$b] = $tm > 0 ? $tm : 10000;
                    }
                    ?>

                    <script>
                    const siswaDt   = <?= json_encode(array_values($arr_siswa)) ?>;
                    const payMap    = <?= json_encode($pay_map) ?>;
                    const targetMap = <?= json_encode($target_map) ?>;
                    const namaBulan = ['','Januari','Februari','Maret','April',
                                    'Mei','Juni','Juli','Agustus',
                                    'September','Oktober','November','Desember'];

                    function inisial(n){
                        return n.split(' ').slice(0,2).map(w=>w[0]).join('').toUpperCase();
                    }

                    function filterSiswa(){
                        const q    = document.getElementById('cariSiswa').value.toLowerCase();
                        const bln  = parseInt(document.getElementById('filterBulan').value);
                        const stat = document.getElementById('filterStatus').value;
                        const tgt  = targetMap[bln] ?? 10000;

                        let html = ''; let count = 0;
                        siswaDt.forEach(s => {
                            if (q && !s.nama_siswa.toLowerCase().includes(q)) return;

                            const bayar  = (payMap[s.id_siswa] ?? {})[bln] ?? 0;
                            const status = bayar >= tgt ? 'lunas' : bayar > 0 ? 'sebagian' : 'belum';
                            if (stat && status !== stat) return;
                            count++;

                            const pct = Math.min(100, tgt > 0 ? Math.round(bayar / tgt * 100) : 0);
                            const barColor = status==='lunas'   ? 'bg-success'
                                        : status==='sebagian'? 'bg-warning' : 'bg-danger';
                            const badge    = status==='lunas'
                                ? '<span class="badge rounded-pill text-bg-success">Lunas</span>'
                                : status==='sebagian'
                                ? '<span class="badge rounded-pill text-bg-warning">Sebagian</span>'
                                : '<span class="badge rounded-pill text-bg-danger">Belum Bayar</span>';

                            const sisa = Math.max(0, tgt - bayar);

                            html += `<tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary
                                                    d-flex align-items-center justify-content-center flex-shrink-0"
                                            style="width:32px;height:32px;font-size:11px;font-weight:600;">
                                            ${inisial(s.nama_siswa)}
                                        </div>
                                        <div>
                                            <div class="fw-medium" style="font-size:13px;">${s.nama_siswa}</div>
                                            <div class="text-muted" style="font-size:11px;">${s.kelas ?? '-'}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="progress rounded-pill mb-1" style="height:5px;min-width:80px;">
                                        <div class="progress-bar ${barColor}" style="width:${pct}%"></div>
                                    </div>
                                    <small class="text-muted">${pct}%</small>
                                </td>
                                <td class="text-end fw-medium" style="font-size:13px;">
                                    Rp ${bayar.toLocaleString('id-ID')}
                                </td>
                                <td class="text-end text-muted" style="font-size:12px;">
                                    Rp ${tgt.toLocaleString('id-ID')}
                                </td>
                                <td class="text-end text-danger" style="font-size:12px;">
                                    ${sisa > 0 ? 'Rp '+sisa.toLocaleString('id-ID') : '<span class="text-success">-</span>'}
                                </td>
                                <td class="text-center">${badge}</td>
                            </tr>`;
                        });

                        document.getElementById('bodyDetail').innerHTML = html;
                        document.getElementById('jumlahSiswa').textContent = count + ' siswa';
                        document.getElementById('emptyMsg').style.display = count ? 'none' : 'block';
                    }
                    </script>

                    <!-- Filter bar -->
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-md-4">
                            <input type="text" id="cariSiswa"
                                class="form-control form-control-sm rounded-3"
                                placeholder="Cari nama siswa…"
                                oninput="filterSiswa()">
                        </div>
                        <div class="col-6 col-md-3">
                            <select id="filterBulan" class="form-select form-select-sm rounded-3"
                                    onchange="filterSiswa()">
                                <?php for ($b = 1; $b <= 12; $b++): ?>
                                <option value="<?= $b ?>" <?= $b == $bulan ? 'selected' : '' ?>>
                                    <?= $nama_bulan[$b] ?> <?= $tahun_loop ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <select id="filterStatus" class="form-select form-select-sm rounded-3"
                                    onchange="filterSiswa()">
                                <option value="">Semua Status</option>
                                <option value="lunas">Lunas</option>
                                <option value="sebagian">Sebagian</option>
                                <option value="belum">Belum Bayar</option>
                            </select>
                        </div>
                    </div>

                    <!-- Tabel detail -->
                    <div class="table-responsive">
                        <div class="mb-2">
                            <small class="text-muted" id="jumlahSiswa">— siswa</small>
                        </div>
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nama Siswa</th>
                                    <th style="min-width:120px;">Progres</th>
                                    <th class="text-end">Dibayar</th>
                                    <th class="text-end">Target</th>
                                    <th class="text-end text-danger">Sisa</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody id="bodyDetail"></tbody>
                        </table>
                        <p id="emptyMsg" class="text-center text-muted small py-3" style="display:none">
                            <i class="bi bi-inbox me-1"></i>Tidak ada data ditemukan.
                        </p>
                    </div>
                </div><!-- /paneDetail -->

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
                        <h4 class="fw-bold text-success mb-0"><?= $jml_transaksi_masuk ?></h4>
                        <small class="text-success">Pemasukan</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border rounded-3 text-center p-3">
                        <h4 class="fw-bold text-danger mb-0"><?= $jml_transaksi_keluar ?></h4>
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

<!-- ══ BOTTOM NAVIGATION (Mobile Only) ══ -->
<div id="bottom-nav">
    <a href="dashboard.php" class="bn-item">
        <i class="fa-solid fa-house"></i>
        <span>Dashboard</span>
    </a>
    <a href="datamurid.php" class="bn-item">
        <i class="fa-solid fa-users"></i>
        <span>Murid</span>
    </a>
    <a href="status.php" class="bn-item">
        <i class="fa-regular fa-circle-check"></i>
        <span>Status</span>
    </a>
    <button class="bn-add" data-bs-toggle="modal" data-bs-target="#modalTransaksi">
        <div class="bn-add-icon"><i class="fa-solid fa-plus"></i></div>
        <span class="bn-add-label">Tambah</span>
    </button>
    <a href="arus.php" class="bn-item">
        <i class="fa-solid fa-chart-column"></i>
        <span>Arus Kas</span>
    </a>
    <a href="laporan.php" class="bn-item active">
        <i class="fa-regular fa-file-lines"></i>
        <span>Laporan</span>
    </a>
    <a href="logout.php" onclick="return confirm('Yakin ingin logout?')" class="bn-item">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Keluar</span>
    </a>
</div>
 
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
</script>
</body>
</html>