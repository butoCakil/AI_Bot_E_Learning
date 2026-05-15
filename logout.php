<?php
session_start();
$role = $_SESSION['role'] ?? 'siswa';
session_destroy();
if ($role === 'guru') {
    header('Location: login_guru.php');
} else {
    header('Location: login.php');
}
exit;
?>
