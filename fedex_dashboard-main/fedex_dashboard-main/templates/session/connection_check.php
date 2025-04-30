<?php

// By default, it will connect to the UCCS server. true = UCCS, false = Localhost
$useUCCS = false;

// UCCS Credentials
$uccs_host = '128.198.162.149';
$uccs_user = 'team2';
$uccs_pass = 'Team2SqlPass';
$uccs_db = 'team2';

// Localhost Credentials
$localhost_host = '127.0.0.1';
$localhost_user = 'root';
$localhost_pass = '';
$localhost_db = 'team2';    


// Choosing which server to connect to
if ($useUCCS) {
    $host = $uccs_host;
    $user = $uccs_user;
    $pass = $uccs_pass;
    $db = $uccs_db;
} else {
    $host = $localhost_host;
    $user = $localhost_user;    
    $pass = $localhost_pass;
    $db = $localhost_db;
}

// Connecting to the database
$conn = mysqli_connect($host, $user, $pass, $db);

// If connection fails, displays error 
if (mysqli_connect_errno()) {
    echo "Failed to connect to DB: " . mysqli_connect_error();
} 

?>