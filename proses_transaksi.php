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

    $mulai = date('Y-m-d', strtotime('monday this week', strtotime($tanggal)));
    $selesai = date('Y-m-d', strtotime('sunday this week', strtotime($tanggal)));

    // cek periode
    $cek = mysqli_query($koneksi_db, "
        SELECT * FROM tb_periode
        WHERE tanggal_mulai = '$mulai'
        AND tanggal_selesai = '$selesai'
        LIMIT 1
    ");

    $data = mysqli_fetch_assoc($cek);

    if($data){
        return $data;
    } else {

        // bikin nama periode (Minggu ke X bulan)
        $minggu_ke = ceil(date('j', strtotime($tanggal)) / 7);
        $bulan = date('F', strtotime($tanggal));
        $tahun = date('Y', strtotime($tanggal));

        $nama = "Minggu ke-$minggu_ke bulan $bulan $tahun";

        mysqli_query($koneksi_db, "
            INSERT INTO tb_periode 
            (nama_periode, minggu_ke, tahun, tanggal_mulai, tanggal_selesai, status, target)
            VALUES
            ('$nama', '$minggu_ke', '$tahun', '$mulai', '$selesai', 'aktif', 10000)
        ");

        $id = mysqli_insert_id($koneksi_db);

        return [
            'id_periode' => $id,
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $selesai
        ];
    }
}

// ============================
// PROSES SIMPAN
// ============================
$id_user = 1; // bendahara tetap
$id_siswa = $_POST['id_siswa'] ?? '';

if(isset($_POST['simpan'])){

    $jenis       = $_POST['jenis'] ?? '';
    $keterangan  = $_POST['keterangan'] ?? '';
    $jumlah      = $_POST['jumlah'] ?? 0;
    $tanggal     = $_POST['tanggal'] ?? '';

    // ============================
    // VALIDASI
    // ============================
    if($jenis == '' || $jumlah <= 0 || $tanggal == ''){
    echo "<script>alert('Data tidak lengkap!'); window.history.back();</script>";
    exit();
    }

    if($jenis == 'bayar' && $id_siswa == ''){
        echo "<script>alert('Pilih siswa dulu!'); window.history.back();</script>";
        exit();
    }

    if($jenis != 'bayar' && $jenis != 'pengeluaran'){
    echo "<script>
        alert('Jenis tidak valid!');
        window.history.back();
    </script>";
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

    // ============================
    // AMBIL PERIODE OTOMATIS
    // ============================
    $periode = getPeriode($koneksi_db, $tanggal);
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
    // VALIDASI MAKSIMAL PEMBAYARAN (ANTI LEBIH DARI TARGET)
    // ============================
    if($jenis == 'bayar'){

    // ambil total yang sudah dibayar siswa di periode ini
    $q = mysqli_query($koneksi_db, "
        SELECT COALESCE(SUM(jumlah),0) as total 
        FROM tb_transaksi
        WHERE id_siswa = '$id_siswa'
        AND id_periode = '$id_periode'
        AND jenis = 'bayar'
    ");

    $total_sudah = mysqli_fetch_assoc($q)['total'];

    // ambil target dari periode
    $q_target = mysqli_query($koneksi_db, "
        SELECT target FROM tb_periode WHERE id_periode = '$id_periode'
    ");

    $target = mysqli_fetch_assoc($q_target)['target'] ?? 10000;

    // hitung sisa
    $sisa = $target - $total_sudah;

    // kalau sudah lunas
    if($sisa <= 0){
        echo "<script>
            alert('Siswa sudah lunas!');
            window.history.back();
        </script>";
        exit();
    }

    // kalau input melebihi sisa
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

    // ambil total pemasukan
    $q_masuk = mysqli_query($koneksi_db, "
        SELECT SUM(jumlah) as total 
        FROM tb_transaksi 
        WHERE jenis='bayar'
    ");
    $total_masuk = mysqli_fetch_assoc($q_masuk)['total'] ?? 0;

    // ambil total pengeluaran
    $q_keluar = mysqli_query($koneksi_db, "
        SELECT SUM(jumlah) as total 
        FROM tb_transaksi 
        WHERE jenis='pengeluaran'
    ");
    $total_keluar = mysqli_fetch_assoc($q_keluar)['total'] ?? 0;

    // hitung saldo
    $saldo = $total_masuk - $total_keluar;

    // cek saldo cukup atau tidak
    if($jumlah > $saldo){
        echo "<script>
            alert('Saldo tidak mencukupi! Sisa saldo: Rp " . number_format($saldo,0,',','.') . "');
            window.history.back();
        </script>";
        exit();
    }
    }

    
    // ============================
    // INSERT DATABASE
    // ============================
    $id_siswa_fix = ($jenis == 'pengeluaran') ? "NULL" : "'$id_siswa'";

    $query = mysqli_query($koneksi_db, "
        INSERT INTO tb_transaksi 
        (id_siswa, id_user, id_periode, jenis, jumlah, tanggal, keterangan)
        VALUES 
        ($id_siswa_fix, '$id_user', '$id_periode', '$jenis', '$jumlah', '$tanggal', '$ket')
    ");

    if($query){
        echo "<script>
            alert('Transaksi berhasil!');
            window.location='arus.php';
        </script>";
    } else {
        echo "Error: " . mysqli_error($koneksi_db);
    }
}
?>