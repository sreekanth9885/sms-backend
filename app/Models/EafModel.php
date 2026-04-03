<?php

class EafModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // Most efficient version - Single query for all inserts
    public function bulkInsert(int $classId, array $students, array $subjects): bool
    {
        if (empty($students) || empty($subjects)) {
            return false;
        }

        try {
            // Build a single massive INSERT query
            $values = [];
            $params = [];

            foreach ($students as $student) {
                $rollNo = $student['admission_number'] ?? $student['roll_number'] ?? null;

                foreach ($subjects as $subject) {
                    $values[] = "(?, ?, ?, ?, ?, ?, ?)";
                    $params[] = $student['id'];
                    $params[] = $classId;
                    $params[] = $subject['sid'];
                    $params[] = $rollNo;
                    $params[] = $subject['subname'];
                    $params[] = $subject['fa'];
                    $params[] = $subject['sa'];
                }
            }

            // Execute in chunks to avoid MySQL packet size limits
            $chunkSize = 1000; // Process 1000 records at a time
            $chunks = array_chunk($values, $chunkSize);

            foreach ($chunks as $chunkIndex => $chunk) {
                $query = "INSERT INTO eaf (
                    student_id,
                    class_id,
                    subject_id,
                    roll_no,
                    subject_name,
                    fa1max,
                    sa1max
                ) VALUES " . implode(',', $chunk) . "
                ON DUPLICATE KEY UPDATE
                    subject_name = VALUES(subject_name),
                    fa1max = VALUES(fa1max),
                    sa1max = VALUES(sa1max)";

                // Calculate params for this chunk
                $chunkParams = array_slice($params, $chunkIndex * $chunkSize * 7, count($chunk) * 7);

                $stmt = $this->db->prepare($query);
                $stmt->execute($chunkParams);
            }

            return true;
        } catch (Exception $e) {
            error_log("Bulk insert error: " . $e->getMessage());
            throw $e;
        }
    }

    // Alternative: Use LOAD DATA INFILE for very large datasets
    public function bulkInsertWithFile(int $classId, array $students, array $subjects): bool
    {
        if (empty($students) || empty($subjects)) {
            return false;
        }

        // Create temporary CSV file
        $tempFile = tempnam(sys_get_temp_dir(), 'eaf_');
        $handle = fopen($tempFile, 'w');

        foreach ($students as $student) {
            $rollNo = $student['admission_number'] ?? $student['roll_number'] ?? null;

            foreach ($subjects as $subject) {
                fputcsv($handle, [
                    $student['id'],
                    $classId,
                    $subject['sid'],
                    $rollNo,
                    $subject['subname'],
                    $subject['fa'],
                    $subject['sa']
                ]);
            }
        }

        fclose($handle);

        try {
            // Use LOAD DATA INFILE for bulk insert
            $query = "
                LOAD DATA LOCAL INFILE '$tempFile'
                INTO TABLE eaf
                FIELDS TERMINATED BY ','
                ENCLOSED BY '\"'
                LINES TERMINATED BY '\n'
                (student_id, class_id, subject_id, roll_no, subject_name, fa1max, sa1max)
                SET id = NULL
            ";

            $this->db->exec($query);

            // Clean up
            unlink($tempFile);

            return true;
        } catch (Exception $e) {
            unlink($tempFile);
            throw $e;
        }
    }

    public function getMarks($classId, $subjectId, $examType): array
    {
        $column = match ($examType) {
            'fa1' => 'fa1m',
            'fa2' => 'fa2m',
            'fa3' => 'fa3m',
            'fa4' => 'fa4m',
            'sa1' => 'sa1m',
            'sa2' => 'sa2m',
            default => 'fa1m'
        };

        $stmt = $this->db->prepare("
            SELECT
                e.*, 
                e.id,
                e.student_id,
                e.roll_no,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                COALESCE(e.$column, '') as marks
            FROM eaf e
            INNER JOIN students s ON s.id = e.student_id
            WHERE e.class_id = ?
            AND e.subject_id = ?
            ORDER BY s.first_name ASC
        ");

        $stmt->execute([$classId, $subjectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateMarks($examType, $records)
    {
        if (empty($records)) {
            return;
        }

        $column = match ($examType) {
            'fa1' => 'fa1m',
            'fa2' => 'fa2m',
            'fa3' => 'fa3m',
            'fa4' => 'fa4m',
            'sa1' => 'sa1m',
            'sa2' => 'sa2m',
        };

        // Use CASE statement for bulk update
        $ids = [];
        $cases = [];
        $params = [];

        foreach ($records as $rec) {
            if (isset($rec['id']) && isset($rec['marks']) && $rec['marks'] !== '') {
                $ids[] = $rec['id'];
                $cases[] = "WHEN id = ? THEN ?";
                $params[] = $rec['id'];
                $params[] = $rec['marks'];
            }
        }

        if (!empty($ids)) {
            $idsList = implode(',', array_fill(0, count($ids), '?'));
            $casesStr = implode(' ', $cases);

            $query = "
                UPDATE eaf 
                SET $column = CASE $casesStr END
                WHERE id IN ($idsList)
            ";

            $params = array_merge($params, $ids);
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
        }
    }
}