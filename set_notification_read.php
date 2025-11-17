<?php

session_name('penyedia_session');
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['read'])) {
    $_SESSION['notification_read'] = true;
    echo 'OK';
} else {
    echo 'ERROR';
}
?>