<?php
/** Child/day attendance is independent of saved messages and group changes. */
class Attendance {
    public const UNAVAILABLE = 'Απαιτείται ενεργοποίηση της αποθήκευσης απουσιών από διαχειριστή στις Παραμέτρους ή έλεγχος της βάσης. Η αποστολή email δεν επιτρέπεται χωρίς έλεγχο απουσιών.';

    public function __construct(private PDO $db) {}

    public static function validDate(mixed $date): bool {
        if (!is_string($date) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $m)) return false;
        return (int)$m[1] >= 1000 && checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
    }

    public function isReady(): bool {
        $columns = $this->db->query("SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'child_attendance'")->fetchAll(PDO::FETCH_ASSOC);
        $types = array_column($columns, 'DATA_TYPE', 'COLUMN_NAME');
        foreach (['child_id' => 'int', 'attendance_date' => 'date', 'is_absent' => 'tinyint', 'updated_by' => 'int', 'updated_at' => 'datetime'] as $name => $type) {
            if (($types[$name] ?? null) !== $type) return false;
        }
        foreach ($columns as $column) {
            if (in_array($column['COLUMN_NAME'], ['child_id', 'attendance_date', 'is_absent', 'updated_at'], true)
                && $column['IS_NULLABLE'] !== 'NO') return false;
            if (in_array($column['COLUMN_NAME'], ['child_id', 'updated_by'], true)
                && !str_contains($column['COLUMN_TYPE'], 'unsigned')) return false;
        }
        $pk = $this->db->query("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'child_attendance' AND CONSTRAINT_NAME = 'PRIMARY'
            ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_COLUMN);
        if ($pk !== ['child_id', 'attendance_date']) return false;
        $engine = $this->db->query("SELECT ENGINE FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'child_attendance'")->fetchColumn();
        if (strtolower((string)$engine) !== 'innodb') return false;
        $fk = $this->db->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'child_attendance'
            AND COLUMN_NAME = 'child_id' AND REFERENCED_TABLE_NAME = 'children' AND REFERENCED_COLUMN_NAME = 'id'")->fetchColumn();
        return (int)$fk === 1;
    }

    /** Explicit admin action only. No migrations on reads or application startup. */
    public function migrate(): void {
        $name = 'kinderlink-attendance-' . substr(hash('sha256', (string)$this->db->query('SELECT DATABASE()')->fetchColumn()), 0, 24);
        $lock = $this->db->prepare('SELECT GET_LOCK(?, 5)');
        $lock->execute([$name]);
        if ((int)$lock->fetchColumn() !== 1) throw new RuntimeException('Migration busy.');
        try {
            if ($this->isReady()) return;
            $exists = $this->db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'child_attendance'")->fetchColumn();
            if ((int)$exists !== 0) throw new RuntimeException('Incompatible attendance table; refusing to alter it.');
            $sql = file_get_contents(__DIR__ . '/../database/attendance-migration-2026-09-23.sql');
            if ($sql === false) throw new RuntimeException('Missing migration file.');
            $this->db->exec($sql);
            if (!$this->isReady()) throw new RuntimeException('Migration verification failed.');
        } finally {
            $this->db->prepare('SELECT RELEASE_LOCK(?)')->execute([$name]);
        }
    }

    public function absentIds(string $date): array {
        $stmt = $this->db->prepare('SELECT child_id FROM child_attendance WHERE attendance_date = ? AND is_absent = 1');
        $stmt->execute([$date]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function isAbsent(int $childId, string $date): bool {
        $stmt = $this->db->prepare('SELECT is_absent FROM child_attendance WHERE child_id = ? AND attendance_date = ?');
        $stmt->execute([$childId, $date]);
        return (int)$stmt->fetchColumn() === 1;
    }

    public function setAbsent(int $childId, string $date, bool $absent, int $userId): void {
        if ($childId <= 0 || !self::validDate($date)) throw new InvalidArgumentException('Invalid attendance scope.');
        $this->db->prepare('INSERT INTO child_attendance (child_id, attendance_date, is_absent, updated_by)
            VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_absent = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP')
            ->execute([$childId, $date, (int)$absent, $userId, (int)$absent, $userId]);
    }
}