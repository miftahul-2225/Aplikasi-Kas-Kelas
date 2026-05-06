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
if (!$stmt) die("Error profil: " . mysqli_error($koneksi_db));
mysqli_stmt_bind_param($stmt, "s", $id_siswa);
mysqli_stmt_execute($stmt);
$siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$siswa) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// ── AMBIL RIWAYAT TRANSAKSI SISWA ──
// Kolom tb_transaksi: id_transaksi, id_siswa, tanggal, jenis, jumlah, keterangan, id_user, id_periode
// Kolom tb_periode  : id_periode, nama_periode, minggu_ke, tahun, tanggal_mulai, tanggal_selesai, status, target
$stmt2 = mysqli_prepare($koneksi_db, "
    SELECT tr.id_transaksi, tr.tanggal, tr.jenis, tr.jumlah, tr.keterangan,
           p.nama_periode, p.minggu_ke, p.tahun
    FROM tb_transaksi tr
    LEFT JOIN tb_periode p ON tr.id_periode = p.id_periode
    WHERE tr.id_siswa = ?
    ORDER BY tr.tanggal DESC, tr.id_transaksi DESC
");
if (!$stmt2) die("Error transaksi: " . mysqli_error($koneksi_db));
mysqli_stmt_bind_param($stmt2, "s", $id_siswa);
mysqli_stmt_execute($stmt2);
$result_transaksi = mysqli_stmt_get_result($stmt2);

$transaksi_list = [];
$total_bayar    = 0;
$total_minggu   = 0; // jumlah periode/minggu yang sudah dibayar

while ($row = mysqli_fetch_assoc($result_transaksi)) {
    $transaksi_list[] = $row;
    if ($row['jenis'] === 'bayar') {
        $total_bayar  += $row['jumlah'];
        $total_minggu++;
    }
}

// ── AMBIL TARGET KAS PER MINGGU (dari periode aktif) ──
$q_target = mysqli_query($koneksi_db, "SELECT target FROM tb_periode WHERE status = 'aktif' LIMIT 1");
$target_per_minggu = 0;
if ($q_target && $row_t = mysqli_fetch_assoc($q_target)) {
    $target_per_minggu = $row_t['target'];
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

        /* NAVBAR */
        .navbar-kas {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            padding: 14px 24px;
        }
        .navbar-kas .brand {
            font-size: 1.1rem; font-weight: 700; color: #fff;
            display: flex; align-items: center; gap: 8px;
        }
        .navbar-kas .user-info {
            display: flex; align-items: center; gap: 8px;
        }
        .navbar-kas .avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: rgba(255,255,255,.25);
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; font-weight: 700; color: #fff;
        }

        /* STAT CARDS */
        .stat-card {
            border-radius: 1rem; border: none;
            padding: 18px 20px; display: flex; align-items: center; gap: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,.07); height: 100%;
        }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }
        .stat-label { font-size: .74rem; color: #6c757d; margin-bottom: 2px; }
        .stat-value { font-size: 1.05rem; font-weight: 700; line-height: 1.2; }

        /* SECTION CARDS */
        .section-card {
            border-radius: 1rem; border: none;
            box-shadow: 0 2px 12px rgba(0,0,0,.07);
        }
        .section-card .card-header {
            background: #fff; border-bottom: 1px solid #e9ecef;
            border-radius: 1rem 1rem 0 0 !important;
            padding: 14px 18px; font-weight: 600; font-size: .9rem;
            display: flex; align-items: center; gap: 8px;
        }

        /* PROFIL */
        .profil-avatar {
            width: 60px; height: 60px; border-radius: 50%;
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            display: flex; align-items: center; justify-content: center;
            font-size: 26px; color: #fff; font-weight: 700;
        }
        .info-label { font-size: .75rem; color: #6c757d; }
        .info-value { font-size: .88rem; font-weight: 500; }

        /* TABLE */
        .table-kas th {
            font-size: .74rem; text-transform: uppercase;
            letter-spacing: .04em; color: #6c757d;
            font-weight: 600; background: #f8f9fa; white-space: nowrap;
        }
        .table-kas td { font-size: .855rem; vertical-align: middle; }

        /* BADGE */
        .badge-bayar     { background: #d1fae5; color: #065f46; }
        .badge-pengeluaran { background: #ede9fe; color: #5b21b6; }

        /* EMPTY */
        .empty-state { text-align: center; padding: 36px 20px; color: #adb5bd; }
        .empty-state i { font-size: 36px; margin-bottom: 8px; display: block; }

        /* LOGOUT */
        .btn-logout {
            background: transparent; border: 1px solid rgba(255,255,255,.4);
            color: #fff; font-size: .82rem; padding: 5px 13px;
            border-radius: 8px; text-decoration: none; transition: background .2s;
        }
        .btn-logout:hover { background: rgba(255,255,255,.15); color: #fff; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar-kas d-flex justify-content-between align-items-center">
    <div class="brand">
        <i class="bi bi-shield-lock"></i> E Kas Seven
    </div>
    <div class="d-flex align-items-center gap-3">
        <div class="user-info">
            <div class="avatar"><?= strtoupper(substr($siswa['nama_siswa'], 0, 1)) ?></div>
            <div>
                <div style="font-weight:600;color:#fff;font-size:.88rem; line-height:1.2;">
                    <?= htmlspecialchars($siswa['nama_siswa']) ?>
                </div>
                <div style="font-size:.74rem;color:rgba(255,255,255,.75);">
                    <?= htmlspecialchars($siswa['kelas']) ?>
                </div>
            </div>
        </div>
        <a href="../logout.php" class="btn-logout">
            <i class="bi bi-box-arrow-right me-1"></i> Keluar
        </a>
    </div>
</nav>

<div class="container py-4">

    <!-- STAT CARDS -->
    <div class="row g-3 mb-4">

        <div class="col-6 col-md-3">
            <div class="stat-card bg-white">
                <div class="stat-icon" style="background:#eff6ff;">
                    <i class="bi bi-cash-coin" style="color:#0d6efd;"></i>
                </div>
                <div>
                    <div class="stat-label">Total Dibayar</div>
                    <div class="stat-value" style="color:#0d6efd;"><?= rupiah($total_bayar) ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card bg-white">
                <div class="stat-icon" style="background:#d1fae5;">
                    <i class="bi bi-calendar-check" style="color:#059669;"></i>
                </div>
                <div>
                    <div class="stat-label">Minggu Dibayar</div>
                    <div class="stat-value" style="color:#059669;"><?= $total_minggu ?> minggu</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card bg-white">
                <div class="stat-icon" style="background:#fef9c3;">
                    <i class="bi bi-wallet2" style="color:#d97706;"></i>
                </div>
                <div>
                    <div class="stat-label">Kas/Minggu</div>
                    <div class="stat-value" style="color:#d97706;"><?= rupiah($target_per_minggu) ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card bg-white">
                <div class="stat-icon" style="background:#fce7f3;">
                    <i class="bi bi-receipt-cutoff" style="color:#db2777;"></i>
                </div>
                <div>
                    <div class="stat-label">Total Transaksi</div>
                    <div class="stat-value" style="color:#db2777;"><?= count($transaksi_list) ?> transaksi</div>
                </div>
            </div>
        </div>

    </div>

    <div class="row g-3">

        <!-- KOLOM KIRI: Profil -->
        <div class="col-lg-4">
            <div class="card section-card">
                <div class="card-header">
                    <i class="bi bi-person-circle text-primary"></i> Profil Siswa
                </div>
                <div class="card-body p-3">

                    <!-- Avatar + Nama -->
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="profil-avatar">
                            <?= strtoupper(substr($siswa['nama_siswa'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="fw-semibold" style="font-size:.92rem; line-height:1.3;">
                                <?= htmlspecialchars($siswa['nama_siswa']) ?>
                            </div>
                            <div class="text-muted" style="font-size:.78rem;">
                                <?= htmlspecialchars($siswa['kelas']) ?>
                            </div>
                            <span class="badge rounded-pill mt-1"
                                style="background:#d1fae5;color:#065f46;font-size:.7rem;">
                                <i class="bi bi-circle-fill me-1" style="font-size:.45rem;"></i>
                                <?= ucfirst(htmlspecialchars($siswa['status'])) ?>
                            </span>
                        </div>
                    </div>

                    <hr class="my-2">

                    <div class="mb-2">
                        <div class="info-label">ID Siswa</div>
                        <div class="info-value"><?= htmlspecialchars($siswa['id_siswa']) ?></div>
                    </div>
                    <div>
                        <div class="info-label">Alamat</div>
                        <div class="info-value"><?= htmlspecialchars($siswa['alamat']) ?></div>
                    </div>

                    <hr class="my-3">

                    <!-- Info login -->
                    <div class="rounded-3 p-2" style="background:#eff6ff;font-size:.76rem;color:#1d4ed8;">
                        <i class="bi bi-info-circle me-1"></i>
                        Password login Anda adalah <strong>ID Siswa</strong> Anda.
                    </div>

                </div>
            </div>
        </div>

        <!-- KOLOM KANAN: Riwayat Transaksi -->
        <div class="col-lg-8">
            <div class="card section-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>
                        <i class="bi bi-clock-history text-primary"></i> Riwayat Transaksi Kas
                    </span>
                    <span class="badge bg-primary rounded-pill" style="font-size:.7rem;">
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
                                    <th>Keterangan</th>
                                    <th>Jumlah</th>
                                    <th>Jenis</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($transaksi_list as $tr): ?>
                                <tr>
                                    <td class="ps-3" style="white-space:nowrap;">
                                        <?= date('d/m/Y', strtotime($tr['tanggal'])) ?>
                                    </td>
                                    <td style="white-space:nowrap;">
                                        <?php if (!empty($tr['nama_periode'])): ?>
                                            <span style="font-size:.78rem;">
                                                <?= htmlspecialchars($tr['nama_periode']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($tr['keterangan'] ?? '-') ?></td>
                                    <td style="white-space:nowrap; font-weight:500;">
                                        <?= rupiah($tr['jumlah']) ?>
                                    </td>
                                    <td>
                                        <?php if ($tr['jenis'] === 'bayar'): ?>
                                            <span class="badge rounded-pill badge-bayar" style="font-size:.7rem;">
                                                <i class="bi bi-arrow-down-circle-fill me-1"></i>Bayar
                                            </span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill badge-pengeluaran" style="font-size:.7rem;">
                                                <i class="bi bi-arrow-up-circle-fill me-1"></i>Pengeluaran
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
                        <div>Belum ada riwayat transaksi</div>
                        <div style="font-size:.8rem;" class="mt-1">
                            Transaksi kas Anda akan muncul di sini
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>