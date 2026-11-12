<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

if (!Auth::check()) {
    $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? url('admin/index.php');
    flash('error', 'Please sign in to access the administration area.');
    redirect('admin/login.php');
}

$admin = Auth::user();
if (!$admin) {
    Auth::logout();
    flash('error', 'Your administrator account is no longer active.');
    redirect('admin/login.php');
}
