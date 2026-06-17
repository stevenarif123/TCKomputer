<?php
/**
 * PHP Migration Script: Migrate existing buyers from orders to users table
 * Generates clean, personalized, unique usernames from their real names.
 */


try {
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/config/helpers.php';
    
    $pdo = getDBConnection();
    
    // 1. Fetch unique buyers from orders table
    $stmt = $pdo->query(
        "SELECT buyer_phone, MAX(buyer_name) AS buyer_name, MAX(buyer_address) AS buyer_address 
         FROM orders 
         GROUP BY buyer_phone"
    );
    $buyers = $stmt->fetchAll();
    
    if (empty($buyers)) {
        echo "Info: Tidak ada data pesanan di tabel 'orders' untuk dimigrasi.\n";
    } else {
        echo "Memulai migrasi " . count($buyers) . " pembeli dari tabel 'orders' ke tabel 'users'...\n\n";
        
        // Default password hash for 'user123'
        $defaultPasswordHash = password_hash('user123', PASSWORD_BCRYPT);
        $migratedCount = 0;
        
        foreach ($buyers as $buyer) {
            $phone = trim($buyer['buyer_phone']);
            $realName = trim($buyer['buyer_name']);
            $address = trim($buyer['buyer_address']);
            
            // Skip if phone is empty
            if (empty($phone)) {
                continue;
            }
            
            // Check if phone number is already registered in users table
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $checkStmt->execute([$phone]);
            if ($checkStmt->fetch()) {
                echo "Lewati: Nomor telepon $phone sudah terdaftar di tabel 'users'.\n";
                continue;
            }
            
            // Generate a clean username based on their real name
            // 1. Lowercase and replace spaces/hyphens with underscores
            $baseUsername = strtolower($realName);
            $baseUsername = str_replace([' ', '-'], '_', $baseUsername);
            // 2. Remove any character that is not alphanumeric or underscore
            $baseUsername = preg_replace('/[^a-z0-9_]/', '', $baseUsername);
            // 3. Ensure length is between 3 and 25 characters
            if (strlen($baseUsername) < 3) {
                // Fallback to user_phone if name cleaning resulted in a too short string
                $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
                $baseUsername = 'user_' . $cleanPhone;
            }
            if (strlen($baseUsername) > 25) {
                $baseUsername = substr($baseUsername, 0, 25);
            }
            
            // Remove trailing/leading underscores
            $baseUsername = trim($baseUsername, '_');
            
            // Ensure username is unique in the database
            $username = $baseUsername;
            $counter = 1;
            
            while (true) {
                $userCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $userCheck->execute([$username]);
                if (!$userCheck->fetch()) {
                    break; // Username is unique!
                }
                // If exists, append counter
                $username = $baseUsername . '_' . $counter;
                $counter++;
            }
            
            // Generate dummy email based on username
            $email = $username . '@tckomputer.com';
            
            // Insert into users table
            $insertStmt = $pdo->prepare(
                "INSERT INTO users (username, phone, name, email, address, password, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );
            $insertStmt->execute([
                $username,
                $phone,
                $realName,
                $email,
                $address,
                $defaultPasswordHash
            ]);
            
            echo "Sukses Migrasi: Nama: '$realName' -> Username: '$username', Telepon: '$phone', Kata Sandi Default: 'user123'\n";
            $migratedCount++;
        }
        
        echo "\nMigrasi selesai! Berhasil memindahkan $migratedCount pembeli ke tabel 'users'.\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
