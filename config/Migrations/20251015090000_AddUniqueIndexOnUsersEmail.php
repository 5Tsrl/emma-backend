<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddUniqueIndexOnUsersEmail extends AbstractMigration
{
    public function up(): void
    {
        // 1) Normalize data: trim spaces and lowercase emails (optional but common)
        $this->execute("UPDATE users SET email = TRIM(LOWER(email)) WHERE email IS NOT NULL");

        // 2) Resolve duplicates by nulling later duplicates, keeping the earliest by created date
        //    This is a best-effort heuristic to allow adding the unique index without failing.
        //    If 'created' is missing, fall back to id comparison.
        $sqlDedup = <<<SQL
UPDATE users u
JOIN (
    SELECT email, id
    FROM (
        SELECT email, id,
               ROW_NUMBER() OVER (PARTITION BY email ORDER BY COALESCE(created, '1970-01-01 00:00:00'), id) AS rn
        FROM users
        WHERE email IS NOT NULL AND email != ''
    ) t
    WHERE t.rn > 1
) d ON d.id = u.id
SET u.email = NULL;
SQL;
        try {
            $this->execute($sqlDedup);
        } catch (\Throwable $e) {
            // Fallback for MySQL without window functions
            $fallback = <<<SQL
UPDATE users u
JOIN (
    SELECT u1.id
    FROM users u1
    JOIN users u2 ON u1.email = u2.email AND u1.id > u2.id
    WHERE u1.email IS NOT NULL AND u1.email != ''
) d ON d.id = u.id
SET u.email = NULL;
SQL;
            $this->execute($fallback);
        }

        // 3) Add a unique index on email (null values are allowed and duplicate nulls won't conflict in MySQL)
        $table = $this->table('users');
        // Remove any existing non-unique index to avoid duplicate index names
        if ($table->hasIndex(['email'])) {
            // Phinx doesn't expose drop by columns reliably; use raw SQL to drop non-unique index if present
            $this->execute("ALTER TABLE users DROP INDEX email");
        }
        $this->execute("ALTER TABLE users ADD UNIQUE INDEX UNQ_users_email (email)");
    }

    public function down(): void
    {
        // Drop unique index; recreate a normal index named 'email' for performance parity
        $this->execute("ALTER TABLE users DROP INDEX UNQ_users_email");
        $this->execute("ALTER TABLE users ADD INDEX email (email)");
    }
}
