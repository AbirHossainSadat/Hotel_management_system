<?php
// ১. ডেটাবেজ কনফিগারেশন
$host     = "localhost";
$username = "root";
$password = "";
$dbname   = "ahmani_hotel"; // আপনার ডাটাবেজের নাম
$port     = 3307;

// ২. কানেকশন তৈরি
$conn = mysqli_connect($host, $username, $password, $dbname, $port);

// ৩. কানেকশন সফল হয়েছে কিনা তা পরীক্ষা
if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// ৪. ক্যারেক্টার সেট UTF-8 নির্ধারণ (বাংলা/স্পেশাল টেক্সটের সঠিক সাপোর্টের জন্য)
mysqli_set_charset($conn, "utf8mb4");
?>