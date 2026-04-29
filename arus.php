<?php
require_once 'config/koneksi.php';
 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'bendahara') {
    header("Location: login.php");
    exit();
}
 
$bulan  = $_GET['bulan']  ?? date('m');
$tahun  = $_GET['tahun']  ?? date('Y');
$limit  = $_GET['limit']  ?? 10;
$filter = $_GET['filter'] ?? 'all';
$sort   = $_GET['sort']   ?? 'nama';
 
$periode = mysqli_query($koneksi_db, "
    SELECT * FROM tb_periode 
    WHERE status='aktif'
    AND tanggal_mulai <= LAST_DAY('$tahun-$bulan-01')
    AND tanggal_selesai >= '$tahun-$bulan-01'
    ORDER BY tanggal_mulai ASC
");
 
$q_siswa     = mysqli_query($koneksi_db, "SELECT COUNT(*) as total FROM tb_siswa");
$total_siswa = mysqli_fetch_assoc($q_siswa)['total'];
 
$q_total = mysqli_query($koneksi_db, "
    SELECT COALESCE(SUM(jumlah),0) as total 
    FROM tb_transaksi 
    WHERE jenis='bayar'
    AND MONTH(tanggal) = '$bulan'
    AND YEAR(tanggal) = '$tahun'
");
$total_terkumpul = mysqli_fetch_assoc($q_total)['total'];
 
$total_target_bulanan = 0;
$periode_list = [];
 
while ($p = mysqli_fetch_assoc($periode)) {
    $periode_list[] = $p;
    $total_target_bulanan += ($p['target'] ?? 10000) * $total_siswa;
}
 
$tunggakan = max(0, $total_target_bulanan - $total_terkumpul);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Arus Kas - Kas Kelas</title>
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
            <a href="arus.php" class="nav-link active" data-tip="Arus Kas">
                <i class="nav-icon fa-solid fa-chart-column"></i>
                <span class="nav-label">Arus Kas</span>
            </a>
        </div>
        <div class="nav-item mt-2">
            <a href="laporan.php" class="nav-link" data-tip="Laporan">
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
        <h4 class="fw-bold mb-1">Arus Kas dan Kalender Pemantauan Siswa</h4>
        <p class="text-muted small mb-0">Pantau pembayaran uang kas dan tagihan siswa</p>
    </div>
 
    <!-- KALENDER NAV -->
    <div class="card border-0 rounded-4 mb-4 p-4" style="background:#eaf2ff;">
        <div class="d-flex align-items-center gap-3 mb-3">
            <div class="bg-primary text-white rounded-3 d-flex align-items-center justify-content-center"
                 style="width:44px;height:44px;">
                <i class="bi bi-calendar"></i>
            </div>
            <div>
                <h5 class="mb-0 fw-semibold">Kalender Pembayaran Kas</h5>
                <small class="text-primary">Rp 10.000 per minggu</small>
            </div>
        </div>
 
        <div class="d-flex justify-content-between align-items-center">
            <button id="prevMonth" class="btn btn-white border rounded-pill px-3">
                <i class="bi bi-chevron-left"></i> Sebelumnya
            </button>
            <div class="text-center">
                <h5 class="mb-0 fw-semibold" id="monthYear"></h5>
                <small class="text-primary" id="currentLabel"></small>
            </div>
            <button id="nextMonth" class="btn btn-white border rounded-pill px-3">
                Berikutnya <i class="bi bi-chevron-right"></i>
            </button>
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
                            Rp <?= number_format($total_terkumpul, 0, ',', '.') ?>
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
                            Rp <?= number_format($total_target_bulanan, 0, ',', '.') ?>
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
 
    <!-- PERIODE CARDS -->
    <?php
    $data_bayar = [];
    $q_all = mysqli_query($koneksi_db, "
        SELECT id_periode, id_siswa, SUM(jumlah) as total
        FROM tb_transaksi
        WHERE jenis='bayar'
        AND MONTH(tanggal) = '$bulan'
        AND YEAR(tanggal) = '$tahun'
        GROUP BY id_periode, id_siswa
    ");
    while ($d = mysqli_fetch_assoc($q_all)) {
        $data_bayar[$d['id_periode']][$d['id_siswa']] = $d['total'];
    }
    ?>
 
    <div class="row g-4">
    <?php foreach ($periode_list as $p):
        $id_periode  = $p['id_periode'];
        $total       = isset($data_bayar[$id_periode]) ? array_sum($data_bayar[$id_periode]) : 0;
        $target_total = ($p['target'] ?? 10000) * $total_siswa;
        $persen      = $target_total > 0 ? min(($total / $target_total) * 100, 100) : 0;
    ?>
    <div class="col-md-6">
        <div class="card border-0 rounded-4 overflow-hidden shadow-sm">
 
            <!-- HEADER CARD -->
            <div class="p-4 text-white" style="background: linear-gradient(135deg,#4f8cff,#1e40af);">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-semibold mb-0"><?= htmlspecialchars($p['nama_periode']) ?></h6>
                    <span class="badge bg-white text-primary small">
                        <?= date('d M', strtotime($p['tanggal_mulai'])) ?> -
                        <?= date('d M', strtotime($p['tanggal_selesai'])) ?>
                    </span>
                </div>
 
                <div class="d-flex justify-content-between align-items-end mb-3">
                    <div>
                        <p class="small opacity-75 mb-0">Terkumpul</p>
                        <h5 class="fw-bold mb-0">Rp <?= number_format($total, 0, ',', '.') ?></h5>
                    </div>
                    <div class="text-end">
                        <p class="small opacity-75 mb-0">Target</p>
                        <h6 class="fw-bold mb-0">Rp <?= number_format($target_total, 0, ',', '.') ?></h6>
                    </div>
                </div>
 
                <div class="progress rounded-pill" style="height:8px;">
                    <div class="progress-bar bg-success progress-bar-striped progress-bar-animated"
                         style="width:<?= $persen ?>%"></div>
                </div>
                <div class="d-flex justify-content-between mt-1 small opacity-90">
                    <span><?= round($persen) ?>%</span>
                    <span>Kurang Rp <?= number_format($target_total - $total, 0, ',', '.') ?></span>
                </div>
            </div>
 
            <!-- FILTER -->
            <div class="p-3 border-bottom bg-white">
                <div class="d-flex gap-2 flex-wrap">
                    <select onchange="updateFilter()" id="filterStatus" class="form-select form-select-sm w-auto">
                        <option value="all"    <?= $filter=='all'     ? 'selected':'' ?>>Semua</option>
                        <option value="lunas"  <?= $filter=='lunas'   ? 'selected':'' ?>>Lunas</option>
                        <option value="sebagian" <?= $filter=='sebagian'? 'selected':'' ?>>Sebagian</option>
                        <option value="belum"  <?= $filter=='belum'   ? 'selected':'' ?>>Belum</option>
                    </select>
                    <select onchange="updateFilter()" id="limitData" class="form-select form-select-sm w-auto">
                        <option value="10" <?= $limit==10 ? 'selected':'' ?>>10</option>
                        <option value="15" <?= $limit==15 ? 'selected':'' ?>>15</option>
                        <option value="33" <?= $limit==33 ? 'selected':'' ?>>33</option>
                    </select>
                    <select onchange="updateFilter()" id="sortData" class="form-select form-select-sm w-auto">
                        <option value="nama"      <?= $sort=='nama'      ? 'selected':'' ?>>Nama</option>
                        <option value="bayar_desc" <?= $sort=='bayar_desc'? 'selected':'' ?>>Terbesar</option>
                        <option value="bayar_asc"  <?= $sort=='bayar_asc' ? 'selected':'' ?>>Terkecil</option>
                    </select>
                </div>
            </div>
 
            <!-- LIST SISWA -->
            <div class="p-3 bg-white">
                <?php
                $data_siswa = [];
                $siswa_loop = mysqli_query($koneksi_db, "SELECT * FROM tb_siswa");
                while ($s = mysqli_fetch_assoc($siswa_loop)) {
                    $bayar  = $data_bayar[$id_periode][$s['id_siswa']] ?? 0;
                    $target = $p['target'] ?? 10000;
 
                    if ($bayar >= $target)      { $status='lunas';    $warna='success'; $icon='check-circle'; }
                    elseif ($bayar > 0)         { $status='sebagian'; $warna='warning'; $icon='exclamation-circle'; }
                    else                        { $status='belum';    $warna='danger';  $icon='times-circle'; }
 
                    if ($filter != 'all' && $status != $filter) continue;
 
                    $s['bayar']  = $bayar;
                    $s['status'] = $status;
                    $s['warna']  = $warna;
                    $s['icon']   = $icon;
                    $data_siswa[] = $s;
                }
 
                if ($sort == 'bayar_desc')     usort($data_siswa, fn($a,$b) => $b['bayar'] <=> $a['bayar']);
                elseif ($sort == 'bayar_asc')  usort($data_siswa, fn($a,$b) => $a['bayar'] <=> $b['bayar']);
                else                           usort($data_siswa, fn($a,$b) => strcmp($a['nama_siswa'], $b['nama_siswa']));
 
                $data_siswa = array_slice($data_siswa, 0, $limit);
 
                foreach ($data_siswa as $s): ?>
                <div class="d-flex justify-content-between align-items-center border rounded-3 p-2 mb-2 bg-light">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-<?= $s['warna'] ?> bg-opacity-10 text-<?= $s['warna'] ?> rounded-circle d-flex align-items-center justify-content-center"
                             style="width:36px;height:36px;">
                            <i class="fa fa-<?= $s['icon'] ?> small"></i>
                        </div>
                        <div>
                            <p class="fw-semibold small mb-0"><?= htmlspecialchars($s['nama_siswa']) ?></p>
                            <small class="text-muted">Rp <?= number_format($s['bayar'], 0, ',', '.') ?></small>
                        </div>
                    </div>
                    <span class="badge bg-<?= $s['warna'] ?> rounded-pill px-3" style="font-size:11px;">
                        <?= ucfirst($s['status']) ?>
                    </span>
                </div>
                <?php endforeach; ?>
 
                <?php if (empty($data_siswa)): ?>
                <p class="text-muted text-center small py-2">Tidak ada data.</p>
                <?php endif; ?>
            </div>
 
        </div>
    </div>
    <?php endforeach; ?>
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
    /* ── SIDEBAR ── */
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
 
    /* ── KALENDER ── */
    const monthNames = ["Januari","Februari","Maret","April","Mei","Juni",
                        "Juli","Agustus","September","Oktober","November","Desember"];
 
    let currentDate = new Date(<?= $tahun ?>, <?= $bulan ?> - 1);
 
    function renderCalendar() {
        const m = currentDate.getMonth();
        const y = currentDate.getFullYear();
        document.getElementById('monthYear').textContent = monthNames[m] + ' ' + y;
        const today = new Date();
        document.getElementById('currentLabel').textContent =
            (m === today.getMonth() && y === today.getFullYear()) ? 'Bulan Ini' : '';
    }
 
    function updateURL() {
        window.location.href = `?bulan=${currentDate.getMonth()+1}&tahun=${currentDate.getFullYear()}`;
    }
 
    document.getElementById('prevMonth').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar(); updateURL();
    });
    document.getElementById('nextMonth').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar(); updateURL();
    });
 
    renderCalendar();
 
    /* ── FILTER ── */
    function updateFilter() {
        const url = new URL(window.location.href);
        url.searchParams.set('filter', document.getElementById('filterStatus').value);
        url.searchParams.set('limit',  document.getElementById('limitData').value);
        url.searchParams.set('sort',   document.getElementById('sortData').value);
        url.searchParams.set('bulan',  <?= $bulan ?>);
        url.searchParams.set('tahun',  <?= $tahun ?>);
        window.location.href = url.toString();
    }
</script>
</body>
</html>