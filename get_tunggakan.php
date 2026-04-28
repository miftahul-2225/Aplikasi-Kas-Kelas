<?php
require_once 'config/koneksi.php';

$id_siswa = $_GET['id_siswa'] ?? '';

if($id_siswa == ''){
    echo json_encode(['status' => 'error']);
    exit;
}

// ambil semua periode
$query = mysqli_query($koneksi_db, "
    SELECT p.id_periode, p.nama_periode, p.target,
    COALESCE(SUM(t.jumlah),0) as dibayar
    FROM tb_periode p
    LEFT JOIN tb_transaksi t 
        ON p.id_periode = t.id_periode 
        AND t.id_siswa = '$id_siswa'
        AND t.jenis = 'bayar'
    GROUP BY p.id_periode
    ORDER BY p.tanggal_mulai ASC
");

$data = [];
$total_tunggakan = 0;

while($row = mysqli_fetch_assoc($query)){
    $kurang = $row['target'] - $row['dibayar'];

    if($kurang > 0){
        $data[] = [
            'periode' => $row['nama_periode'],
            'kurang' => $kurang
        ];
        $total_tunggakan += $kurang;
    }
}

echo json_encode([
    'status' => 'success',
    'total' => $total_tunggakan,
    'detail' => $data
]);