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

    // tentukan awal & akhir minggu (Senin - Minggu)
    $mulai = date('Y-m-d', strtotime('monday this week', strtotime($tanggal)));
    $selesai = date('Y-m-d', strtotime('sunday this week', strtotime($tanggal)));

    // cek apakah sudah ada
    $cek = mysqli_query($koneksi_db, "
        SELECT * FROM tb_periode
        WHERE tanggal_mulai = '$mulai'
        AND tanggal_selesai = '$selesai'
        LIMIT 1
    ");

    if(!$cek){
        die(mysqli_error($koneksi_db));
    }

    $data = mysqli_fetch_assoc($cek);

    if($data){
        return $data;
    } else {

        // ===== HITUNG MINGGU DALAM BULAN =====
        $tanggal_obj = strtotime($tanggal);

        $tanggal_hari = date('j', $tanggal_obj); // tanggal (1-31)
        $bulan = date('n', $tanggal_obj);
        $tahun = date('Y', $tanggal_obj);

        // minggu ke dalam bulan
        $minggu_ke = ceil($tanggal_hari / 7);

        // format tanggal
        $mulai_format = date('d M', strtotime($mulai));
        $selesai_format = date('d M', strtotime($selesai));

        // nama bulan indo
        $nama_bulan = bulanIndo($bulan);

        // nama periode baru
        $nama = "Minggu ke-$minggu_ke $nama_bulan $tahun ($mulai_format - $selesai_format)";

        // insert ke database
        $insert = mysqli_query($koneksi_db, "
            INSERT INTO tb_periode 
            (nama_periode, minggu_ke, tahun, tanggal_mulai, tanggal_selesai, status, target)
            VALUES
            ('$nama', '$minggu_ke', '$tahun', '$mulai', '$selesai', 'aktif', 10000)
        ");

        if(!$insert){
            die(mysqli_error($koneksi_db));
        }

        $id = mysqli_insert_id($koneksi_db);

        return [
            'id_periode' => $id,
            'target' => 10000
        ];
    }
}

// ambil tanggal hari ini
$today = date('Y-m-d');

// ambil periode aktif otomatis
$periodeAktif = getPeriode($koneksi_db, $today);
$id_periode = $periodeAktif['id_periode'];
$target_default = $periodeAktif['target'] ?? 10000;

// ambil data siswa
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

if(!$data){
    die(mysqli_error($koneksi_db));
}

// hitung status
$lunas = 0;
$sebagian = 0;
$belum = 0;

$rows = [];

while($row = mysqli_fetch_assoc($data)) {

    $target = $target_default;
    $dibayar = $row['dibayar'];

    if ($dibayar >= $target && $target > 0) {
        $row['status'] = 'lunas';
        $lunas++;
    } elseif ($dibayar > 0) {
        $row['status'] = 'sebagian';
        $sebagian++;
    } else {
        $row['status'] = 'belum';
        $belum++;
    }

    $row['target'] = $target;
    $rows[] = $row;
}

// ================= SIMPAN PEMBAYARAN =================
$id_user = 1;

if(isset($_POST['simpan'])) {

    $id_siswa = $_POST['id_siswa'] ?? '';
    $jumlah   = $_POST['jumlah'] ?? '';
    $tanggal  = $_POST['tanggal'] ?? '';

    if($id_siswa == '' || $jumlah == '' || $tanggal == ''){
        echo "<script>alert('Data belum lengkap!');</script>";
    } else {

        $id_siswa = mysqli_real_escape_string($koneksi_db, $id_siswa);
        $jumlah   = (int)$jumlah;
        $tanggal  = mysqli_real_escape_string($koneksi_db, $tanggal);

        // VALIDASI HARI (Senin–Jumat)
        $hari = date('N', strtotime($tanggal)); // 1=Senin

        if($hari > 5){
            echo "<script>alert('Pembayaran hanya boleh hari Senin - Jumat!');</script>";
        } else {

            // ambil / buat periode otomatis
            $periodeBaru = getPeriode($koneksi_db, $tanggal);
            $id_periode_input = $periodeBaru['id_periode'];

            // simpan transaksi
            $simpan = mysqli_query($koneksi_db, "
                INSERT INTO tb_transaksi 
                (id_siswa, id_user, id_periode, tanggal, jenis, jumlah, keterangan)
                VALUES 
                ('$id_siswa', '$id_user', '$id_periode_input', '$tanggal', 'bayar', '$jumlah', 'Iuran Kas')
            ");

            if($simpan){
                echo "<script>
                    alert('Pembayaran berhasil!');
                    window.location='status.php';
                </script>";
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
    <title>Kas Kelas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <!-- Custom CSS -->
    <style>
    .siswa-item {
        cursor: pointer;
        transition: 0.2s;
    }

    .siswa-item:hover {
        background-color: #e9f2ff;
    }

    #opsiJumlah button {
    border-radius: 10px;
    flex: 1;
    }

    .modal-content {
    border-radius: 16px;
    }

    .modal-header {
        padding-bottom: 0;
    }

    .modal-body input,
    .modal-body select,
    .modal-body textarea {
        border-radius: 10px;
    }
    </style>
</head>

<body class="bg-light">
<div class="d-flex">

    <!-- SIDEBAR -->
    <aside class="bg-white border-end p-3 d-flex flex-column position-sticky top-0 vh-100" style="width:230px;">

        <!-- HEADER -->
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center"
                 style="width:44px; height:44px;">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <h5 class="text-primary mb-0">E Kas Seven</h5>
        </div>

        <!-- MENU -->
        <ul class="nav nav-pills flex-column gap-2 mb-auto">

            <li class="nav-item">
                <a href="dashboard.php" class="nav-link text-dark d-flex align-items-center gap-3 fs-5">
                    <i class="fa-solid fa-house"></i>
                    Dashboard
                </a>
            </li>

            <li class="nav-item">
                <a href="datamurid.php" class="nav-link text-dark d-flex align-items-center gap-3 fs-5">
                    <i class="fa-solid fa-users"></i>
                    Data Murid
                </a>
            </li>

            <li class="nav-item">
                <a href="kasmasuk.php" class="nav-link text-dark d-flex align-items-center gap-3 fs-5">
                    <i class="fa-solid fa-arrow-trend-up"></i>
                    Kas Masuk
                </a>
            </li>

            <li class="nav-item">
                <a href="kaskeluar.php" class="nav-link text-dark d-flex align-items-center gap-3 fs-5">
                    <i class="fa-solid fa-arrow-trend-down"></i>
                    Kas Keluar
                </a>
            </li>

            <li class="nav-item">
                <a href="status.php" class="nav-link active d-flex align-items-center gap-3 fs-5">
                    <i class="fa-regular fa-circle-check"></i>
                    Status Pembayaran
                </a>
            </li>

            <li class="nav-item">
                <a href="arus.php" class="nav-link text-dark d-flex align-items-center gap-3 fs-5">
                    <i class="fa-solid fa-chart-column"></i>
                    Arus Kas
                </a>
            </li>

            <li class="nav-item">
                <a href="laporan.php" class="nav-link text-dark d-flex align-items-center gap-3 fs-5">
                    <i class="fa-regular fa-file-lines"></i>
                    Laporan
                </a>
            </li>
        </ul>

        <!-- FOOTER BUTTON -->
        <div class="d-grid gap-3 mt-4">
            <button class="btn btn-primary rounded-3"
            data-bs-toggle="modal"
            data-bs-target="#modalTransaksi">
            <i class="fa-solid fa-plus me-2"></i>
            Tambah Transaksi
            </button>

            <a href="logout.php" 
            onclick="return confirm('Yakin ingin logout?')" 
            class="btn btn-danger rounded-3">
            <i class="fa-solid fa-right-from-bracket me-2"></i>
            Keluar
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-fill p-4">

    <!-- HEADER -->
    <div class="mb-4">
        <h2 class="fw-semibold">Status Pembayaran Kas Kelas</h2>
        <p class="text-muted mb-0">Pantau pembayaran uang kas siswa</p>
    </div>

    <!-- BUTTON INPUT -->
    <div class="mb-4">
        <button class="btn btn-primary w-100 rounded-3 py-2" data-bs-toggle="modal" data-bs-target="#modalBayar">
        <i class="fa-solid fa-dollar-sign me-2"></i>
        Input Pembayaran Kas
        </button>
    </div>

    <!-- SUMMARY CARD -->
    <div class="row g-4 mb-4">

        <!-- LUNAS -->
        <div class="col-md-4">
            <div class="card bg-success-subtle border-success rounded-4 text-center p-4">
                <div class="bg-success rounded-circle mx-auto mb-3"
                     style="width:50px; height:50px;"></div>
                <h2 class="text-success"><?= $lunas ?></h2>
            </div>
        </div>

        <!-- SEBAGIAN -->
        <div class="col-md-4">
            <div class="card bg-warning-subtle border-warning rounded-4 text-center p-4">
                <div class="bg-warning rounded-circle mx-auto mb-3"
                     style="width:50px; height:50px;"></div>
                <h2 class="text-warning"><?= $sebagian ?></h2>
            </div> 
        </div>

        <!-- BELUM BAYAR -->
        <div class="col-md-4">
            <div class="card bg-danger-subtle border-danger rounded-4 text-center p-4">
                <div class="bg-danger rounded-circle mx-auto mb-3"
                     style="width:50px; height:50px;"></div>
                <h2 class="text-danger"><?= $belum ?></h2>
            </div>
        </div>

    </div>

    <!-- STATUS LIST -->
    <div class="card shadow-sm rounded-4">
        <div class="card-body p-4">
            <h5 class="fw-semibold mb-4">Status Pembayaran Kas</h5>
            <?php foreach($rows as $row): 
            $target = $row['target'] ?? 10000;
            $dibayar = $row['dibayar'];
            $persen = $target > 0 ? ($dibayar / $target) * 100 : 0;
            $persen = min($persen, 100);
            if ($row['status'] == 'lunas') {
                $persen = 100;
            }
            $kekurangan = $target - $dibayar;

            // warna & status
            if ($row['status'] == 'lunas') {
                $warna = 'success';
                $label = 'Lunas';
            } elseif ($row['status'] == 'sebagian') {
                $warna = 'warning';
                $label = 'Sebagian';
            } else {
                $warna = 'danger';
                $label = 'Belum Bayar';
            }
            ?>
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="d-flex gap-3">
                        <div class="bg-<?= $warna ?>-subtle text-<?= $warna ?> rounded-circle d-flex align-items-center justify-content-center"
                        style="width:45px; height:45px;">
                        <i class="fa-solid fa-user"></i>
                        </div>
                        <div>
                        <h6 class="mb-1"><?= $row['nama_siswa'] ?></h6>
                        <small class="text-muted">NISN: <?= $row['id_siswa'] ?></small>
                        </div>
                    </div>
                    <span class="badge bg-<?= $warna ?>-subtle text-<?= $warna ?>">
                        <i class="fa-solid fa-check-circle me-1"></i> <?= $label ?>
                    </span>
                    </div>

                    <!-- DETAIL -->
                    <div class="mt-3 d-flex justify-content-between small text-muted">
                        <span>Dibayar: Rp <?= number_format($dibayar,0,',','.') ?></span>
                        <span>Target: Rp <?= number_format($target,0,',','.') ?></span>
                    </div>

                    <div class="progress mt-2" style="height:8px;">
                        <div class="progress-bar bg-<?= $warna ?>"
                            style="width:<?= min($persen,100) ?>%"></div>
                    </div>

                    <div class="text-end small mt-1">
                    <?= round($persen) ?>% Terbayar

                    <?php if($row['status'] == 'sebagian'): ?>
                        <br>
                        <span class="text-warning">
                            Kurang Rp <?= number_format($kekurangan,0,',','.') ?>
                        </span>
                    <?php endif; ?>
                    </div>
                    </div>
        <?php endforeach; ?>  
        </div>
    </div>
    </main>
    </div>

<div class="modal fade" id="modalBayar" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4">
      <form method="POST" action="proses_transaksi.php">
      
      <div class="modal-header d-flex justify-content-between align-items-start">
      <div>
      <h5 class="modal-title mb-0">Input Pembayaran Kas Siswa</h5>
      <small class="text-muted">Catat pembayaran iuran kas dari siswa</small>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">            
        <!-- Pilih Siswa -->
        <div class="mb-3 position-relative">
            <label class="form-label">Pilih Siswa</label>

            <!-- Trigger -->
            <div class="form-control d-flex justify-content-between align-items-center"
                id="dropdownBtn" style="cursor:pointer;">
                <span id="selectedText">Pilih siswa...</span>
                <i class="fa-solid fa-chevron-down"></i>
            </div>
            
            <!-- Info iuran -->
            <div id="infoIuran" class="mb-3 d-none mt-3">
            <div class="form-control d-flex justify-content-between bg-primary-subtle text-primary">
            <span>Iuran Mingguan:</span>
            <strong id="nominalIuran">Rp 10.000</strong>
            </div>
            </div>

            <!-- Dropdown -->
            <div id="dropdownList" class="card shadow-sm mt-2 d-none position-absolute w-100">
                <div class="list-group list-group-flush">
                    <?php
                    $siswa = mysqli_query($koneksi_db, "SELECT * FROM tb_siswa");
                    while($s = mysqli_fetch_assoc($siswa)) :
                    ?>
                    <div class="list-group-item siswa-item"
                        data-id="<?= $s['id_siswa'] ?>"
                        data-nama="<?= $s['nama_siswa'] ?>">

                        <strong><?= $s['nama_siswa'] ?></strong><br>
                        <small class="text-muted">
                            NIS: <?= $s['id_siswa'] ?> • Rp 10.000/minggu
                        </small>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <div id="alertTunggakan" class="alert alert-primary d-none mt-2">
                <strong>Tunggakan:</strong>
                <div id="isiTunggakan"></div>
            </div>

            <!-- Hidden input -->
            <input type="hidden" name="id_siswa" id="id_siswa" required>
            <input type="hidden" name="jenis" value="bayar">
            <input type="hidden" name="keterangan" value="Iuran Kas">   
        </div>

        <!-- Jumlah -->
        <div class="mb-3">
          <label class="form-label">Jumlah Pembayaran (Rp)</label>
          <input type="number" name="jumlah" id="inputJumlah" class="form-control" required>
        </div>

        <div id="opsiJumlah" class="d-flex gap-2 d-none mb-2">
        <button type="button" class="btn btn-outline-secondary btn-sm pilih-jumlah" data-value="10000">Penuh</button>
        <button type="button" class="btn btn-outline-secondary btn-sm pilih-jumlah" data-value="5000">Setengah</button>
        <button type="button" class="btn btn-outline-secondary btn-sm pilih-jumlah" data-value="2000">2.000</button>
        </div>

        <!-- Tanggal -->
        <div class="mb-3">
          <label class="form-label">Tanggal Pembayaran</label>
          <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>

      </div>

      <div class="modal-footer">
        <button type="submit" name="simpan" class="btn btn-primary w-100">
          Simpan Pembayaran
        </button>
      </div>
      </form>

    </div>
  </div>
</div>

<div class="modal fade" id="modalTransaksi" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4">

      <!-- HEADER -->
      <div class="modal-header border-0">
        <h5 class="modal-title fw-semibold">Tambah Transaksi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <!-- BODY -->
      <form method="POST" action="proses_transaksi.php">
        <div class="modal-body">

    <!-- JENIS -->
    <div class="mb-3">
        <label>Jenis</label>
        <select name="jenis" class="form-control" required>
            <option value="">-- Pilih --</option>
            <option value="bayar">Pemasukan</option>
            <option value="pengeluaran">Pengeluaran</option>
        </select>
    </div>

    <!-- KETERANGAN -->
    <div class="mb-3">
        <label>Keterangan</label>
        <textarea name="keterangan" class="form-control" required></textarea>
    </div>

    <!-- JUMLAH -->
    <div class="mb-3">
        <label>Jumlah</label>
        <input type="number" name="jumlah" class="form-control" required>
    </div>

    <!-- TANGGAL -->
    <div class="mb-3">
        <label>Tanggal</label>
        <input type="date" name="tanggal" class="form-control" required>
    </div>

    <button type="submit" name="simpan" class="btn btn-primary">
        Simpan
    </button>
    </form>
    </div>
    </div>
</div>

<script>
const btn = document.getElementById('dropdownBtn');
const list = document.getElementById('dropdownList');
const text = document.getElementById('selectedText');
const input = document.getElementById('id_siswa');
const infoIuran = document.getElementById('infoIuran');
const opsiJumlah = document.getElementById('opsiJumlah');
const inputJumlah = document.getElementById('inputJumlah');

const alertBox = document.getElementById('alertTunggakan');
const isiTunggakan = document.getElementById('isiTunggakan');

const modalBayar = document.getElementById('modalBayar');


// ================= RESET FUNCTION =================
function resetModal() {
    text.innerText = "Pilih siswa...";
    input.value = "";

    inputJumlah.value = "";

    infoIuran.classList.add('d-none');
    opsiJumlah.classList.add('d-none');

    alertBox.classList.add('d-none');
    isiTunggakan.innerHTML = "";

    // reset tombol jumlah
    document.querySelectorAll('.pilih-jumlah').forEach(btn => {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-outline-secondary');
    });
}


// ================= DROPDOWN =================
btn.addEventListener('click', () => {
    const isOpen = !list.classList.contains('d-none');

    list.classList.toggle('d-none');

    // kalau dibuka ulang → reset
    if (!isOpen) {
        resetModal();
    }
});

// klik luar = tutup + reset
document.addEventListener('click', function(e) {
    if (!btn.contains(e.target) && !list.contains(e.target)) {
        list.classList.add('d-none');
    }
});


// ================= PILIH SISWA + FETCH =================
document.querySelectorAll('.siswa-item').forEach(item => {
    item.addEventListener('click', () => {
        const nama = item.dataset.nama;
        const id = item.dataset.id;

        text.innerText = nama;
        input.value = id;
        list.classList.add('d-none');

        infoIuran.classList.remove('d-none');
        opsiJumlah.classList.remove('d-none');

        // ambil data tunggakan
        fetch('get_tunggakan.php?id_siswa=' + id)
        .then(res => res.json())
        .then(res => {
            if(res.status === 'success'){

                if(res.total > 0){
                    let html = `
                        Total: <strong>Rp ${res.total.toLocaleString('id-ID')}</strong><br>
                        <strong>Mohon Lunaskan Tunggakan Terlebih Dahulu</strong><br>
                        <small>
                    `;

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


// ================= PILIH JUMLAH =================
document.querySelectorAll('.pilih-jumlah').forEach(button => {
    button.addEventListener('click', function() {

        const value = this.getAttribute('data-value');
        inputJumlah.value = value;

        // reset semua tombol
        document.querySelectorAll('.pilih-jumlah').forEach(btn => {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-outline-secondary');
        });

        // aktifkan tombol yg dipilih
        this.classList.remove('btn-outline-secondary');
        this.classList.add('btn-primary');
    });
});


// ================= MODAL CLOSE =================
modalBayar.addEventListener('hidden.bs.modal', function () {
    resetModal();
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
