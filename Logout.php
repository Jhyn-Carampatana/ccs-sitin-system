<?php
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session on the server
session_destroy();

// Redirect the user back to the login page immediately
header("Location: Login.php");
exit();
?>