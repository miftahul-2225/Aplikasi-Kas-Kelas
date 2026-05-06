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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa – E Kas Seven</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --sb-full : 220px;
            --sb-mini : 64px;
            --accent  : #0d6efd;
            --ease    : 0.25s ease;
        }

        body { background: #f4f6fb; margin: 0; font-family: 'Segoe UI', sans-serif; }

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

        .sb-nav { flex: 1; padding: 10px 6px; overflow-y: auto; overflow-x: hidden; }
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
        .sb-btn.danger  { background: #fdecea; color: #c62828; }
        .sb-btn.danger:hover  { background: #fcd5d1; }
        .sb-btn-icon { font-size: 14px; width: 20px; text-align: center; flex-shrink: 0; }
        .sb-btn-label { transition: opacity var(--ease); }
        #sidebar.mini .sb-btn-label { opacity: 0; pointer-events: none; }

        /* ══ TOPBAR ══ */
        #topbar {
            position: fixed; top: 0;
            left: var(--sb-full); right: 0;
            height: 64px; background: #fff;
            border-bottom: 1px solid #e8eaf0;
            display: flex; align-items: center;
            justify-content: space-between;
            padding: 0 24px; z-index: 1030;
            box-shadow: 0 1px 4px rgba(0,0,0,.05);
            transition: left var(--ease);
        }
        #topbar.expanded { left: var(--sb-mini); }

        .topbar-title { font-size: 1rem; font-weight: 700; color: #1e293b; }
        .topbar-sub   { font-size: .74rem; color: #94a3b8; }

        .topbar-user {
            display: flex; align-items: center; gap: 10px;
        }
        .topbar-avatar {
            width: 38px; height: 38px; border-radius: 50%;
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 15px; font-weight: 700; flex-shrink: 0;
        }
        .topbar-name { font-size: .875rem; font-weight: 600; color: #1e293b; line-height: 1.2; }
        .topbar-role { font-size: .72rem; color: #94a3b8; }

        /* ══ MAIN ══ */
        #main {
            margin-left: var(--sb-full);
            margin-top: 64px;
            min-height: calc(100vh - 64px);
            padding: 28px;
            transition: margin-left var(--ease);
        }
        #main.expanded { margin-left: var(--sb-mini); }

        /* ══ STAT CARDS ══ */
        .stat-card {
            border-radius: 1rem; border: none;
            padding: 18px 20px; display: flex; align-items: center; gap: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06); height: 100%; background: #fff;
        }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
        }
        .stat-label { font-size: .73rem; color: #64748b; margin-bottom: 2px; }
        .stat-value { font-size: 1.05rem; font-weight: 700; line-height: 1.2; }

        /* ══ SECTION CARDS ══ */
        .section-card {
            border-radius: 1rem; border: none;
            box-shadow: 0 2px 12px rgba(0,0,0,.06); background: #fff;
        }
        .section-card .card-header {
            background: #fff; border-bottom: 1px solid #f1f5f9;
            border-radius: 1rem 1rem 0 0 !important;
            padding: 14px 18px; font-weight: 600; font-size: .9rem;
            display: flex; align-items: center; justify-content: space-between;
            color: #1e293b;
        }

        /* ══ PROFIL ══ */
        .profil-avatar {
            width: 58px; height: 58px; border-radius: 50%;
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; color: #fff; font-weight: 700;
        }
        .info-label { font-size: .73rem; color: #94a3b8; margin-bottom: 1px; }
        .info-value { font-size: .875rem; font-weight: 500; color: #1e293b; }

        /* ══ TABLE ══ */
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

        /* ══ ANIMASI ══ */
        .card-anim { opacity: 0; transform: translateY(16px); animation: fadeUp .45s ease forwards; }
        @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }

        /* ══ BOTTOM NAV (Mobile) ══ */
        #bottom-nav {
            display: none;
            position: fixed; bottom: 0; left: 0; right: 0;
            height: 64px; background: #fff;
            border-top: 1px solid #e8eaf0; z-index: 1050;
            align-items: center; justify-content: space-around;
            padding: 0 4px; box-shadow: 0 -4px 16px rgba(0,0,0,.07);
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

        /* ══ MOBILE ══ */
        @media (max-width: 767.98px) {
            #sidebar    { display: none !important; }
            #topbar     { left: 0 !important; }
            #main       { margin-left: 0 !important; padding: 20px 16px 84px; }
            #bottom-nav { display: flex !important; }
            .topbar-name, .topbar-role { display: none; }
        }
    </style>
</head>
<body>

<!-- ══════════════════════════════
     SIDEBAR
══════════════════════════════ -->
<div id="sidebar">
    <div class="sb-brand">
        <div class="sb-logo">
            <i class="fa-solid fa-graduation-cap"></i>
        </div>
        <span class="sb-title">E Kas Seven</span>
    </div>

    <div class="sb-toggle" onclick="desktopToggle()">
        <i class="fa-solid fa-chevron-left"></i>
    </div>

    <nav class="sb-nav">
        <div class="nav-item mt-1">
            <a href="dashboard_siswa.php" class="nav-link active" data-tip="Dashboard">
                <i class="nav-icon fa-solid fa-house"></i>
                <span class="nav-label">Dashboard</span>
            </a>
        </div>
        <div class="nav-item mt-2">
            <a href="profil_siswa.php" class="nav-link" data-tip="Profil Saya">
                <i class="nav-icon fa-solid fa-circle-user"></i>
                <span class="nav-label">Profil Saya</span>
            </a>
        </div>
        <div class="nav-item mt-2">
            <a href="riwayat_bayar.php" class="nav-link" data-tip="Riwayat Bayar">
                <i class="nav-icon fa-solid fa-clock-rotate-left"></i>
                <span class="nav-label">Riwayat Bayar</span>
            </a>
        </div>
        <div class="nav-item mt-2">
            <a href="status_bayar.php" class="nav-link" data-tip="Status Pembayaran">
                <i class="nav-icon fa-regular fa-circle-check"></i>
                <span class="nav-label">Status Pembayaran</span>
            </a>
        </div>
    </nav>

    <div class="sb-footer">
        <a href="../logout.php" onclick="return confirm('Yakin ingin keluar?')" class="sb-btn danger">
            <span class="sb-btn-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
            <span class="sb-btn-label">Keluar</span>
        </a>
    </div>
</div>

<!-- ══════════════════════════════
     TOPBAR
══════════════════════════════ -->
<div id="topbar">
    <div>
        <div class="topbar-title">Dashboard Siswa</div>
        <div class="topbar-sub">
            Selamat datang, <?= htmlspecialchars(explode(' ', $siswa['nama_siswa'])[0]) ?>!
        </div>
    </div>
    <div class="topbar-user">
        <div class="topbar-avatar"><?= $initial ?></div>
        <div>
            <div class="topbar-name"><?= htmlspecialchars($siswa['nama_siswa']) ?></div>
            <div class="topbar-role"><?= htmlspecialchars($siswa['kelas']) ?></div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════
     MAIN CONTENT
══════════════════════════════ -->
<main id="main">

    <!-- STAT CARDS -->
    <div class="row g-3 mb-4">

        <div class="col-6 col-md-3">
            <div class="stat-card card-anim" style="animation-delay:.1s;">
                <div class="stat-icon" style="background:#eff6ff;">
                    <i class="fa-solid fa-money-bill-wave" style="color:#0d6efd;"></i>
                </div>
                <div>
                    <div class="stat-label">Total Dibayar</div>
                    <div class="stat-value" style="color:#0d6efd;"><?= rupiah($total_bayar) ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card card-anim" style="animation-delay:.2s;">
                <div class="stat-icon" style="background:#d1fae5;">
                    <i class="fa-solid fa-calendar-check" style="color:#059669;"></i>
                </div>
                <div>
                    <div class="stat-label">Minggu Dibayar</div>
                    <div class="stat-value" style="color:#059669;"><?= $total_minggu ?> minggu</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card card-anim" style="animation-delay:.3s;">
                <div class="stat-icon" style="background:#fef9c3;">
                    <i class="fa-solid fa-wallet" style="color:#d97706;"></i>
                </div>
                <div>
                    <div class="stat-label">Kas / Minggu</div>
                    <div class="stat-value" style="color:#d97706;"><?= rupiah($target_per_minggu) ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card card-anim" style="animation-delay:.4s;">
                <div class="stat-icon" style="background:#fce7f3;">
                    <i class="fa-solid fa-receipt" style="color:#db2777;"></i>
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
            <div class="section-card h-100 card-anim" style="animation-delay:.45s;">
                <div class="card-header">
                    <span>
                        <i class="fa-solid fa-circle-user text-primary me-1"></i> Profil Siswa
                    </span>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="profil-avatar"><?= $initial ?></div>
                        <div>
                            <div class="fw-semibold" style="font-size:.92rem; color:#1e293b; line-height:1.3;">
                                <?= htmlspecialchars($siswa['nama_siswa']) ?>
                            </div>
                            <div class="text-muted" style="font-size:.78rem;">
                                <?= htmlspecialchars($siswa['kelas']) ?>
                            </div>
                            <span class="badge rounded-pill mt-1"
                                style="background:#d1fae5;color:#065f46;font-size:.68rem;">
                                <i class="fa-solid fa-circle" style="font-size:.4rem;"></i>
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
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Password login Anda adalah <strong>ID Siswa</strong> Anda.
                    </div>
                </div>
            </div>
        </div>

        <!-- Riwayat Transaksi -->
        <div class="col-lg-8">
            <div class="section-card card-anim" style="animation-delay:.5s;">
                <div class="card-header">
                    <span>
                        <i class="fa-solid fa-clock-rotate-left text-primary me-1"></i> Riwayat Transaksi Kas
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
                                        <?= date('d M Y', strtotime($tr['tanggal'])) ?>
                                    </td>
                                    <td style="white-space:nowrap; font-size:.78rem;">
                                        <?= !empty($tr['nama_periode'])
                                            ? htmlspecialchars($tr['nama_periode'])
                                            : '<span class="text-muted">-</span>' ?>
                                    </td>
                                    <td><?= htmlspecialchars($tr['keterangan'] ?? '-') ?></td>
                                    <td style="white-space:nowrap; font-weight:600;">
                                        <?php if ($tr['jenis'] === 'bayar'): ?>
                                            <span class="text-success">+<?= rupiah($tr['jumlah']) ?></span>
                                        <?php else: ?>
                                            <span class="text-danger">-<?= rupiah($tr['jumlah']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($tr['jenis'] === 'bayar'): ?>
                                            <span class="badge rounded-pill badge-bayar" style="font-size:.68rem;">
                                                <i class="fa-solid fa-arrow-down me-1"></i>Bayar
                                            </span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill badge-pengeluaran" style="font-size:.68rem;">
                                                <i class="fa-solid fa-arrow-up me-1"></i>Pengeluaran
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
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <p class="mb-1">Belum ada riwayat transaksi</p>
                        <p class="text-muted" style="font-size:.8rem;">
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

<!-- ══════════════════════════════
     BOTTOM NAV (Mobile Only)
══════════════════════════════ -->
<div id="bottom-nav">
    <a href="dashboard_siswa.php" class="bn-item active">
        <i class="fa-solid fa-house"></i>
        <span>Dashboard</span>
    </a>
    <a href="profil_siswa.php" class="bn-item">
        <i class="fa-solid fa-circle-user"></i>
        <span>Profil</span>
    </a>
    <a href="riwayat_bayar.php" class="bn-item">
        <i class="fa-solid fa-clock-rotate-left"></i>
        <span>Riwayat</span>
    </a>
    <a href="status_bayar.php" class="bn-item">
        <i class="fa-regular fa-circle-check"></i>
        <span>Status</span>
    </a>
    <a href="../logout.php" onclick="return confirm('Yakin ingin keluar?')" class="bn-item">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Keluar</span>
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function desktopToggle() {
        document.getElementById('sidebar').classList.toggle('mini');
        document.getElementById('main').classList.toggle('expanded');
        document.getElementById('topbar').classList.toggle('expanded');
    }
</script>
</body>
</html>