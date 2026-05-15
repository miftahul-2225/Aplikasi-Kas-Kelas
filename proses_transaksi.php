<?php
session_start();
require_once 'config/koneksi.php';

// ============================
// VALIDASI LOGIN
// ============================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'bendahara') {
    header("Location: login.php");
    exit();
}

// ============================
// FUNCTION AUTO PERIODE
// ============================
function getPeriode($koneksi_db, $tanggal){

    $mulai   = date('Y-m-d', strtotime('monday this week', strtotime($tanggal)));
    $selesai = date('Y-m-d', strtotime('sunday this week', strtotime($tanggal)));

    $cek  = mysqli_query($koneksi_db, "
        SELECT * FROM tb_periode
        WHERE tanggal_mulai = '$mulai'
        AND tanggal_selesai = '$selesai'
        LIMIT 1
    ");
    $data = mysqli_fetch_assoc($cek);

    if($data){
        return $data;
    } else {
        $minggu_ke = ceil(date('j', strtotime($tanggal)) / 7);
        $bulan     = date('F', strtotime($tanggal));
        $tahun     = date('Y', strtotime($tanggal));
        $nama      = "Minggu ke-$minggu_ke bulan $bulan $tahun";

        mysqli_query($koneksi_db, "
            INSERT INTO tb_periode 
            (nama_periode, minggu_ke, tahun, tanggal_mulai, tanggal_selesai, status, target)
            VALUES
            ('$nama', '$minggu_ke', '$tahun', '$mulai', '$selesai', 'aktif', 10000)
        ");

        $id = mysqli_insert_id($koneksi_db);

        return [
            'id_periode'      => $id,
            'tanggal_mulai'   => $mulai,
            'tanggal_selesai' => $selesai
        ];
    }
}

// ============================
// PROSES SIMPAN
// ============================
$id_user  = 1;
$id_siswa = $_POST['id_siswa'] ?? '';
$ada_siswa = $_POST['ada_siswa'] ?? '0'; // dari checkbox modal

if(isset($_POST['simpan'])){

    $jenis      = $_POST['jenis']      ?? '';
    $keterangan = $_POST['keterangan'] ?? '';
    $jumlah     = $_POST['jumlah']     ?? 0;
    $tanggal    = $_POST['tanggal']    ?? '';

    // ============================
    // VALIDASI DASAR
    // ============================
    if($jenis == '' || $jumlah <= 0 || $tanggal == ''){
        echo "<script>alert('Data tidak lengkap!'); window.history.back();</script>";
        exit();
    }

    if($jenis != 'bayar' && $jenis != 'pengeluaran'){
        echo "<script>alert('Jenis tidak valid!'); window.history.back();</script>";
        exit();
    }

    // Siswa wajib dipilih HANYA kalau pemasukan + checkbox dicentang
    if($jenis == 'bayar' && $ada_siswa == '1' && $id_siswa == ''){
        echo "<script>alert('Pilih siswa dulu!'); window.history.back();</script>";
        exit();
    }

    // VALIDASI HARI (Senin–Jumat)
    $hari = date('N', strtotime($tanggal));
    if($hari > 5){
        echo "<script>alert('Hanya boleh input Senin - Jumat!'); window.history.back();</script>";
        exit();
    }

    // ============================
    // AMANKAN INPUT
    // ============================
    $jenis      = mysqli_real_escape_string($koneksi_db, $jenis);
    $keterangan = mysqli_real_escape_string($koneksi_db, $keterangan);
    $jumlah     = (int)$jumlah;
    $tanggal    = mysqli_real_escape_string($koneksi_db, $tanggal);
    $id_siswa   = mysqli_real_escape_string($koneksi_db, $id_siswa);

    // ============================
    // AMBIL PERIODE OTOMATIS
    // ============================
    $periode    = getPeriode($koneksi_db, $tanggal);
    $id_periode = $periode['id_periode'];

    // ============================
    // FORMAT KETERANGAN
    // ============================
    if($jenis == 'bayar'){
        $ket = "Pemasukan - $keterangan";
    } else {
        $ket = "Pengeluaran - $keterangan";
    }

    // ============================
    // VALIDASI MAKSIMAL PEMBAYARAN
    // Hanya berlaku kalau pemasukan terkait siswa
    // ============================
    if($jenis == 'bayar' && $ada_siswa == '1' && $id_siswa != ''){

        $q = mysqli_query($koneksi_db, "
            SELECT COALESCE(SUM(jumlah),0) as total 
            FROM tb_transaksi
            WHERE id_siswa   = '$id_siswa'
            AND id_periode = '$id_periode'
            AND jenis      = 'bayar'
        ");
        $total_sudah = mysqli_fetch_assoc($q)['total'];

        $q_target = mysqli_query($koneksi_db, "
            SELECT target FROM tb_periode WHERE id_periode = '$id_periode'
        ");
        $target = mysqli_fetch_assoc($q_target)['target'] ?? 10000;

        $sisa = $target - $total_sudah;

        if($sisa <= 0){
            echo "<script>alert('Siswa sudah lunas!'); window.history.back();</script>";
            exit();
        }

        if($jumlah > $sisa){
            echo "<script>
                alert('Maks pembayaran hanya Rp " . number_format($sisa,0,',','.') . " lagi!');
                window.history.back();
            </script>";
            exit();
        }
    }

    // ============================
    // VALIDASI SALDO (PENGELUARAN)
    // ============================
    if($jenis == 'pengeluaran'){

        $q_masuk = mysqli_query($koneksi_db, "
            SELECT SUM(jumlah) as total FROM tb_transaksi WHERE jenis='bayar'
        ");
        $total_masuk = mysqli_fetch_assoc($q_masuk)['total'] ?? 0;

        $q_keluar = mysqli_query($koneksi_db, "
            SELECT SUM(jumlah) as total FROM tb_transaksi WHERE jenis='pengeluaran'
        ");
        $total_keluar = mysqli_fetch_assoc($q_keluar)['total'] ?? 0;

        $saldo = $total_masuk - $total_keluar;

        if($jumlah > $saldo){
            echo "<script>
                alert('Saldo tidak mencukupi! Sisa saldo: Rp " . number_format($saldo,0,',','.') . "');
                window.history.back();
            </script>";
            exit();
        }
    }

    // ============================
    // TENTUKAN id_siswa UNTUK INSERT
    // - pengeluaran          → NULL
    // - pemasukan + siswa    → id siswa
    // - pemasukan tanpa siswa → NULL
    // ============================
    if($jenis == 'pengeluaran' || $id_siswa == ''){
        $id_siswa_fix = "NULL";
    } else {
        $id_siswa_fix = "'$id_siswa'";
    }

    // Insert ke database 
    // Menyimpan data transaksi baru ke dalam tabel tb_transaksi
    // dengan data id_siswa, id_user, id_periode, jenis transaksi,
    // jumlah, tanggal, dan keterangan yang telah divalidasi sebelumnya

    $query = mysqli_query($koneksi_db, "
        INSERT INTO tb_transaksi 
        (id_siswa, id_user, id_periode, jenis, jumlah, tanggal, keterangan)
        VALUES 
        ($id_siswa_fix, '$id_user', '$id_periode', '$jenis', '$jumlah', '$tanggal', '$ket')
    ");

    if($query){
        echo "<script>
            alert('Transaksi berhasil!');
            window.location='dashboard.php';
        </script>";
    } else {
        echo "Error: " . mysqli_error($koneksi_db);
    }
}
?>