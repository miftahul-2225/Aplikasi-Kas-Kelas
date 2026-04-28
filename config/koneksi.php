<?php 
$server = "localhost";
$user = "root";
$pass = "";
$db = "db_kas";

$koneksi_db = mysqli_connect($server, $user, $pass, $db);
if (!$koneksi_db) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
$koneksi_db->set_charset("utf8mb4");
?>