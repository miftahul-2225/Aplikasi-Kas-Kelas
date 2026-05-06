<?php
session_start();
require_once 'config/koneksi.php';

$error = "";

// KETIKA SUDAH LOGIN
if (isset($_SESSION['user_id'])) {
    redirectDashboard($_SESSION['role']);
}

// FUNGSI REDIRECT — sesuai struktur folder
function redirectDashboard($role) {
    if ($role == 'bendahara') {
        header("Location: dashboard.php");                      // root
    } elseif ($role == 'siswa') {
        header("Location: murid/dashboard_siswa.php");          // folder murid/
    } elseif ($role == 'ketua') {
        header("Location: dashboard_ketua.php");                // root (sesuaikan jika ada folder sendiri)
    } elseif ($role == 'wali') {
        header("Location: walikelas/dashboard_wali.php");       // folder walikelas/
    } else {
        header("Location: index.php");
    }
    exit();
}

// PROSES LOGIN
if (isset($_POST['login'])) {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // USERNAME KOSONG
    if (empty($username)) {
        $error = "Username tidak boleh kosong!";

    // PASSWORD KOSONG
    } elseif (empty($password)) {
        $error = "Password tidak boleh kosong!";

    } else {

        // SANITASI INPUT
        $username = mysqli_real_escape_string($koneksi_db, $username);
        $password = mysqli_real_escape_string($koneksi_db, $password);

        // =============================================
        // CEK 1: USER DI tb_user (bendahara / wali / ketua)
        // =============================================
        $q_user = mysqli_query($koneksi_db, "
            SELECT * FROM tb_user 
            WHERE username='$username'
            LIMIT 1
        ");

        if ($q_user && mysqli_num_rows($q_user) > 0) {

            $user = mysqli_fetch_assoc($q_user);

            // CEK STATUS AKTIF
            if (isset($user['status']) && $user['status'] !== 'aktif') {
                $error = "Akun Anda tidak aktif. Hubungi administrator.";

            } elseif ($password == $user['password']) {
                // LOGIN BERHASIL
                $_SESSION['user_id'] = $user['id_user'];
                $_SESSION['role']    = $user['role'];
                $_SESSION['nama']    = $user['username'];

                redirectDashboard($user['role']);

            } else {
                $error = "Password salah!";
            }

        } else {
            // =============================================
            // CEK 2: SISWA DI tb_siswa (username = id_siswa, password = id_siswa)
            // =============================================
            $q_siswa = mysqli_query($koneksi_db, "
                SELECT * FROM tb_siswa 
                WHERE id_siswa='$username'
                LIMIT 1
            ");

            if ($q_siswa && mysqli_num_rows($q_siswa) > 0) {

                $siswa = mysqli_fetch_assoc($q_siswa);

                // CEK STATUS SISWA
                if ($siswa['status'] !== 'aktif') {
                    $error = "Akun siswa tidak aktif. Hubungi wali kelas.";

                } elseif ($password == $siswa['id_siswa']) {
                    // LOGIN SISWA BERHASIL
                    $_SESSION['user_id']  = $siswa['id_siswa'];
                    $_SESSION['role']     = 'siswa';
                    $_SESSION['nama']     = $siswa['nama_siswa'];
                    $_SESSION['kelas']    = $siswa['kelas'];
                    $_SESSION['alamat']   = $siswa['alamat'];
                    $_SESSION['id_siswa'] = $siswa['id_siswa'];

                    redirectDashboard('siswa');

                } else {
                    $error = "Password salah! Password siswa = ID Siswa.";
                }

            } else {
                $error = "Username / NISN Siswa tidak ditemukan!";
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
        body {
            min-height: 100vh;
            background-color: #f0f4f8;
        }

        .login-card { border-radius: 1rem; overflow: hidden; }
        .login-header { background: linear-gradient(135deg, #0d6efd, #0b5ed7); }

        .form-control:focus {
            box-shadow: none;
            border-color: #0d6efd;
        }

        /* ── CUSTOM ALERT ERROR ── */
        .alert-custom {
            border-radius: .75rem;
            padding: 13px 15px;
            font-size: .875rem;
            display: flex;
            align-items: flex-start;
            gap: 11px;
            animation: slideDown .3s ease;
            border: none;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .alert-danger-custom {
            background: #fff1f0;
            border-left: 4px solid #dc3545 !important;
            color: #842029;
        }

        .alert-danger-custom .alert-icon-wrap {
            background: #dc3545;
            color: #fff;
            width: 30px; height: 30px; min-width: 30px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        /* ── INFO STRIP ── */
        .info-strip {
            background: #eff6ff;
            border-left: 4px solid #0d6efd !important;
            border-radius: .75rem;
            border: none;
            padding: 12px 14px;
            font-size: .8rem;
            color: #084298;
            display: flex;
            gap: 11px;
            align-items: flex-start;
        }

        .info-strip .info-icon-wrap {
            background: #0d6efd;
            color: #fff;
            width: 28px; height: 28px; min-width: 28px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .info-strip .info-title {
            font-weight: 600;
            margin-bottom: 6px;
            font-size: .82rem;
        }

        /* Role badges */
        .role-badge {
            display: inline-block;
            font-size: .68rem;
            padding: 1px 7px;
            border-radius: 20px;
            font-weight: 700;
            white-space: nowrap;
        }
        .badge-bendahara { background: #dbeafe; color: #1d4ed8; }
        .badge-wali      { background: #dcfce7; color: #166534; }
        .badge-ketua     { background: #fef9c3; color: #854d0e; }
        .badge-siswa     { background: #fce7f3; color: #9d174d; }

        .info-row {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 3px;
            line-height: 1.4;
        }

        .info-row:last-child { margin-bottom: 0; }
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

                <!-- ── ALERT ERROR ── -->
                <?php if (!empty($error)) { ?>
                <div class="alert-custom alert-danger-custom mb-3">
                    <div class="alert-icon-wrap">
                        <i class="bi bi-exclamation-lg"></i>
                    </div>
                    <div>
                        <div class="fw-semibold mb-1" style="font-size:.85rem;">Login Gagal</div>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                </div>
                <?php } ?>

                <!-- ── INFO STRIP ── -->
                <div class="info-strip mb-3">
                    <div class="info-icon-wrap">
                        <i class="bi bi-info-lg"></i>
                    </div>
                    <div>
                        <div class="info-title">Panduan Login</div>
                        <div class="info-row">
                            <span class="role-badge badge-bendahara">Bendahara</span>
                            <span>Username &amp; password akun</span>
                        </div>
                        <div class="info-row">
                            <span class="role-badge badge-wali">Wali Kelas</span>
                            <span>Username &amp; password akun</span>
                        </div>
                        <div class="info-row">
                            <span class="role-badge badge-ketua">Ketua Kelas</span>
                            <span>Username &amp; password akun</span>
                        </div>
                        <div class="info-row">
                            <span class="role-badge badge-siswa">Siswa</span>
                            <span>Username &amp; password = <strong>NISN Siswa</strong></span>
                        </div>
                    </div>
                </div>

                <!-- ── FORM ── -->
                <form method="POST">

                    <!-- USERNAME -->
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-person-fill"></i>
                            </span>
                            <input
                                type="text"
                                name="username"
                                class="form-control <?= !empty($error) ? 'is-invalid' : '' ?>"
                                value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                                placeholder="Username / NISN Siswa"
                                autofocus
                                required>
                        </div>
                    </div>

                    <!-- PASSWORD -->
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-lock-fill"></i>
                            </span>
                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="form-control <?= !empty($error) ? 'is-invalid' : '' ?>"
                                placeholder="Password"
                                required>
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
    const icon     = document.getElementById("eyeIcon");

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