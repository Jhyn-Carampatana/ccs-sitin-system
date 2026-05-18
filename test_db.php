<?php
$conn = mysqli_connect("localhost", "root", "", "jhyn");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
echo "Connected successfully to database 'jhyn'!";
?>