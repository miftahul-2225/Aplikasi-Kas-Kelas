<?php
require_once 'config/koneksi.php';
 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'bendahara') {
    header("Location: login.php");
    exit();
}
 
// fungsi format bulan indo
function bulanIndo($bulan){
    $nama = [
        1 => 'Januari','Februari','Maret','April','Mei','Juni',
        'Juli','Agustus','September','Oktober','November','Desember'
    ];
    return $nama[(int)$bulan];
}
 
// fungsi ambil periode / auto generate
function getPeriode($koneksi_db, $tanggal){
    $mulai   = date('Y-m-d', strtotime('monday this week', strtotime($tanggal)));
    $selesai = date('Y-m-d', strtotime('sunday this week', strtotime($tanggal)));
 
    $cek = mysqli_query($koneksi_db, "
        SELECT * FROM tb_periode
        WHERE tanggal_mulai = '$mulai'
        AND tanggal_selesai = '$selesai'
        LIMIT 1
    ");
    if (!$cek) die(mysqli_error($koneksi_db));
 
    $data = mysqli_fetch_assoc($cek);
    if ($data) return $data;
 
    $tanggal_obj    = strtotime($tanggal);
    $tanggal_hari   = date('j', $tanggal_obj);
    $bulan          = date('n', $tanggal_obj);
    $tahun          = date('Y', $tanggal_obj);
    $minggu_ke      = ceil($tanggal_hari / 7);
    $mulai_format   = date('d M', strtotime($mulai));
    $selesai_format = date('d M', strtotime($selesai));
    $nama_bulan     = bulanIndo($bulan);
    $nama = "Minggu ke-$minggu_ke $nama_bulan $tahun ($mulai_format - $selesai_format)";
 
    $insert = mysqli_query($koneksi_db, "
        INSERT INTO tb_periode 
        (nama_periode, minggu_ke, tahun, tanggal_mulai, tanggal_selesai, status, target)
        VALUES ('$nama', '$minggu_ke', '$tahun', '$mulai', '$selesai', 'aktif', 10000)
    ");
    if (!$insert) die(mysqli_error($koneksi_db));
 
    return ['id_periode' => mysqli_insert_id($koneksi_db), 'target' => 10000];
}
 
$today        = date('Y-m-d');
$periodeAktif = getPeriode($koneksi_db, $today);
$id_periode   = $periodeAktif['id_periode'];
$target_default = $periodeAktif['target'] ?? 10000;
 
$data = mysqli_query($koneksi_db, "
    SELECT 
        s.id_siswa,
        s.nama_siswa,
        COALESCE(SUM(tr.jumlah),0) as dibayar
    FROM tb_siswa s
    LEFT JOIN tb_transaksi tr 
        ON s.id_siswa = tr.id_siswa 
        AND tr.jenis = 'bayar'
        AND tr.id_periode = '$id_periode'
    GROUP BY s.id_siswa
");
if (!$data) die(mysqli_error($koneksi_db));
 
$lunas = 0; $sebagian = 0; $belum = 0;
$rows  = [];
 
while ($row = mysqli_fetch_assoc($data)) {
    $target  = $target_default;
    $dibayar = $row['dibayar'];
 
    if ($dibayar >= $target && $target > 0) {
        $row['status'] = 'lunas';    $lunas++;
    } elseif ($dibayar > 0) {
        $row['status'] = 'sebagian'; $sebagian++;
    } else {
        $row['status'] = 'belum';    $belum++;
    }
    $row['target'] = $target;
    $rows[] = $row;
}
 
// ── SIMPAN PEMBAYARAN ──
$id_user = 1;
if (isset($_POST['simpan'])) {
    $id_siswa = $_POST['id_siswa'] ?? '';
    $jumlah   = $_POST['jumlah']   ?? '';
    $tanggal  = $_POST['tanggal']  ?? '';
 
    if ($id_siswa == '' || $jumlah == '' || $tanggal == '') {
        echo "<script>alert('Data belum lengkap!');</script>";
    } else {
        $id_siswa = mysqli_real_escape_string($koneksi_db, $id_siswa);
        $jumlah   = (int)$jumlah;
        $tanggal  = mysqli_real_escape_string($koneksi_db, $tanggal);
        $hari     = date('N', strtotime($tanggal));
 
        if ($hari > 5) {
            echo "<script>alert('Pembayaran hanya boleh hari Senin - Jumat!');</script>";
        } else {
            $periodeBaru      = getPeriode($koneksi_db, $tanggal);
            $id_periode_input = $periodeBaru['id_periode'];
 
            $simpan = mysqli_query($koneksi_db, "
                INSERT INTO tb_transaksi 
                (id_siswa, id_user, id_periode, tanggal, jenis, jumlah, keterangan)
                VALUES ('$id_siswa','$id_user','$id_periode_input','$tanggal','bayar','$jumlah','Iuran Kas')
            ");
 
            if ($simpan) {
                echo "<script>alert('Pembayaran berhasil!'); window.location='status.php';</script>";
            } else {
                die(mysqli_error($koneksi_db));
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Status Pembayaran - Kas Kelas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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

        /* ══ CUSTOM ══ */
        .siswa-item { cursor: pointer; transition: background .15s; }
        .siswa-item:hover { background: #e9f2ff; }
        #opsiJumlah button { border-radius: 10px; flex: 1; }

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
            <a href="status.php" class="nav-link active" data-tip="Status Pembayaran">
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
            <a href="laporan.php" class="nav-link" data-tip="Laporan">
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
        <h4 class="fw-bold mb-1">Status Pembayaran Kas Kelas</h4>
        <p class="text-muted small mb-0">Pantau pembayaran uang kas siswa</p>
    </div>
 
    <!-- BUTTON INPUT -->
    <div class="mb-4">
        <button class="btn btn-primary rounded-3 py-2 px-4"
                data-bs-toggle="modal" data-bs-target="#modalBayar">
            <i class="fa-solid fa-dollar-sign me-2"></i>Input Pembayaran Kas
        </button>
    </div>
 
    <!-- SUMMARY CARD -->
    <div class="row g-3 mb-4">
        <div class="col-4">
            <div class="card border-0 rounded-4 text-center p-3" style="background:#d1fae5;">
                <p class="text-success small fw-semibold mb-1">Lunas</p>
                <h3 class="text-success fw-bold mb-0"><?= $lunas ?></h3>
            </div>
        </div>
        <div class="col-4">
            <div class="card border-0 rounded-4 text-center p-3" style="background:#fef9c3;">
                <p class="text-warning small fw-semibold mb-1">Sebagian</p>
                <h3 class="text-warning fw-bold mb-0"><?= $sebagian ?></h3>
            </div>
        </div>
        <div class="col-4">
            <div class="card border-0 rounded-4 text-center p-3" style="background:#fee2e2;">
                <p class="text-danger small fw-semibold mb-1">Belum</p>
                <h3 class="text-danger fw-bold mb-0"><?= $belum ?></h3>
            </div>
        </div>
    </div>
 
    <!-- STATUS LIST -->
    <div class="card border rounded-4">
        <div class="card-body p-4">
            <h6 class="fw-semibold mb-4">Status Pembayaran Kas</h6>
 
            <?php foreach ($rows as $row):
                $target     = $row['target'] ?? 10000;
                $dibayar    = $row['dibayar'];
                $persen     = $target > 0 ? min(($dibayar / $target) * 100, 100) : 0;
                $kekurangan = $target - $dibayar;
 
                if ($row['status'] == 'lunas')         { $warna = 'success'; $label = 'Lunas';       $persen = 100; }
                elseif ($row['status'] == 'sebagian')  { $warna = 'warning'; $label = 'Sebagian'; }
                else                                   { $warna = 'danger';  $label = 'Belum Bayar'; }
            ?>
            <div class="card border rounded-4 mb-3">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex gap-3 align-items-center">
                            <div class="bg-<?= $warna ?> bg-opacity-10 text-<?= $warna ?> rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:42px; height:42px;">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <div>
                                <p class="fw-semibold small mb-0"><?= htmlspecialchars($row['nama_siswa']) ?></p>
                                <small class="text-muted">NISN: <?= htmlspecialchars($row['id_siswa']) ?></small>
                            </div>
                        </div>
                        <span class="badge bg-<?= $warna ?> bg-opacity-10 text-<?= $warna ?> rounded-pill px-3" style="font-size:11px;">
                            <?= $label ?>
                        </span>
                    </div>
 
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Dibayar: Rp <?= number_format($dibayar, 0, ',', '.') ?></span>
                        <span>Target: Rp <?= number_format($target, 0, ',', '.') ?></span>
                    </div>
 
                    <div class="progress rounded-pill" style="height:7px;">
                        <div class="progress-bar bg-<?= $warna ?> rounded-pill"
                             style="width:<?= $persen ?>%"></div>
                    </div>
 
                    <div class="d-flex justify-content-between small mt-1">
                        <span class="text-muted"><?= round($persen) ?>% terbayar</span>
                        <?php if ($row['status'] == 'sebagian'): ?>
                        <span class="text-warning fw-medium">
                            Kurang Rp <?= number_format($kekurangan, 0, ',', '.') ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
 
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
    <a href="status.php" class="bn-item active">
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
    <a href="laporan.php" class="bn-item">
        <i class="fa-regular fa-file-lines"></i>
        <span>Laporan</span>
    </a>
    <a href="logout.php" onclick="return confirm('Yakin ingin logout?')" class="bn-item">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Keluar</span>
    </a>
</div>
 
<!-- ══ MODAL INPUT PEMBAYARAN ══ -->
<div class="modal fade" id="modalBayar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <form method="POST" action="proses_transaksi.php">
                <div class="modal-header border-0">
                    <div>
                        <h5 class="modal-title fw-semibold mb-0">Input Pembayaran Kas Siswa</h5>
                        <small class="text-muted">Catat pembayaran iuran kas dari siswa</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
 
                <div class="modal-body pt-0">
 
                    <!-- PILIH SISWA -->
                    <div class="mb-3 position-relative">
                        <label class="form-label">Pilih Siswa</label>
                        <div class="form-control d-flex justify-content-between align-items-center rounded-3"
                             id="dropdownBtn" style="cursor:pointer;">
                            <span id="selectedText">Pilih siswa...</span>
                            <i class="fa-solid fa-chevron-down text-muted"></i>
                        </div>
 
                        <!-- Dropdown list -->
                        <div id="dropdownList" class="card border rounded-3 shadow-sm mt-1 d-none position-absolute w-100" style="z-index:999; max-height:200px; overflow-y:auto;">
                            <div class="list-group list-group-flush">
                                <?php
                                $siswa = mysqli_query($koneksi_db, "SELECT * FROM tb_siswa ORDER BY nama_siswa ASC");
                                while ($s = mysqli_fetch_assoc($siswa)) : ?>
                                <div class="list-group-item siswa-item px-3 py-2"
                                     data-id="<?= $s['id_siswa'] ?>"
                                     data-nama="<?= htmlspecialchars($s['nama_siswa']) ?>">
                                    <p class="fw-semibold small mb-0"><?= htmlspecialchars($s['nama_siswa']) ?></p>
                                    <small class="text-muted">NIS: <?= $s['id_siswa'] ?> • Rp 10.000/minggu</small>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
 
                        <!-- Info iuran -->
                        <div id="infoIuran" class="mt-2 d-none">
                            <div class="form-control d-flex justify-content-between bg-primary bg-opacity-10 text-primary rounded-3">
                                <span>Iuran Mingguan:</span>
                                <strong>Rp 10.000</strong>
                            </div>
                        </div>
 
                        <!-- Alert tunggakan -->
                        <div id="alertTunggakan" class="alert alert-primary rounded-3 mt-2 d-none">
                            <strong>Tunggakan:</strong>
                            <div id="isiTunggakan"></div>
                        </div>
 
                        <input type="hidden" name="id_siswa" id="id_siswa" required>
                        <input type="hidden" name="jenis" value="bayar">
                        <input type="hidden" name="keterangan" value="Iuran Kas">
                    </div>
 
                    <!-- JUMLAH -->
                    <div class="mb-2">
                        <label class="form-label">Jumlah Pembayaran (Rp)</label>
                        <input type="number" name="jumlah" id="inputJumlah" class="form-control rounded-3" required>
                    </div>
 
                    <div id="opsiJumlah" class="d-flex gap-2 d-none mb-3">
                        <button type="button" class="btn btn-outline-secondary btn-sm pilih-jumlah" data-value="10000">Penuh</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm pilih-jumlah" data-value="5000">Setengah</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm pilih-jumlah" data-value="2000">2.000</button>
                    </div>
 
                    <!-- TANGGAL -->
                    <div class="mb-3">
                        <label class="form-label">Tanggal Pembayaran</label>
                        <input type="date" name="tanggal" class="form-control rounded-3"
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
 
                    <button type="submit" name="simpan" class="btn btn-primary w-100 rounded-3">
                        Simpan Pembayaran
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
 
<!-- ══ MODAL TAMBAH TRANSAKSI ══ -->
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
 
    /* ── MODAL BAYAR ── */
    const btn          = document.getElementById('dropdownBtn');
    const list         = document.getElementById('dropdownList');
    const text         = document.getElementById('selectedText');
    const inputId      = document.getElementById('id_siswa');
    const infoIuran    = document.getElementById('infoIuran');
    const opsiJumlah   = document.getElementById('opsiJumlah');
    const inputJumlah  = document.getElementById('inputJumlah');
    const alertBox     = document.getElementById('alertTunggakan');
    const isiTunggakan = document.getElementById('isiTunggakan');
    const modalBayar   = document.getElementById('modalBayar');
 
    function resetModal() {
        text.innerText    = "Pilih siswa...";
        inputId.value     = "";
        inputJumlah.value = "";
        infoIuran.classList.add('d-none');
        opsiJumlah.classList.add('d-none');
        alertBox.classList.add('d-none');
        isiTunggakan.innerHTML = "";
        document.querySelectorAll('.pilih-jumlah').forEach(b => {
            b.classList.remove('btn-primary');
            b.classList.add('btn-outline-secondary');
        });
    }
 
    btn.addEventListener('click', () => {
        list.classList.toggle('d-none');
    });
 
    document.addEventListener('click', e => {
        if (!btn.contains(e.target) && !list.contains(e.target)) {
            list.classList.add('d-none');
        }
    });
 
    document.querySelectorAll('.siswa-item').forEach(item => {
        item.addEventListener('click', () => {
            text.innerText = item.dataset.nama;
            inputId.value  = item.dataset.id;
            list.classList.add('d-none');
            infoIuran.classList.remove('d-none');
            opsiJumlah.classList.remove('d-none');
 
            fetch('get_tunggakan.php?id_siswa=' + item.dataset.id)
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        if (res.total > 0) {
                            let html = `Total: <strong>Rp ${res.total.toLocaleString('id-ID')}</strong><br>
                                        <strong>Mohon Lunaskan Tunggakan Terlebih Dahulu</strong><br><small>`;
                            res.detail.forEach(d => {
                                html += `• ${d.periode} : Rp ${d.kurang.toLocaleString('id-ID')}<br>`;
                            });
                            html += `</small>`;
                            isiTunggakan.innerHTML = html;
                        } else {
                            isiTunggakan.innerHTML = "Tidak ada tunggakan 🎉";
                        }
                        alertBox.classList.remove('d-none');
                    }
                });
        });
    });
 
    document.querySelectorAll('.pilih-jumlah').forEach(button => {
        button.addEventListener('click', function () {
            inputJumlah.value = this.dataset.value;
            document.querySelectorAll('.pilih-jumlah').forEach(b => {
                b.classList.remove('btn-primary');
                b.classList.add('btn-outline-secondary');
            });
            this.classList.remove('btn-outline-secondary');
            this.classList.add('btn-primary');
        });
    });
 
    modalBayar.addEventListener('hidden.bs.modal', resetModal);
</script>
</body>
</html>