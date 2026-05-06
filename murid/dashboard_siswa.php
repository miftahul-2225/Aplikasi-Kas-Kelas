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
$total_minggu   = 0;

while ($row = mysqli_fetch_assoc($result_transaksi)) {
    $transaksi_list[] = $row;
    if ($row['jenis'] === 'bayar') {
        $total_bayar  += $row['jumlah'];
        $total_minggu++;
    }
}

// ── TARGET KAS PER MINGGU ──
$q_target = mysqli_query($koneksi_db, "SELECT target FROM tb_periode WHERE status = 'aktif' LIMIT 1");
$target_per_minggu = 0;
if ($q_target && $row_t = mysqli_fetch_assoc($q_target)) {
    $target_per_minggu = $row_t['target'];
}

function rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

$initial = strtoupper(substr($siswa['nama_siswa'], 0, 1));
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
        :root {
            --sidebar-w: 240px;
            --topbar-h:  64px;
            --blue:      #0d6efd;
            --blue-dark: #0b5ed7;
        }

        * { box-sizing: border-box; }
        body { margin: 0; background: #f0f4f8; font-family: 'Segoe UI', sans-serif; }

        /* ══════════════════════════════
           SIDEBAR
        ══════════════════════════════ */
        .sidebar {
            position: fixed;
            top: 0; left: 0;
            width: var(--sidebar-w);
            height: 100vh;
            background: #fff;
            border-right: 1px solid #e9ecef;
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: transform .3s ease;
        }

        .sidebar-brand {
            height: var(--topbar-h);
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 20px;
            border-bottom: 1px solid #e9ecef;
            text-decoration: none;
        }

        .sidebar-brand .brand-icon {
            width: 36px; height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--blue), var(--blue-dark));
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 18px; flex-shrink: 0;
        }

        .sidebar-brand .brand-name {
            font-size: .95rem;
            font-weight: 700;
            color: #1e293b;
        }

        .sidebar-nav {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
        }

        .nav-label {
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #94a3b8;
            padding: 0 8px;
            margin: 12px 0 6px;
        }

        .nav-item-kas {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 8px;
            font-size: .875rem;
            color: #475569;
            text-decoration: none;
            font-weight: 500;
            transition: background .15s, color .15s;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }

        .nav-item-kas:hover {
            background: #f1f5f9;
            color: var(--blue);
        }

        .nav-item-kas.active {
            background: #eff6ff;
            color: var(--blue);
            font-weight: 600;
        }

        .nav-item-kas i {
            font-size: 16px;
            width: 20px;
            text-align: center;
            flex-shrink: 0;
        }

        .sidebar-footer {
            padding: 12px;
            border-top: 1px solid #e9ecef;
        }

        .btn-logout-side {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 8px;
            font-size: .875rem;
            color: #ef4444;
            text-decoration: none;
            font-weight: 500;
            transition: background .15s;
            width: 100%;
            border: none;
            background: none;
        }

        .btn-logout-side:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        /* ══════════════════════════════
           TOPBAR
        ══════════════════════════════ */
        .topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-w);
            right: 0;
            height: var(--topbar-h);
            background: #fff;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            z-index: 999;
            box-shadow: 0 1px 4px rgba(0,0,0,.06);
        }

        .topbar-left .page-title {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
        }

        .topbar-left .page-sub {
            font-size: .74rem;
            color: #94a3b8;
        }

        .topbar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 20px;
            color: #64748b;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .topbar-toggle:hover { background: #f1f5f9; }

        /* User info di topbar */
        .topbar-user {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .topbar-avatar {
            width: 38px; height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--blue), var(--blue-dark));
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 15px; font-weight: 700;
            flex-shrink: 0;
        }

        .topbar-user-name {
            font-size: .875rem;
            font-weight: 600;
            color: #1e293b;
            line-height: 1.2;
        }

        .topbar-user-role {
            font-size: .72rem;
            color: #94a3b8;
        }

        /* ══════════════════════════════
           MAIN CONTENT
        ══════════════════════════════ */
        .main-content {
            margin-left: var(--sidebar-w);
            margin-top: var(--topbar-h);
            padding: 24px;
            min-height: calc(100vh - var(--topbar-h));
        }

        /* ── STAT CARDS ── */
        .stat-card {
            border-radius: 1rem; border: none;
            padding: 18px 20px; display: flex; align-items: center; gap: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06); height: 100%;
            background: #fff;
        }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }
        .stat-label { font-size: .73rem; color: #64748b; margin-bottom: 2px; }
        .stat-value { font-size: 1.05rem; font-weight: 700; line-height: 1.2; }

        /* ── SECTION CARDS ── */
        .section-card {
            border-radius: 1rem; border: none;
            box-shadow: 0 2px 12px rgba(0,0,0,.06); background: #fff;
        }
        .section-card .card-header {
            background: #fff; border-bottom: 1px solid #f1f5f9;
            border-radius: 1rem 1rem 0 0 !important;
            padding: 14px 18px; font-weight: 600; font-size: .9rem;
            display: flex; align-items: center; gap: 8px; color: #1e293b;
        }

        /* ── PROFIL ── */
        .profil-avatar {
            width: 58px; height: 58px; border-radius: 50%;
            background: linear-gradient(135deg, var(--blue), var(--blue-dark));
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; color: #fff; font-weight: 700;
        }
        .info-label { font-size: .73rem; color: #94a3b8; margin-bottom: 1px; }
        .info-value { font-size: .875rem; font-weight: 500; color: #1e293b; }

        /* ── TABLE ── */
        .table-kas th {
            font-size: .72rem; text-transform: uppercase;
            letter-spacing: .04em; color: #94a3b8;
            font-weight: 600; background: #f8fafc; white-space: nowrap;
            border-bottom: 1px solid #f1f5f9 !important;
        }
        .table-kas td {
            font-size: .855rem; vertical-align: middle;
            border-bottom: 1px solid #f8fafc !important; color: #334155;
        }
        .table-kas tbody tr:hover { background: #f8fafc; }

        /* BADGE */
        .badge-bayar       { background: #d1fae5; color: #065f46; }
        .badge-pengeluaran { background: #ede9fe; color: #5b21b6; }

        /* EMPTY */
        .empty-state { text-align: center; padding: 40px 20px; color: #cbd5e1; }
        .empty-state i { font-size: 38px; margin-bottom: 10px; display: block; }
        .empty-state p { font-size: .85rem; margin: 0; }

        /* OVERLAY mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.4);
            z-index: 999;
        }

        /* ══════════════════════════════
           RESPONSIVE
        ══════════════════════════════ */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .sidebar-overlay.open {
                display: block;
            }
            .topbar {
                left: 0;
            }
            .topbar-toggle {
                display: block;
            }
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
            .topbar-user-name,
            .topbar-user-role {
                display: none;
            }
        }
    </style>
</head>
<body>

<!-- ══════════════════════════════
     SIDEBAR
══════════════════════════════ -->
<aside class="sidebar" id="sidebar">

    <!-- Brand -->
    <a class="sidebar-brand" href="#">
        <div class="brand-icon">
            <i class="bi bi-mortarboard-fill"></i>
        </div>
        <span class="brand-name">E Kas Seven</span>
    </a>

    <!-- Nav -->
    <nav class="sidebar-nav">
        <div class="nav-label">Menu</div>

        <a class="nav-item-kas active" href="dashboard_siswa.php">
            <i class="bi bi-house-door-fill"></i>
            Dashboard
        </a>

        <a class="nav-item-kas" href="profil_siswa.php">
            <i class="bi bi-person-circle"></i>
            Profil Saya
        </a>

        <div class="nav-label">Kas</div>

        <a class="nav-item-kas" href="riwayat_bayar.php">
            <i class="bi bi-clock-history"></i>
            Riwayat Bayar
        </a>

        <a class="nav-item-kas" href="status_bayar.php">
            <i class="bi bi-patch-check"></i>
            Status Pembayaran
        </a>

    </nav>

    <!-- Footer Logout -->
    <div class="sidebar-footer">
        <a href="../logout.php" class="btn-logout-side">
            <i class="bi bi-box-arrow-left"></i>
            Keluar
        </a>
    </div>

</aside>

<!-- Overlay mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<header class="topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="topbar-toggle" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
        <div class="topbar-left">
            <div class="page-title">Dashboard</div>
            <div class="page-sub">Selamat datang, <?= htmlspecialchars(explode(' ', $siswa['nama_siswa'])[0]) ?></div>
        </div>
    </div>

    <div class="topbar-user">
        <div class="topbar-avatar"><?= $initial ?></div>
        <div>
            <div class="topbar-user-name"><?= htmlspecialchars($siswa['nama_siswa']) ?></div>
            <div class="topbar-user-role"><?= htmlspecialchars($siswa['kelas']) ?></div>
        </div>
    </div>
</header>

<main class="main-content">

    <!-- STAT CARDS -->
    <div class="row g-3 mb-4">

        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#eff6ff;">
                    <i class="bi bi-cash-coin" style="color:#0d6efd;"></i>
                </div>
                <div>
                    <div class="stat-label">Total Dibayar</div>
                    <div class="stat-value" style="color:#0d6efd;"><?= rupiah($total_bayar) ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#d1fae5;">
                    <i class="bi bi-calendar-check" style="color:#059669;"></i>
                </div>
                <div>
                    <div class="stat-label">Minggu Dibayar</div>
                    <div class="stat-value" style="color:#059669;"><?= $total_minggu ?> minggu</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fef9c3;">
                    <i class="bi bi-wallet2" style="color:#d97706;"></i>
                </div>
                <div>
                    <div class="stat-label">Kas / Minggu</div>
                    <div class="stat-value" style="color:#d97706;"><?= rupiah($target_per_minggu) ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stat-card">
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

    <!-- ROW: Profil + Transaksi -->
    <div class="row g-3">

        <!-- Profil -->
        <div class="col-lg-4">
            <div class="section-card h-100">
                <div class="card-header">
                    <i class="bi bi-person-circle text-primary"></i> Profil Siswa
                </div>
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="profil-avatar"><?= $initial ?></div>
                        <div>
                            <div class="fw-semibold" style="font-size:.92rem; color:#1e293b;">
                                <?= htmlspecialchars($siswa['nama_siswa']) ?>
                            </div>
                            <div class="text-muted" style="font-size:.78rem;">
                                <?= htmlspecialchars($siswa['kelas']) ?>
                            </div>
                            <span class="badge rounded-pill mt-1"
                                style="background:#d1fae5;color:#065f46;font-size:.68rem;">
                                <i class="bi bi-circle-fill me-1" style="font-size:.4rem;"></i>
                                <?= ucfirst(htmlspecialchars($siswa['status'])) ?>
                            </span>
                        </div>
                    </div>

                    <hr class="my-2" style="border-color:#f1f5f9;">

                    <div class="mb-2">
                        <div class="info-label">ID Siswa</div>
                        <div class="info-value"><?= htmlspecialchars($siswa['id_siswa']) ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="info-label">Alamat</div>
                        <div class="info-value"><?= htmlspecialchars($siswa['alamat']) ?></div>
                    </div>

                    <div class="rounded-3 p-2" style="background:#eff6ff;font-size:.75rem;color:#1d4ed8;">
                        <i class="bi bi-info-circle me-1"></i>
                        Password login Anda adalah <strong>ID Siswa</strong> Anda.
                    </div>
                </div>
            </div>
        </div>

        <!-- Riwayat Transaksi -->
        <div class="col-lg-8">
            <div class="section-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>
                        <i class="bi bi-clock-history text-primary"></i> Riwayat Transaksi Kas
                    </span>
                    <span class="badge bg-primary rounded-pill" style="font-size:.68rem;">
                        <?= count($transaksi_list) ?>
                    </span>
                </div>
                <div class="card-body p-0">
                    <?php if (count($transaksi_list) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-kas mb-0">
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
                                    <td style="white-space:nowrap; font-size:.78rem;">
                                        <?= !empty($tr['nama_periode'])
                                            ? htmlspecialchars($tr['nama_periode'])
                                            : '<span class="text-muted">-</span>' ?>
                                    </td>
                                    <td><?= htmlspecialchars($tr['keterangan'] ?? '-') ?></td>
                                    <td style="white-space:nowrap; font-weight:600;">
                                        <?= rupiah($tr['jumlah']) ?>
                                    </td>
                                    <td>
                                        <?php if ($tr['jenis'] === 'bayar'): ?>
                                            <span class="badge rounded-pill badge-bayar" style="font-size:.68rem;">
                                                <i class="bi bi-arrow-down-circle-fill me-1"></i>Bayar
                                            </span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill badge-pengeluaran" style="font-size:.68rem;">
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
                        <p>Belum ada riwayat transaksi</p>
                        <p class="text-muted mt-1" style="font-size:.78rem;">
                            Transaksi kas Anda akan muncul di sini
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <div class="text-center text-muted mt-4" style="font-size:.74rem;">
        © <?= date('Y') ?> Sistem Kas Kelas · E Kas Seven
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const sidebar        = document.getElementById('sidebar');
    const overlay        = document.getElementById('sidebarOverlay');
    const toggleBtn      = document.getElementById('sidebarToggle');

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('open');
    });

    overlay.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
    });
</script>
</body>
</html>