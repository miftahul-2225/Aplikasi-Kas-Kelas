<?php
session_start();

// hapus semua session
session_unset();

// hancurkan session
session_destroy();

// redirect ke login
header("Location: login.php");
exit();