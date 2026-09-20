<?php
include 'includes/auth.php';

auth_logout();
header('Location: /pos_olbeemi/login.php');
exit;
