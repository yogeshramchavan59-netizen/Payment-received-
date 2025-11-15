<?php
// config.php
return [
    // Replace with your Razorpay keys (test keys for now)
    'rzp_key_id' => 'rzp_test_Rfuvem0Ql6y6xJ',
    'rzp_key_secret' => 'be8GFXsfwqPS2gdEOwYe7Tz4',

    // Token TTL (seconds) - how long download token valid
    'download_ttl' => 300, // 5 minutes

    // Path to protected files
    'protected_dir' => __DIR__ . '/protected_files',

    // Path to sqlite DB
    'sqlite_db' => __DIR__ . '/data/db.sqlite',

    // Base URL of your deployed server (set after deploy)
    'base_url' => 'https://yourproject.onrender.com'
];
