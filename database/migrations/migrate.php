<?php
/**
 * Migration Runner for papatiger.tech Projects
 * Handles schema versioning with checksum validation.
 */

// 1. Load Database Connection
require_once __DIR__ . '/../../config/db.php';

// Ensure we are running via CLI (Safety)
if (php_sapi_name() !== 'cli') {
    die("Error: This script can only be run from the command line.\n");
}

echo "--- papatiger.tech Migration Runner ---\n";

try {
    // 2. Ensure the tracking table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `filename`    VARCHAR(255) NOT NULL PRIMARY KEY,
        `executed_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `checksum`    CHAR(64)     NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 3. Get already applied migrations
    $applied = $pdo->query("SELECT filename, checksum FROM schema_migrations")
                   ->fetchAll(PDO::FETCH_KEY_PAIR);

    // 4. Scan the migrations folder
    $migrationsDir = __DIR__ . '/';
    if (!is_dir($migrationsDir)) {
        die("Error: Folder '{$migrationsDir}' not found.\n");
    }

    $files = glob($migrationsDir . '*.sql');
    sort($files); // Ensure 001 runs before 002

    $newCount = 0;

    foreach ($files as $file) {
        $filename = basename($file);
        $content = file_get_contents($file);
        $currentChecksum = hash('sha256', $content);

        // Check if file was already run
        if (isset($applied[$filename])) {
            // Validate checksum (Safety Guard). Skip check for baseline placeholder.
            if ($applied[$filename] !== 'baseline-no-op' && $applied[$filename] !== $currentChecksum) {
                echo "CRITICAL ERROR: '{$filename}' has been modified since it was run!\n";
                echo "Stored Checksum:  {$applied[$filename]}\n";
                echo "Current Checksum: {$currentChecksum}\n";
                exit(1); // Abort
            }
            continue; // Skip already applied
        }

        // 5. Apply New Migration
        echo "Applying {$filename}... ";

        try {
            // Use a transaction for safety (Note: MySQL DDL auto-commits)
            $pdo->beginTransaction();

            // Execute the SQL (split into statements, skip empty ones)
            $statements = split_sql_statements($content);
            foreach ($statements as $stmt) {
                $trimmed = trim($stmt);
                if ($trimmed === '') continue;
                $pdo->exec($trimmed);
            }

            // Record execution
            $stmt = $pdo->prepare("INSERT INTO schema_migrations (filename, checksum) VALUES (?, ?)");
            $stmt->execute([$filename, $currentChecksum]);

            $pdo->commit();
            echo "DONE\n";
            $newCount++;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "FAILED!\n";
            echo "Error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    if ($newCount === 0) {
        echo "Database is already up to date.\n";
    } else {
        echo "Successfully applied {$newCount} new migration(s).\n";
    }

} catch (Exception $e) {
    echo "Fatal Runner Error: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Split SQL file into individual statements.
 * Respects single quotes, double quotes, backticks, and strips comments.
 */
function split_sql_statements(string $sql): array {
    // Strip line comments starting with --
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    // Strip /* ... */ block comments
    $sql = preg_replace('!/\*.*?\*/!s', '', $sql);

    $parts = [];
    $buffer = '';
    $inString = false;
    $stringChar = '';
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        if ($inString) {
            $buffer .= $ch;
            if ($ch === $stringChar && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $inString = false;
            }
            continue;
        }
        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $inString = true;
            $stringChar = $ch;
            $buffer .= $ch;
            continue;
        }
        if ($ch === ';') {
            $parts[] = $buffer;
            $buffer = '';
            continue;
        }
        $buffer .= $ch;
    }
    if (trim($buffer) !== '') {
        $parts[] = $buffer;
    }
    return $parts;
}