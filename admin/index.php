<?php
require __DIR__ . '/../bootstrap.php';

use App\Helpers;
use App\Auth;

Auth::requireRoles(Auth::adminRoles(), '/admin/login.php');

Helpers::redirect('/admin/dashboard.php');
