<?php 


// auth/logout.php
require_once '../config/session.php';

destroyUserSession();
header("Location: ../login.php");
exit();