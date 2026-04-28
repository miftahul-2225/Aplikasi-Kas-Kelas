<?php
session_start();
require_once 'config/koneksi.php';

$error = "";

// KETIKA SUDAH LOGIN
if (isset($_SESSION['user_id'])) {
    redirectDashboard($_SESSION['role']);
}

// FUNGSI REDIRECT
function redirectDashboard($role) {
    if ($role == 'bendahara') {
        header("Location: dashboard.php");
    } elseif ($role == 'siswa') {
        header("Location: dashboard_siswa.php");
    } else {
        header("Location: index.php");
    }
    exit();
}

// PROSES LOGIN
if (isset($_POST['login'])) {

    // FORM DIKIRIM
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // USERNAME KOSONG
    if (empty($username)) {
        $error = "Username tidak boleh kosong!";

    // PASSWORD KOSONG
    } elseif (empty($password)) {
        $error = "Password tidak boleh kosong!";
    } else {

        // INPUT VALIDASI
        $username = mysqli_real_escape_string($koneksi_db, $username);
        $password = mysqli_real_escape_string($koneksi_db, $password);

        // CEK USER BENDAHARA
        $q_user = mysqli_query($koneksi_db, "
            SELECT * FROM tb_user 
            WHERE username='$username'
            LIMIT 1
        ");

        if ($q_user && mysqli_num_rows($q_user) > 0) {
            // USER DITEMUKAN
            $user = mysqli_fetch_assoc($q_user);

            if ($password == $user['password']) {
                // PASSWORD BENAR (BENDAHARA)
                $_SESSION['user_id'] = $user['id_user'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['nama'] = $user['username'];

                redirectDashboard($user['role']);

            } else {
                // PASSWORD SALAH (BENDAHARA)
                $error = "Password salah!";
            }

        } else {
            // USER TIDAK ADA → CEK SISWA
            $q_siswa = mysqli_query($koneksi_db, "
                SELECT * FROM tb_siswa 
                WHERE id_siswa='$username'
                LIMIT 1
            ");

            if ($q_siswa && mysqli_num_rows($q_siswa) > 0) {

                // SISWA DITEMUKAN
                $siswa = mysqli_fetch_assoc($q_siswa);

                if ($password == $siswa['id_siswa']) {
                    // PASSWORD BENAR (SISWA)
                    $_SESSION['user_id'] = $siswa['id_siswa'];
                    $_SESSION['role'] = 'siswa';
                    $_SESSION['nama'] = $siswa['nama_siswa'];

                    redirectDashboard('siswa');

                } else {
                    // PASSWORD SALAH (SISWA)
                    $error = "Password salah!";
                }

            } else {
                // USERNAME TIDAK DITEMUKAN
                $error = "Username tidak ditemukan!";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Sistem Kas Kelas</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body { min-height: 100vh; }
        .login-card { border-radius: 1rem; overflow: hidden; }
        .login-header { background: linear-gradient(135deg, #0d6efd, #0b5ed7); }
        .form-control:focus {
            box-shadow: none;
            border-color: #0d6efd;
        }
    </style>
</head>

<body>
<div class="container d-flex align-items-center justify-content-center min-vh-100">
    <div class="col-md-4 col-sm-10">
        <div class="card shadow-lg login-card">

            <!-- HEADER -->
            <div class="card-header login-header text-white text-center py-4">
                <i class="bi bi-shield-lock fs-1"></i>
                <h4 class="mt-2 mb-0 fw-bold">Login E Kas Seven</h4>
                <small class="opacity-75">Akses sesuai data diri anda di kelas</small>
            </div>

            <!-- BODY -->
            <div class="card-body p-4">

                <?php if (!empty($error)) { ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?= $error ?>
                    </div>
                <?php } ?>

                <form method="POST">

                    <!-- USERNAME -->
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-person-fill"></i>
                            </span>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                    </div>

                    <!-- PASSWORD -->
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-lock-fill"></i>
                            </span>
                            <input type="password" name="password" id="password" class="form-control" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword()">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button class="btn btn-primary py-2 fw-semibold" name="login">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Login
                        </button>
                    </div>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="card-footer text-center small text-muted">
                © <?= date('Y') ?> Sistem Kas Kelas
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const password = document.getElementById("password");
    const icon = document.getElementById("eyeIcon");

    if (password.type === "password") {
        password.type = "text";
        icon.classList.remove("bi-eye");
        icon.classList.add("bi-eye-slash");
    } else {
        password.type = "password";
        icon.classList.remove("bi-eye-slash");
        icon.classList.add("bi-eye");
    }
}
</script>
</body>
</html>