<?php
require_once 'config/koneksi.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'bendahara') { header("Location: login.php"); exit(); }
$data = mysqli_query($koneksi_db, "SELECT t.*, s.nama_siswa FROM tb_transaksi t LEFT JOIN tb_siswa s ON t.id_siswa = s.id_siswa WHERE t.jenis = 'bayar' ORDER BY t.tanggal DESC");
$totalKas = mysqli_fetch_assoc(mysqli_query($koneksi_db, "SELECT SUM(jumlah) as total FROM tb_transaksi WHERE jenis='bayar'"))['total'] ?? 0;
$totalTransaksi = mysqli_num_rows($data);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kas Masuk - Kas Kelas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --sb-full:220px; --sb-mini:64px; --accent:#0d6efd; --ease:0.25s ease; }
        body { background:#f4f6fb; margin:0; }
        #sidebar { position:fixed; top:0; left:0; width:var(--sb-full); height:100vh; background:#fff; border-right:1px solid #e8eaf0; display:flex; flex-direction:column; z-index:1040; overflow:hidden; transition:width var(--ease); }
        #sidebar.mini { width:var(--sb-mini); }
        .sb-brand { display:flex; align-items:center; gap:10px; padding:18px 13px 14px; white-space:nowrap; border-bottom:1px solid #f0f2f7; min-height:64px; }
        .sb-logo { width:36px; height:36px; background:#e8f0fe; border-radius:10px; display:flex; align-items:center; justify-content:center; color:var(--accent); flex-shrink:0; }
        .sb-title { font-weight:700; font-size:14px; color:var(--accent); transition:opacity var(--ease),width var(--ease); overflow:hidden; }
        #sidebar.mini .sb-title { opacity:0; width:0; }
        .sb-toggle { position:absolute; top:20px; right:-8px; width:26px; height:26px; background:#fff; border:1px solid #dde2ee; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:10px; color:#666; box-shadow:0 2px 6px rgba(0,0,0,.09); transition:top var(--ease),right var(--ease),transform var(--ease); z-index:10; }
        #sidebar.mini .sb-toggle { top:56px; right:4px; transform:rotate(180deg); }
        .sb-nav { flex:1; padding:10px 8px; overflow-y:auto; overflow-x:hidden; }
        .sb-nav .nav-link { display:flex; align-items:center; gap:12px; padding:10px; border-radius:10px; color:#555; font-weight:500; font-size:14px; white-space:nowrap; text-decoration:none; transition:background .15s,color .15s; position:relative; }
        .sb-nav .nav-link:hover { background:#f0f4ff; color:var(--accent); }
        .sb-nav .nav-link.active { background:var(--accent); color:#fff; }
        .nav-icon { font-size:15px; width:20px; text-align:center; flex-shrink:0; }
        .nav-label { transition:opacity var(--ease); }
        #sidebar.mini .nav-label { opacity:0; pointer-events:none; }
        #sidebar.mini .nav-link::after { content:attr(data-tip); position:absolute; left:calc(var(--sb-mini) - 4px); background:#1a1a2e; color:#fff; font-size:12px; padding:5px 10px; border-radius:6px; white-space:nowrap; opacity:0; pointer-events:none; transition:opacity .15s; z-index:999; }
        #sidebar.mini .nav-link:hover::after { opacity:1; }
        .sb-footer { padding:10px 8px; border-top:1px solid #f0f2f7; display:flex; flex-direction:column; gap:6px; }
        .sb-btn { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; font-size:13px; font-weight:500; border:none; cursor:pointer; text-decoration:none; white-space:nowrap; overflow:hidden; }
        .sb-btn.primary { background:var(--accent); color:#fff; } .sb-btn.primary:hover { background:#1557b0; }
        .sb-btn.danger { background:#fdecea; color:#c62828; } .sb-btn.danger:hover { background:#fcd5d1; }
        .sb-btn-icon { font-size:14px; width:20px; text-align:center; flex-shrink:0; }
        .sb-btn-label { transition:opacity var(--ease); }
        #sidebar.mini .sb-btn-label { opacity:0; pointer-events:none; }
        #main { margin-left:var(--sb-full); min-height:100vh; padding:28px; transition:margin-left var(--ease); }
        #main.expanded { margin-left:var(--sb-mini); }
        #bottom-nav { display:none; position:fixed; bottom:0; left:0; right:0; height:64px; background:#fff; border-top:1px solid #e8eaf0; z-index:1050; align-items:center; justify-content:space-around; padding:0 4px; box-shadow:0 -4px 16px rgba(0,0,0,0.07); }
        .bn-item { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:3px; flex:1; height:100%; text-decoration:none; color:#aaa; font-weight:500; transition:color .15s; padding:6px 2px; }
        .bn-item.active { color:var(--accent); }
        .bn-item i { font-size:19px; }
        .bn-item span { font-size:9px; line-height:1; }
        .bn-add { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:3px; flex:1; height:100%; border:none; background:none; cursor:pointer; padding:6px 2px; }
        .bn-add .bn-add-icon { width:42px; height:42px; background:var(--accent); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:18px; color:#fff; box-shadow:0 4px 14px rgba(13,110,253,0.40); transition:transform .15s,box-shadow .15s; }
        .bn-add:active .bn-add-icon { transform:scale(0.92); }
        .bn-add .bn-add-label { font-size:9px; color:#aaa; line-height:1; }
        @media (max-width:767.98px) {
            #sidebar { display:none !important; }
            #main { margin-left:0 !important; padding:20px 16px 84px; }
            #bottom-nav { display:flex !important; }
        }
    </style>
</head>
<body>
<div id="sidebar">
    <div class="sb-brand"><div class="sb-logo"><i class="fa-solid fa-graduation-cap"></i></div><span class="sb-title">E Kas Seven</span></div>
    <div class="sb-toggle" onclick="desktopToggle()"><i class="fa-solid fa-chevron-left"></i></div>
    <nav class="sb-nav">
        <div class="nav-item mt-1"><a href="dashboard.php" class="nav-link" data-tip="Dashboard"><i class="nav-icon fa-solid fa-house"></i><span class="nav-label">Dashboard</span></a></div>
        <div class="nav-item mt-2"><a href="datamurid.php" class="nav-link" data-tip="Data Murid"><i class="nav-icon fa-solid fa-users"></i><span class="nav-label">Data Murid</span></a></div>
        <div class="nav-item mt-2"><a href="kasmasuk.php" class="nav-link active" data-tip="Kas Masuk"><i class="nav-icon fa-solid fa-arrow-trend-up"></i><span class="nav-label">Kas Masuk</span></a></div>
        <div class="nav-item mt-2"><a href="kaskeluar.php" class="nav-link" data-tip="Kas Keluar"><i class="nav-icon fa-solid fa-arrow-trend-down"></i><span class="nav-label">Kas Keluar</span></a></div>
        <div class="nav-item mt-2"><a href="status.php" class="nav-link" data-tip="Status Pembayaran"><i class="nav-icon fa-regular fa-circle-check"></i><span class="nav-label">Status Pembayaran</span></a></div>
        <div class="nav-item mt-2"><a href="arus.php" class="nav-link" data-tip="Arus Kas"><i class="nav-icon fa-solid fa-chart-column"></i><span class="nav-label">Arus Kas</span></a></div>
        <div class="nav-item mt-2"><a href="laporan.php" class="nav-link" data-tip="Laporan"><i class="nav-icon fa-regular fa-file-lines"></i><span class="nav-label">Laporan</span></a></div>
    </nav>
    <div class="sb-footer">
        <button class="sb-btn primary" data-bs-toggle="modal" data-bs-target="#modalTransaksi"><span class="sb-btn-icon"><i class="fa-solid fa-plus"></i></span><span class="sb-btn-label">Tambah Transaksi</span></button>
        <a href="logout.php" onclick="return confirm('Yakin ingin logout?')" class="sb-btn danger"><span class="sb-btn-icon"><i class="fa-solid fa-right-from-bracket"></i></span><span class="sb-btn-label">Keluar</span></a>
    </div>
</div>

<main id="main">
    <div class="mb-4"><h4 class="fw-bold mb-1">Kas Masuk</h4><p class="text-muted small mb-0">Daftar transaksi kas masuk</p></div>
    <div class="card border-0 rounded-4 mb-4" style="background:linear-gradient(135deg,#198754,#20c997);">
        <div class="card-body d-flex justify-content-between align-items-center p-4">
            <div>
                <p class="text-white opacity-75 small mb-1">Total Kas Masuk</p>
                <h2 class="text-white fw-bold mb-1">Rp <?= number_format($totalKas, 0, ',', '.') ?></h2>
                <small class="text-white opacity-75"><?= $totalTransaksi ?> transaksi pemasukan</small>
            </div>
            <div class="bg-white bg-opacity-25 rounded-4 d-flex align-items-center justify-content-center" style="width:64px;height:64px;">
                <i class="fa-solid fa-arrow-trend-up text-white fs-3"></i>
            </div>
        </div>
    </div>
    <div class="card border rounded-4">
        <div class="card-body p-4">
            <h6 class="fw-semibold mb-4">Riwayat Kas Masuk</h6>
            <?php if ($totalTransaksi == 0) : ?><p class="text-muted text-center py-3"><i class="fa-solid fa-circle-info me-2"></i>Belum ada transaksi kas masuk.</p><?php endif; ?>
            <?php while ($row = mysqli_fetch_assoc($data)) : ?>
            <div class="card border rounded-4 mb-2">
                <div class="card-body d-flex justify-content-between align-items-center gap-3 py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;"><i class="fa-solid fa-arrow-up"></i></div>
                        <div>
                            <p class="fw-semibold small mb-1">Iuran Kas - <?= htmlspecialchars($row['nama_siswa'] ?? 'Umum') ?></p>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge bg-primary bg-opacity-10 text-primary" style="font-size:11px;"><?= htmlspecialchars($row['keterangan'] ?? 'Iuran') ?></span>
                                <small class="text-muted"><?= date('d M Y', strtotime($row['tanggal'])) ?></small>
                            </div>
                        </div>
                    </div>
                    <span class="fw-bold text-success small text-nowrap">+Rp <?= number_format($row['jumlah'], 0, ',', '.') ?></span>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</main>

<!-- ══ BOTTOM NAVIGATION ══ -->
<div id="bottom-nav">
    <a href="dashboard.php" class="bn-item"><i class="fa-solid fa-house"></i><span>Dashboard</span></a>
    <a href="datamurid.php" class="bn-item"><i class="fa-solid fa-users"></i><span>Murid</span></a>
    <a href="status.php" class="bn-item"><i class="fa-regular fa-circle-check"></i><span>Status</span></a>
    <button class="bn-add" data-bs-toggle="modal" data-bs-target="#modalTransaksi">
        <div class="bn-add-icon"><i class="fa-solid fa-plus"></i></div>
        <span class="bn-add-label">Tambah</span>
    </button>
    <a href="arus.php" class="bn-item"><i class="fa-solid fa-chart-column"></i><span>Arus Kas</span></a>
    <a href="laporan.php" class="bn-item"><i class="fa-regular fa-file-lines"></i><span>Laporan</span></a>
    <a href="logout.php" onclick="return confirm('Yakin ingin logout?')" class="bn-item"><i class="fa-solid fa-right-from-bracket"></i><span>Keluar</span></a>
</div>

<!-- ══ MODAL ══ -->
<div class="modal fade" id="modalTransaksi" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-semibold">Tambah Transaksi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="proses_transaksi.php">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Jenis</label><select name="jenis" class="form-select rounded-3" required><option value="">-- Pilih --</option><option value="bayar">Pemasukan</option><option value="pengeluaran">Pengeluaran</option></select></div>
                    <div class="mb-3"><label class="form-label">Keterangan</label><textarea name="keterangan" class="form-control rounded-3" rows="2" required></textarea></div>
                    <div class="mb-3"><label class="form-label">Jumlah</label><input type="number" name="jumlah" class="form-control rounded-3" required></div>
                    <div class="mb-3"><label class="form-label">Tanggal</label><input type="date" name="tanggal" class="form-control rounded-3" required></div>
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