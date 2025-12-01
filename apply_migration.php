<?php
require_once 'config.php';

try {
    echo "Applying migration...\n";
    
    $sql = file_get_contents('migrations/add_security_rules_table.sql');
    
    if (!$sql) {
        die("Error: Could not read migration file.\n");
    }
    
    // Split SQL by semicolon to execute statements individually
    // This is a simple split, might need more robust parsing for complex SQL
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
            echo "Executed statement.\n";
        }
    }
    
    echo "Migration applied successfully.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>