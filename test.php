<?php
$_SESSION['role'] = 'admin';
$_POST = ['academic_session_id' => 1, 'semester' => 1, 'section' => 'A'];
require 'actions/generate.php';
