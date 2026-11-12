<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

Security::requirePost();
Security::requireCsrf();
Auth::logout();
flash('success', 'You have been signed out.');
redirect('admin/login.php');
