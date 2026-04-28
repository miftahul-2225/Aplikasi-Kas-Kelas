    <?php
    require_once 'config/koneksi.php';

    // ambil bulan & tahun dari URL
    $bulan = $_GET['bulan'] ?? date('m');
    $tahun = $_GET['tahun'] ?? date('Y');
    $limit = $_GET['limit'] ?? 10; // default 10
    $filter = $_GET['filter'] ?? 'all'; // all, lunas, sebagian, belum
    $sort = $_GET['sort'] ?? 'nama'; // nama, bayar_desc, bayar_asc

    // query sesuai bulan
    $periode = mysqli_query($koneksi_db, "
    SELECT * FROM tb_periode 
    WHERE status='aktif'
    AND tanggal_mulai <= LAST_DAY('$tahun-$bulan-01')
    AND tanggal_selesai >= '$tahun-$bulan-01'
    ORDER BY tanggal_mulai ASC
    ");

    // ambil jumlah siswa
    $q_siswa = mysqli_query($koneksi_db, "SELECT COUNT(*) as total FROM tb_siswa");
    $total_siswa = mysqli_fetch_assoc($q_siswa)['total'];

    // total uang terkumpul
    $q_total = mysqli_query($koneksi_db, "
        SELECT COALESCE(SUM(jumlah),0) as total 
        FROM tb_transaksi 
        WHERE jenis='bayar'
        AND MONTH(tanggal) = '$bulan'
        AND YEAR(tanggal) = '$tahun'
    ");

    $total_terkumpul = mysqli_fetch_assoc($q_total)['total'];

    // hitung target bulanan
    $total_target_bulanan = 0;
    $periode_list = [];

    while($p = mysqli_fetch_assoc($periode)){
        $periode_list[] = $p;
        $target = $p['target'] ?? 10000;
        $total_target_bulanan += $target * $total_siswa;
    }

    // hitung tunggakan
    $tunggakan = $total_target_bulanan - $total_terkumpul;
    if($tunggakan < 0) $tunggakan = 0;
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

        
        <style>
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
                    <a href="status.php" class="nav-link text-dark d-flex align-items-center gap-3 fs-5">
                        <i class="fa-regular fa-circle-check"></i>
                        Status Pembayaran
                    </a>
                </li>

                <li class="nav-item">
                    <a href="arus.php" class="nav-link active d-flex align-items-center gap-3 fs-5">
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
                <h2 class="fw-semibold">Arus Kas dan Kalender Pemantauan siswa</h2>
                <p class="text-muted mb-0">Pantau pembayaran uang kas dan tagihan siswa</p>
            </div>
    
            <!-- KALENDER -->
            <div class="p-4 mb-4 shadow-sm rounded-4" style="background:#eaf2ff;">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="bg-primary text-white rounded-3 p-3">
                        <i class="bi bi-calendar"></i>
                    </div>
                    <div>
                        <h4 class="mb-0">Kalender Pembayaran Kas</h4>
                        <small class="text-primary">Rp 10.000 per minggu</small>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <button id="prevMonth" class="btn btn-light rounded-pill">
                        <i class="bi bi-chevron-left"></i> Sebelumnya
                    </button>

                    <div class="text-center">
                        <h5 class="mb-0" id="monthYear"></h5>
                        <small class="text-primary" id="currentLabel"></small>
                    </div>

                    <button id="nextMonth" class="btn btn-light rounded-pill">
                        Berikutnya <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>

            <!-- STATISTIK  -->
            <div class="row justify-content-center g-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-success-subtle text-success p-3 rounded-3">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <div>
                            <div class="text-muted">Total Terkumpul</div>
                            <h5 class="text-success mb-0">Rp <?= number_format($total_terkumpul,0,',','.') ?></h5>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary-subtle text-primary p-3 rounded-3">
                            <i class="bi bi-people"></i>
                        </div>
                        <div>
                            <div class="text-muted">Target Bulanan</div>
                            <h5 class="text-primary mb-0">Rp <?= number_format($total_target_bulanan,0,',','.') ?></h5>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-danger-subtle text-danger p-3 rounded-3">
                        <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <div>
                            <div class="text-muted">Tunggakan Bulanan</div>
                            <h5 class="text-danger mb-0">Rp <?= number_format($tunggakan,0,',','.') ?></h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-4">
            <?php
            // ambil semua pembayaran sekaligus
            $data_bayar = [];

            $q_all = mysqli_query($koneksi_db, "
            SELECT id_periode, id_siswa, SUM(jumlah) as total
            FROM tb_transaksi
            WHERE jenis='bayar'
            AND MONTH(tanggal) = '$bulan'
            AND YEAR(tanggal) = '$tahun'
            GROUP BY id_periode, id_siswa
            ");

            while($d = mysqli_fetch_assoc($q_all)){
                $data_bayar[$d['id_periode']][$d['id_siswa']] = $d['total'];
            }
            ?>

        <?php foreach($periode_list as $p): 

        $id_periode = $p['id_periode'];

        // total pembayaran per periode
        $q = mysqli_query($koneksi_db, "
            SELECT COALESCE(SUM(jumlah),0) as total
            FROM tb_transaksi
            WHERE id_periode='$id_periode'
            AND jenis='bayar'
        ");

        $total = 0; 
        if(isset($data_bayar[$id_periode])){
            $total = array_sum($data_bayar[$id_periode]);
        }
        $target_total = $p['target'] * $total_siswa;
        $persen = $target_total > 0 ? ($total / $target_total) * 100 : 0;
        ?>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <!-- HEADER -->
            <div class="p-4 text-white" style="background:linear-gradient(135deg,#4f8cff,#1e40af);">

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0 fw-semibold"><?= $p['nama_periode'] ?></h5>
                    <span class="badge bg-white text-primary small">
                        <?= date('d M', strtotime($p['tanggal_mulai'])) ?> -
                        <?= date('d M', strtotime($p['tanggal_selesai'])) ?>
                    </span>
                </div>

                <!-- NOMINAL -->
                <div class="d-flex justify-content-between align-items-end">
                    <div>
                        <div class="small opacity-75">Terkumpul</div>
                        <h5 class="mb-0">
                            Rp <?= number_format($total,0,',','.') ?>
                        </h5>
                    </div>

                    <div class="text-end">
                        <div class="small opacity-75">Target</div>
                        <h6 class="mb-0">
                            Rp <?= number_format($target_total,0,',','.') ?>
                        </h6>
                    </div>
                </div>

                <!-- PROGRESS -->
                <div class="mt-3">
                    <div class="progress rounded-pill" style="height:10px;">
                        <div class="progress-bar bg-success progress-bar-striped progress-bar-animated"
                            style="width:<?= $persen ?>%">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-1 small">
                        <span><?= round($persen) ?>%</span>
                        <span><?= number_format($target_total - $total,0,',','.') ?> kurang</span>
                    </div>
                </div>
                </div>

                <!-- LIST SISWA -->
                <div class="p-3">
                    <div class="d-flex gap-2 mb-3">
                    <select onchange="updateFilter()" id="filterStatus" class="form-select w-auto">
                        <option value="all">Semua</option>
                        <option value="lunas">Lunas</option>
                        <option value="sebagian">Sebagian</option>
                        <option value="belum">Belum</option>
                    </select>

                    <select onchange="updateFilter()" id="limitData" class="form-select w-auto">
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="33">33</option>
                    </select>

                    <select onchange="updateFilter()" id="sortData" class="form-select w-auto">
                        <option value="nama">Nama</option>
                        <option value="bayar_desc">Terbesar</option>
                        <option value="bayar_asc">Terkecil</option>
                    </select>
                    </div>
                    <?php
                    $data_siswa = [];

                    // ambil semua siswa dulu
                    $siswa_loop = mysqli_query($koneksi_db, "SELECT * FROM tb_siswa");
                    while($s = mysqli_fetch_assoc($siswa_loop)){

                        $bayar = $data_bayar[$id_periode][$s['id_siswa']] ?? 0;
                        $target = $p['target'] ?? 10000;

                        // tentukan status
                        if($bayar >= $target){
                            $status = "lunas";
                            $warna = "success";
                            $icon = "check-circle";
                        } elseif($bayar > 0){
                            $status = "sebagian";
                            $warna = "warning";
                            $icon = "exclamation-circle";
                        } else {
                            $status = "belum";
                            $warna = "danger";
                            $icon = "times-circle";
                        }

                        // FILTER
                        if($filter != 'all' && $status != $filter){
                            continue;
                        }

                        // simpan ke array
                        $s['bayar'] = $bayar;
                        $s['status'] = $status;
                        $s['warna'] = $warna;
                        $s['icon'] = $icon;

                        $data_siswa[] = $s;
                    }
                    // SORT
                    if($sort == 'bayar_desc'){
                        usort($data_siswa, fn($a,$b) => $b['bayar'] <=> $a['bayar']);
                    } elseif($sort == 'bayar_asc'){
                        usort($data_siswa, fn($a,$b) => $a['bayar'] <=> $b['bayar']);
                    } else {
                        usort($data_siswa, fn($a,$b) => strcmp($a['nama_siswa'], $b['nama_siswa']));
                    }
                    // LIMIT
                    $data_siswa = array_slice($data_siswa, 0, $limit);
                    ?>
                    <?php foreach($data_siswa as $s): ?>
                    <div class="d-flex justify-content-between align-items-center border rounded-3 p-2 mb-2 bg-light">
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-<?= $s['warna'] ?>-subtle text-<?= $s['warna'] ?> rounded-circle p-2">
                                <i class="fa fa-<?= $s['icon'] ?>"></i>
                            </div>

                            <div>
                                <div class="fw-semibold"><?= $s['nama_siswa'] ?></div>
                                <small class="text-muted">
                                    Rp <?= number_format($s['bayar'],0,',','.') ?>
                                </small>
                            </div>
                        </div>

                        <span class="badge bg-<?= $s['warna'] ?> text-white px-3">
                            <?= ucfirst($s['status']) ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                    </div>               
                </div>
        </div>
        <?php endforeach; ?>
        </div>

        </main>
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- SCRIPT KALENDER AKTIF -->
    <script>
        const monthYear = document.getElementById("monthYear");
        const currentLabel = document.getElementById("currentLabel");
        const prevBtn = document.getElementById("prevMonth");
        const nextBtn = document.getElementById("nextMonth");

        const monthNames = [
            "Januari", "Februari", "Maret", "April",
            "Mei", "Juni", "Juli", "Agustus",
            "September", "Oktober", "November", "Desember"
        ];

        let currentDate = new Date(
        <?= $tahun ?>,
        <?= $bulan ?> - 1
        );

        function renderCalendar() {
            const month = currentDate.getMonth();
            const year = currentDate.getFullYear();
            monthYear.textContent = monthNames[month] + " " + year;
            const today = new Date();
            if (month === today.getMonth() && year === today.getFullYear()) {
                currentLabel.textContent = "Bulan Ini";
            } else {
                currentLabel.textContent = "";
            }
        }

        function updateURL() {
        const month = currentDate.getMonth() + 1;
        const year = currentDate.getFullYear();
        window.location.href = `?bulan=${month}&tahun=${year}`;
        }

        prevBtn.addEventListener("click", function () {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
            updateURL();
        });

        nextBtn.addEventListener("click", function () {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
            updateURL();
        });

        renderCalendar();

        function updateFilter(){
        const filter = document.getElementById("filterStatus").value;
        const limit = document.getElementById("limitData").value;
        const sort = document.getElementById("sortData").value;

        const url = new URL(window.location.href);
        url.searchParams.set("filter", filter);
        url.searchParams.set("limit", limit);
        url.searchParams.set("sort", sort);

        window.location.href = url.toString();
        }

        document.getElementById("filterStatus").value = "<?= $filter ?>";
        document.getElementById("limitData").value = "<?= $limit ?>";
        document.getElementById("sortData").value = "<?= $sort ?>";
    </script>
    </body>
    </html>
