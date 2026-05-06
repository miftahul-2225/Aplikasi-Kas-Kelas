<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

// CEK LOGIN
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../login.php");
    exit();
}

$id_siswa = $_SESSION['id_siswa'];

// ── AMBIL DATA PROFIL SISWA ──
$stmt = mysqli_prepare($koneksi_db, "SELECT * FROM tb_siswa WHERE id_siswa = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $id_siswa);
mysqli_stmt_execute($stmt);
$siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Jika data siswa tidak ditemukan
if (!$siswa) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// ── AMBIL DATA TAGIHAN ──
$stmt2 = mysqli_prepare($koneksi_db, "
    SELECT t.*, p.nama_periode, p.jumlah_tagihan as nominal_periode
    FROM tb_tagihan t
    LEFT JOIN tb_periode p ON t.id_periode = p.id_periode
    WHERE t.id_siswa = ?
    ORDER BY t.id_tagihan DESC
");
mysqli_stmt_bind_param($stmt2, "s", $id_siswa);
mysqli_stmt_execute($stmt2);
$result_tagihan = mysqli_stmt_get_result($stmt2);

// ── HITUNG RINGKASAN TAGIHAN ──
$total_tagihan  = 0;
$total_lunas    = 0;
$total_belum    = 0;
$total_setengah = 0;
$tagihan_list   = [];

while ($row = mysqli_fetch_assoc($result_tagihan)) {
    $tagihan_list[] = $row;
    $total_tagihan += $row['jumlah_tagihan'];
    if ($row['status'] === 'lunas')             $total_lunas    += $row['jumlah_tagihan'];
    elseif ($row['status'] === 'belum bayar')   $total_belum    += $row['jumlah_tagihan'];
    elseif ($row['status'] === 'setengah bayar')$total_setengah += $row['jumlah_tagihan'];
}

// ── AMBIL RIWAYAT TRANSAKSI ──
$stmt3 = mysqli_prepare($koneksi_db, "
    SELECT tr.*, p.nama_periode
    FROM tb_transaksi tr
    LEFT JOIN tb_periode p ON tr.id_periode = p.id_periode
    WHERE tr.id_siswa = ?
    ORDER BY tr.tanggal DESC, tr.id_transaksi DESC
    LIMIT 10
");
mysqli_stmt_bind_param($stmt3, "s", $id_siswa);
mysqli_stmt_execute($stmt3);
$result_transaksi = mysqli_stmt_get_result($stmt3);

$transaksi_list = [];
while ($row = mysqli_fetch_assoc($result_transaksi)) {
    $transaksi_list[] = $row;
}

// ── FORMAT RUPIAH ──
function rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Siswa – E Kas Seven</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body { background-color: #f0f4f8; min-height: 100vh; }

        /* ── NAVBAR ── */
        .navbar-kas {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            padding: 14px 24px;
        }
        .navbar-kas .brand {
            font-size: 1.1rem; font-weight: 700; color: #fff;
            display: flex; align-items: center; gap: 8px;
        }
        .navbar-kas .user-info {
            font-size: .85rem; color: rgba(255,255,255,.85);
            display: flex; align-items: center; gap: 8px;
        }
        .navbar-kas .user-info .avatar {
            width: 34px; height: 34px; border-radius: 50%;
            background: rgba(255,255,255,.25);
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; font-weight: 700; color: #fff; flex-shrink: 0;
        }

        /* ── STAT CARDS ── */
        .stat-card {
            border-radius: .875rem; border: none;
            padding: 20px 22px; display: flex; align-items: center; gap: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,.07);
        }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }
        .stat-label { font-size: .75rem; color: #6c757d; margin-bottom: 2px; }
        .stat-value { font-size: 1.1rem; font-weight: 700; line-height: 1.2; }

        /* ── SECTION CARDS ── */
        .section-card { border-radius: .875rem; border: none; box-shadow: 0 2px 12px rgba(0,0,0,.07); }
        .section-card .card-header {
            background: #fff; border-bottom: 1px solid #e9ecef;
            border-radius: .875rem .875rem 0 0 !important;
            padding: 16px 20px; font-weight: 600; font-size: .95rem;
            display: flex; align-items: center; gap: 8px;
        }

        /* ── PROFIL ── */
        .profil-avatar {
            width: 64px; height: 64px; border-radius: 50%;
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; color: #fff; font-weight: 700; flex-shrink: 0;
        }
        .profil-detail { font-size: .85rem; }
        .profil-detail .label { color: #6c757d; font-size: .78rem; }
        .profil-detail .value { font-weight: 500; }

        /* ── BADGE STATUS ── */
        .badge-lunas    { background: #d1fae5; color: #065f46; }
        .badge-belum    { background: #fee2e2; color: #991b1b; }
        .badge-setengah { background: #fef9c3; color: #854d0e; }

        /* ── TABLE ── */
        .table-kas th {
            font-size: .78rem; text-transform: uppercase;
            letter-spacing: .04em; color: #6c757d;
            font-weight: 600; background: #f8f9fa;
        }
        .table-kas td { font-size: .875rem; vertical-align: middle; }

        /* ── EMPTY STATE ── */
        .empty-state { text-align: center; padding: 40px 20px; color: #adb5bd; }
        .empty-state i { font-size: 40px; margin-bottom: 10px; display: block; }

        /* ── LOGOUT BTN ── */
        .btn-logout {
            background: transparent; border: 1px solid rgba(255,255,255,.4);
            color: #fff; font-size: .82rem; padding: 6px 14px;
            border-radius: 8px; transition: background .2s; text-decoration: none;
        }
        .btn-logout:hover { background: rgba(255,255,255,.15); color: #fff; }
    </style>
</head>
<body>

<!-- ── NAVBAR ── -->
<nav class="navbar-kas d-flex justify-content-between align-items-center">
    <div class="brand">
        <i class="bi bi-shield-lock"></i> E Kas Seven
    </div>
    <div class="d-flex align-items-center gap-3">
        <div class="user-info">
            <div class="avatar"><?= strtoupper(substr($siswa['nama_siswa'], 0, 1)) ?></div>
            <div>
                <div style="font-weight:600;color:#fff;font-size:.88rem;">
                    <?= htmlspecialchars($siswa['nama_siswa']) ?>
                </div>
                <div style="font-size:.75rem;opacity:.8;">
                    <?= htmlspecialchars($siswa['kelas']) ?>
                </div>
            </div>
        </div>
        <a href="../logout.php" class="btn-logout">
            <i class="bi bi-box-arrow-right me-1"></i> Keluar
        </a>
    </div>
</nav>

<!-- ── KONTEN ── -->
<div class="container py-4">

    <!-- ── STAT CARDS ── -->
    <div class="row g-3 mb-4">

        <div class="col-6 col-md-3">
            <div class="stat-card bg-white">
                <div class="stat-icon" style="background:#eff6ff;">
                    <i class="bi bi-receipt" style="color:#0d6efd;"></i>
                </div>
                <div>
                    <div class="stat-label">Total Tagihan</div>
                    <div class="stat-value" style="color:#0d6efd;"><?= rupiah($total_tagihan) ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card bg-white">
                <div class="stat-icon" style="background:#d1fae5;">
                    <i class="bi bi-check-circle" style="color:#059669;"></i>
                </div>
                <div>
                    <div class="stat-label">Sudah Lunas</div>
                    <div class="stat-value" style="color:#059669;"><?= rupiah($total_lunas) ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card bg-white">
                <div class="stat-icon" style="background:#fee2e2;">
                    <i class="bi bi-exclamation-circle" style="color:#dc2626;"></i>
                </div>
                <div>
                    <div class="stat-label">Belum Bayar</div>
                    <div class="stat-value" style="color:#dc2626;"><?= rupiah($total_belum) ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card bg-white">
                <div class="stat-icon" style="background:#fef9c3;">
                    <i class="bi bi-hourglass-split" style="color:#d97706;"></i>
                </div>
                <div>
                    <div class="stat-label">Setengah Bayar</div>
                    <div class="stat-value" style="color:#d97706;"><?= rupiah($total_setengah) ?></div>
                </div>
            </div>
        </div>

    </div>

    <div class="row g-3">

        <!-- KOLOM KIRI: Profil -->
        <div class="col-lg-5">
            <div class="card section-card mb-3">
                <div class="card-header">
                    <i class="bi bi-person-circle text-primary"></i> Profil Siswa
                </div>
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="profil-avatar">
                            <?= strtoupper(substr($siswa['nama_siswa'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="fw-semibold" style="font-size:.95rem;">
                                <?= htmlspecialchars($siswa['nama_siswa']) ?>
                            </div>
                            <div class="text-muted" style="font-size:.8rem;">
                                <?= htmlspecialchars($siswa['kelas']) ?>
                            </div>
                            <span class="badge rounded-pill mt-1"
                                style="background:#d1fae5;color:#065f46;font-size:.72rem;">
                                <i class="bi bi-circle-fill me-1" style="font-size:.5rem;"></i>
                                <?= htmlspecialchars($siswa['status']) ?>
                            </span>
                        </div>
                    </div>
                    <hr class="my-2">
                    <div class="profil-detail">
                        <div class="mb-2">
                            <div class="label">ID Siswa</div>
                            <div class="value"><?= htmlspecialchars($siswa['id_siswa']) ?></div>
                        </div>
                        <div>
                            <div class="label">Alamat</div>
                            <div class="value"><?= htmlspecialchars($siswa['alamat']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KOLOM KANAN: Tagihan & Transaksi -->
        <div class="col-lg-7">

            <!-- DAFTAR TAGIHAN -->
            <div class="card section-card mb-3">
                <div class="card-header">
                    <i class="bi bi-receipt text-primary"></i> Daftar Tagihan Kas
                </div>
                <div class="card-body p-0">
                    <?php if (count($tagihan_list) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-kas table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3">Periode</th>
                                    <th>Jumlah</th>
                                    <th>Tgl Bayar</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($tagihan_list as $t): ?>
                                <?php
                                $st  = $t['status'];
                                $cls = match($st) {
                                    'lunas'          => 'badge-lunas',
                                    'belum bayar'    => 'badge-belum',
                                    'setengah bayar' => 'badge-setengah',
                                    default          => 'bg-secondary text-white'
                                };
                                $icon = match($st) {
                                    'lunas'          => 'bi-check-circle-fill',
                                    'belum bayar'    => 'bi-x-circle-fill',
                                    'setengah bayar' => 'bi-hourglass-split',
                                    default          => 'bi-question-circle'
                                };
                                ?>
                                <tr>
                                    <td class="ps-3"><?= htmlspecialchars($t['nama_periode'] ?? '-') ?></td>
                                    <td><?= rupiah($t['jumlah_tagihan']) ?></td>
                                    <td>
                                        <?= $t['tanggal_bayar']
                                            ? date('d/m/Y', strtotime($t['tanggal_bayar']))
                                            : '<span class="text-muted">-</span>' ?>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill <?= $cls ?>" style="font-size:.72rem;">
                                            <i class="bi <?= $icon ?> me-1"></i>
                                            <?= ucfirst($st) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        Belum ada tagihan
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIWAYAT TRANSAKSI -->
            <div class="card section-card">
                <div class="card-header">
                    <i class="bi bi-clock-history text-primary"></i> Riwayat Transaksi
                    <span class="badge bg-primary rounded-pill ms-1" style="font-size:.7rem;">
                        <?= count($transaksi_list) ?>
                    </span>
                </div>
                <div class="card-body p-0">
                    <?php if (count($transaksi_list) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-kas table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3">Tanggal</th>
                                    <th>Periode</th>
                                    <th>Jumlah</th>
                                    <th>Jenis</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($transaksi_list as $tr): ?>
                                <tr>
                                    <td class="ps-3"><?= date('d/m/Y', strtotime($tr['tanggal'])) ?></td>
                                    <td><?= htmlspecialchars($tr['nama_periode'] ?? '-') ?></td>
                                    <td><?= rupiah($tr['jumlah']) ?></td>
                                    <td>
                                        <?php if ($tr['jenis'] === 'bayar'): ?>
                                            <span class="badge rounded-pill badge-lunas" style="font-size:.72rem;">
                                                <i class="bi bi-arrow-down-circle-fill me-1"></i> Bayar
                                            </span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill"
                                                style="background:#ede9fe;color:#5b21b6;font-size:.72rem;">
                                                <i class="bi bi-arrow-up-circle-fill me-1"></i> Pengeluaran
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-clock-history"></i>
                        Belum ada riwayat transaksi
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <div class="text-center text-muted mt-4" style="font-size:.78rem;">
        © <?= date('Y') ?> Sistem Kas Kelas · E Kas Seven
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>