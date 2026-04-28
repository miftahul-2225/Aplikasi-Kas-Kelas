<?php
require_once 'config/koneksi.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'bendahara') {
    header("Location: login.php");
    exit();
}

// SEARCH
$cari = $_GET['cari'] ?? '';

if ($cari) {
    $data = mysqli_query($koneksi_db, "
        SELECT * FROM tb_siswa 
        WHERE nama_siswa LIKE '%$cari%'
        OR id_siswa LIKE '%$cari%'
        OR kelas LIKE '%$cari%'
        ORDER BY nama_siswa ASC
    ");
} else {
    $data = mysqli_query($koneksi_db, "SELECT * FROM tb_siswa ORDER BY nama_siswa ASC");
}

$total = mysqli_num_rows($data);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data Murid - Kas Kelas</title>
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
            position: fixed;
            top: 0; left: 0;
            width: var(--sb-full);
            height: 100vh;
            background: #fff;
            border-right: 1px solid #e8eaf0;
            display: flex;
            flex-direction: column;
            z-index: 1040;
            overflow: hidden;
            transition: width var(--ease);
        }
        #sidebar.mini { width: var(--sb-mini); }

        /* ── brand ── */
        .sb-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 18px 13px 14px;
            white-space: nowrap;
            border-bottom: 1px solid #f0f2f7;
            min-height: 64px;
        }
        .sb-logo {
            width: 36px; height: 36px;
            background: #e8f0fe;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: var(--accent);
            flex-shrink: 0;
        }
        .sb-title {
            font-weight: 700; font-size: 14px;
            color: var(--accent);
            transition: opacity var(--ease), width var(--ease);
            overflow: hidden;
        }
        #sidebar.mini .sb-title { opacity: 0; width: 0; }

        /* ── toggle arrow ── */
        .sb-toggle {
            position: absolute;
            top: 20px; right: -8px;
            width: 26px; height: 26px;
            background: #fff;
            border: 1px solid #dde2ee;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            font-size: 10px; color: #666;
            box-shadow: 0 2px 6px rgba(0,0,0,.09);
            transition: top var(--ease), right var(--ease), transform var(--ease);
            z-index: 10;
        }
        #sidebar.mini .sb-toggle {
            top: 56px;
            right: 4px;
            transform: rotate(180deg);
        }

        /* ── nav ── */
        .sb-nav {
            flex: 1;
            padding: 10px 8px;
            overflow-y: auto; overflow-x: hidden;
        }
        .sb-nav .nav-link {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 10px;
            border-radius: 10px;
            color: #555; font-weight: 500; font-size: 14px;
            white-space: nowrap;
            text-decoration: none;
            transition: background .15s, color .15s;
            position: relative;
        }
        .sb-nav .nav-link:hover  { background: #f0f4ff; color: var(--accent); }
        .sb-nav .nav-link.active { background: var(--accent); color: #fff; }

        .nav-icon { font-size: 15px; width: 20px; text-align: center; flex-shrink: 0; }

        .nav-label { transition: opacity var(--ease); }
        #sidebar.mini .nav-label { opacity: 0; pointer-events: none; }

        /* tooltip saat mini */
        #sidebar.mini .nav-link::after {
            content: attr(data-tip);
            position: absolute;
            left: calc(var(--sb-mini) - 4px);
            background: #1a1a2e; color: #fff;
            font-size: 12px; padding: 5px 10px;
            border-radius: 6px; white-space: nowrap;
            opacity: 0; pointer-events: none;
            transition: opacity .15s;
            z-index: 999;
        }
        #sidebar.mini .nav-link:hover::after { opacity: 1; }

        /* ── footer ── */
        .sb-footer {
            padding: 10px 8px;
            border-top: 1px solid #f0f2f7;
            display: flex; flex-direction: column; gap: 6px;
        }
        .sb-btn {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border-radius: 10px;
            font-size: 13px; font-weight: 500;
            border: none; cursor: pointer;
            text-decoration: none; white-space: nowrap; overflow: hidden;
        }
        .sb-btn.primary { background: var(--accent); color: #fff; }
        .sb-btn.primary:hover { background: #1557b0; }
        .sb-btn.danger  { background: #fdecea; color: #c62828; }
        .sb-btn.danger:hover  { background: #fcd5d1; }
        .sb-btn-icon { font-size: 14px; width: 20px; text-align: center; flex-shrink: 0; }
        .sb-btn-label { transition: opacity var(--ease); }
        #sidebar.mini .sb-btn-label { opacity: 0; pointer-events: none; }

        /* ══ MAIN ══ */
        #main {
            margin-left: var(--sb-full);
            min-height: 100vh;
            padding: 28px;
            transition: margin-left var(--ease);
        }
        #main.expanded { margin-left: var(--sb-mini); }

        /* ══ MOBILE ══ */
        #mobile-btn {
            display: none;
            position: fixed; top: 14px; left: 14px;
            z-index: 1060;
            background: var(--accent); border: none; color: #fff;
            width: 40px; height: 40px; border-radius: 10px;
            font-size: 16px; cursor: pointer;
            align-items: center; justify-content: center;
        }
        #overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.35); z-index: 1039;
        }
        #overlay.show { display: block; }

        @media (max-width: 767.98px) {
            #mobile-btn  { display: flex; }
            #sidebar     { left: calc(-1 * var(--sb-full)); width: var(--sb-full) !important; transition: left var(--ease); }
            #sidebar.open { left: 0; }
            .sb-toggle   { display: none; }
            #main        { margin-left: 0 !important; padding: 72px 16px 20px; }
        }

        /* ══ TABLE ══ */
        .table thead th { background: #f8f9fa; font-size: 13px; font-weight: 600; }
        .table tbody td, .table tbody th { font-size: 13px; vertical-align: middle; }
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

    <!-- toggle arrow (desktop) -->
    <div class="sb-toggle" onclick="desktopToggle()">
        <i class="fa-solid fa-chevron-left"></i>
    </div>

    <nav class="sb-nav">
        <div class="nav-item">
            <a href="dashboard.php" class="nav-link" data-tip="Dashboard">
                <i class="nav-icon fa-solid fa-house"></i>
                <span class="nav-label">Dashboard</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="datamurid.php" class="nav-link active" data-tip="Data Murid">
                <i class="nav-icon fa-solid fa-users"></i>
                <span class="nav-label">Data Murid</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="kasmasuk.php" class="nav-link" data-tip="Kas Masuk">
                <i class="nav-icon fa-solid fa-arrow-trend-up"></i>
                <span class="nav-label">Kas Masuk</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="kaskeluar.php" class="nav-link" data-tip="Kas Keluar">
                <i class="nav-icon fa-solid fa-arrow-trend-down"></i>
                <span class="nav-label">Kas Keluar</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="status.php" class="nav-link" data-tip="Status Pembayaran">
                <i class="nav-icon fa-regular fa-circle-check"></i>
                <span class="nav-label">Status Pembayaran</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="arus.php" class="nav-link" data-tip="Arus Kas">
                <i class="nav-icon fa-solid fa-chart-column"></i>
                <span class="nav-label">Arus Kas</span>
            </a>
        </div>
        <div class="nav-item">
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
        <h4 class="fw-bold mb-1">Data Murid</h4>
        <p class="text-muted small mb-0">Daftar data murid kelas</p>
    </div>

    <!-- SEARCH & ACTION -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <form method="GET" class="d-flex gap-2">
            <div class="input-group" style="max-width:280px;">
                <span class="input-group-text bg-white border-end-0">
                    <i class="fa-solid fa-magnifying-glass text-muted"></i>
                </span>
                <input type="text" name="cari"
                       class="form-control border-start-0 ps-0"
                       placeholder="Cari murid..."
                       value="<?= htmlspecialchars($cari) ?>">
                <button class="btn btn-primary rounded-end-3">Cari</button>
            </div>
        </form>
    </div>

    <p class="text-muted small fw-semibold mb-3">
        Total <?= $total ?> murid terdaftar
    </p>

    <!-- TABEL -->
    <div class="card border rounded-4">
        <div class="card-body p-0">
            <div class="px-4 pt-4 pb-2">
                <h6 class="fw-semibold mb-0">Daftar Murid</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover text-center mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">No</th>
                            <th class="text-start">Nama</th>
                            <th>NISN</th>
                            <th>Kelas</th>
                            <th class="text-start">Alamat</th>
                            <th class="pe-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; ?>
                        <?php while ($row = mysqli_fetch_assoc($data)) : ?>
                        <tr>
                            <td class="ps-4"><?= $no++ ?></td>
                            <td class="text-start fw-medium"><?= htmlspecialchars($row['nama_siswa']) ?></td>
                            <td class="text-muted"><?= htmlspecialchars($row['id_siswa']) ?></td>
                            <td><?= htmlspecialchars($row['kelas']) ?></td>
                            <td class="text-start text-muted"><?= htmlspecialchars($row['alamat']) ?></td>
                            <td class="pe-4">
                                <?php if (strtolower($row['status']) == 'aktif') : ?>
                                    <span class="badge bg-success-subtle text-success rounded-pill px-3">Aktif</span>
                                <?php else : ?>
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3"><?= htmlspecialchars($row['status']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>

                        <?php if ($total == 0) : ?>
                        <tr>
                            <td colspan="6" class="py-4 text-muted">
                                <i class="fa-solid fa-circle-info me-2"></i>
                                Tidak ada data murid<?= $cari ? ' dengan kata kunci "' . htmlspecialchars($cari) . '"' : '' ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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