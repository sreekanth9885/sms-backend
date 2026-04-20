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
                    $values[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params[] = $student['id'];
                    $params[] = $classId;
                    $params[] = $subject['sid'];
                    $params[] = $rollNo;
                    $params[] = $subject['subname'];

                    $params[] = $subject['fa'] ?? 0; // fa1
                    $params[] = $subject['fa'] ?? 0; // fa2
                    $params[] = $subject['fa'] ?? 0; // fa3
                    $params[] = $subject['fa'] ?? 0; // fa4

                    $params[] = $subject['sa'] ?? 0; // sa1
                    $params[] = $subject['sa'] ?? 0; // sa2
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
    fa1max, fa2max, fa3max, fa4max,
    sa1max, sa2max
) VALUES " . implode(',', $chunk) . "
ON DUPLICATE KEY UPDATE
    subject_name = VALUES(subject_name),
    fa1max = VALUES(fa1max),
    fa2max = VALUES(fa2max),
    fa3max = VALUES(fa3max),
    fa4max = VALUES(fa4max),
    sa1max = VALUES(sa1max),
    sa2max = VALUES(sa2max)";

                // Calculate params for this chunk
                $chunkParams = array_slice(
                    $params,
                    $chunkIndex * $chunkSize * 11,
                    count($chunk) * 11
                );

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
            return ["status" => false, "message" => "No records"];
        }

        error_log("Exam Type: " . $examType);
        error_log("Records count: " . count($records));
        error_log("First record: " . json_encode($records[0] ?? []));

        $marksColumn = match ($examType) {
            'fa1' => 'fa1m',
            'fa2' => 'fa2m',
            'fa3' => 'fa3m',
            'fa4' => 'fa4m',
            'sa1' => 'sa1m',
            'sa2' => 'sa2m',
        };

        $maxColumn = match ($examType) {
            'fa1' => 'fa1max',
            'fa2' => 'fa2max',
            'fa3' => 'fa3max',
            'fa4' => 'fa4max',
            'sa1' => 'sa1max',
            'sa2' => 'sa2max',
        };

        $gradePointColumn = match ($examType) {
            'fa1' => 'fa1gp',
            'fa2' => 'fa2gp',
            'fa3' => 'fa3gp',
            'fa4' => 'fa4gp',
            'sa1' => 'sa1gp',
            'sa2' => 'sa2gp',
        };

        // Add cumulative points column (same as grade points)
        $cumulativePointColumn = match ($examType) {
            'fa1' => 'fa1cp',
            'fa2' => 'fa2cp',
            'fa3' => 'fa3cp',
            'fa4' => 'fa4cp',
            'sa1' => 'sa1cp',
            'sa2' => 'sa2cp',
        };

        $gradeLetterColumn = match ($examType) {
            'fa1' => 'fa1gl',
            'fa2' => 'fa2gl',
            'fa3' => 'fa3gl',
            'fa4' => 'fa4gl',
            'sa1' => 'sa1gl',
            'sa2' => 'sa2gl',
        };

        $resultColumn = match ($examType) {
            'fa1' => 'fa1res',
            'fa2' => 'fa2res',
            'fa3' => 'fa3res',
            'fa4' => 'fa4res',
            'sa1' => 'sa1res',
            'sa2' => 'sa2res',
        };

        $successCount = 0;
        $updatedStudentIds = [];

        $this->db->beginTransaction();

        try {
            // Flatten all subjects first
            $allSubjects = [];

            foreach ($records as $record) {
                // Format 1: Direct ID format {id, marks}
                if (isset($record['id']) && !isset($record['student_id'])) {
                    $stmt = $this->db->prepare("
                    SELECT student_id, subject_id, $maxColumn 
                    FROM eaf 
                    WHERE id = ?
                ");
                    $stmt->execute([$record['id']]);
                    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($existing) {
                        $allSubjects[] = [
                            'student_id' => $existing['student_id'],
                            'subject_id' => $existing['subject_id'],
                            'marks' => $record['marks'] ?? null,
                            'max_marks' => $existing[$maxColumn],
                            'grade_points' => $record['grade_points'] ?? null,
                            'grade' => $record['grade'] ?? null,
                            'result' => $record['result'] ?? null
                        ];
                        $updatedStudentIds[$existing['student_id']] = true;
                    }
                }
                // Format 2: Nested structure
                elseif (isset($record['subjects']) && is_array($record['subjects'])) {
                    foreach ($record['subjects'] as $subject) {
                        $allSubjects[] = [
                            'student_id' => $record['student_id'],
                            'subject_id' => $subject['subject_id'],
                            'marks' => $subject['marks'] ?? null,
                            'max_marks' => $subject['max_marks'] ?? null,
                            'grade_points' => $subject['grade_points'] ?? null,
                            'grade' => $subject['grade'] ?? null,
                            'result' => $subject['result'] ?? null
                        ];
                        $updatedStudentIds[$record['student_id']] = true;
                    }
                }
                // Format 3: Flattened structure
                elseif (isset($record['student_id']) && isset($record['subject_id'])) {
                    $allSubjects[] = [
                        'student_id' => $record['student_id'],
                        'subject_id' => $record['subject_id'],
                        'marks' => $record['marks'] ?? null,
                        'max_marks' => $record['max_marks'] ?? null,
                        'grade_points' => $record['grade_points'] ?? null,
                        'grade' => $record['grade'] ?? null,
                        'result' => $record['result'] ?? null
                    ];
                    $updatedStudentIds[$record['student_id']] = true;
                }
            }

            if (empty($allSubjects)) {
                $this->db->commit();
                return ["status" => true, "message" => "No valid records to update", "updated" => 0];
            }

            // Prepare CASE WHEN statements for bulk update
            $caseMarks = "CASE ";
            $caseResult = "CASE ";
            $caseGradePoint = "CASE ";
            $caseCumulativePoint = "CASE ";  // Add for cumulative points
            $caseGradeLetter = "CASE ";

            $ids = [];

            foreach ($allSubjects as $subject) {
                $key = $subject['student_id'] . '_' . $subject['subject_id'];
                $ids[] = $key;

                // Prepare CASE for marks
                if (isset($subject['marks']) && $subject['marks'] !== null) {
                    $marksValue = ($subject['marks'] == -1 || $subject['marks'] === "ABSENT") ? -1 : (int)$subject['marks'];
                    $caseMarks .= "WHEN student_id = {$subject['student_id']} AND subject_id = {$subject['subject_id']} THEN $marksValue ";

                    // Calculate result based on marks
                    $maxMarks = $subject['max_marks'] ?? 100;
                    if ($marksValue == -1) {
                        $subjectResult = 0;
                    } else {
                        $percentage = ($marksValue / $maxMarks) * 100;
                        $subjectResult = ($percentage >= 35) ? 1 : 0;
                    }

                    $caseResult .= "WHEN student_id = {$subject['student_id']} AND subject_id = {$subject['subject_id']} THEN $subjectResult ";
                }

                // Prepare CASE for grade points
                if (isset($subject['grade_points']) && $subject['grade_points'] !== null) {
                    $gradePointsValue = $subject['grade_points'];
                    $caseGradePoint .= "WHEN student_id = {$subject['student_id']} AND subject_id = {$subject['subject_id']} THEN $gradePointsValue ";
                    // CRITICAL: Also set cumulative points to the same value
                    $caseCumulativePoint .= "WHEN student_id = {$subject['student_id']} AND subject_id = {$subject['subject_id']} THEN $gradePointsValue ";
                }

                // Prepare CASE for grade letter
                if (isset($subject['grade']) && $subject['grade'] !== null) {
                    $gradeValue = $this->db->quote($subject['grade']);
                    $caseGradeLetter .= "WHEN student_id = {$subject['student_id']} AND subject_id = {$subject['subject_id']} THEN $gradeValue ";
                }
            }

            if (empty($ids)) {
                $this->db->commit();
                return ["status" => true, "message" => "No records to update", "updated" => 0];
            }

            // Build the complete UPDATE query
            $updateFields = [];

            if (strpos($caseMarks, "WHEN") !== false) {
                $caseMarks .= "ELSE $marksColumn END";
                $updateFields[] = "$marksColumn = $caseMarks";
            }

            if (strpos($caseResult, "WHEN") !== false) {
                $caseResult .= "ELSE $resultColumn END";
                $updateFields[] = "$resultColumn = $caseResult";
            }

            if (strpos($caseGradePoint, "WHEN") !== false) {
                $caseGradePoint .= "ELSE $gradePointColumn END";
                $updateFields[] = "$gradePointColumn = $caseGradePoint";
            }

            // Add cumulative points update
            if (strpos($caseCumulativePoint, "WHEN") !== false) {
                $caseCumulativePoint .= "ELSE $cumulativePointColumn END";
                $updateFields[] = "$cumulativePointColumn = $caseCumulativePoint";
            }

            if (strpos($caseGradeLetter, "WHEN") !== false) {
                $caseGradeLetter .= "ELSE $gradeLetterColumn END";
                $updateFields[] = "$gradeLetterColumn = $caseGradeLetter";
            }

            $updateFields[] = "edit_count = edit_count + 1";
            $updateFields[] = "updated_at = CURRENT_TIMESTAMP";

            // Create WHERE IN clause
            $whereConditions = [];
            foreach ($ids as $id) {
                list($studentId, $subjectId) = explode('_', $id);
                $whereConditions[] = "(student_id = $studentId AND subject_id = $subjectId)";
            }
            $whereClause = implode(" OR ", $whereConditions);

            $sql = "UPDATE eaf SET " . implode(", ", $updateFields) . " WHERE $whereClause";

            error_log("Bulk update SQL length: " . strlen($sql));

            // Execute single bulk update
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $successCount = count($ids);

            $this->db->commit();

            error_log("Successfully updated: $successCount subject records");

            // Update overall results for all affected students
            if (!empty($updatedStudentIds)) {
                $this->updateOverallResults($examType, array_keys($updatedStudentIds));
            }

            return [
                "status" => true,
                "message" => "Marks processed",
                "updated" => $successCount
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Update marks error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                "status" => false,
                "message" => $e->getMessage()
            ];
        }
    }

    private function updateOverallResults($examType, $studentIds)
    {
        if (empty($studentIds)) return;

        $resultColumn = match ($examType) {
            'fa1' => 'fa1res',
            'fa2' => 'fa2res',
            'fa3' => 'fa3res',
            'fa4' => 'fa4res',
            'sa1' => 'sa1res',
            'sa2' => 'sa2res',
        };

        // Process in chunks
        $chunks = array_chunk($studentIds, 50);

        foreach ($chunks as $chunk) {
            $in = implode(',', array_map('intval', $chunk));

            // Get subject results for each student
            $sql = "
            SELECT 
                e.student_id,
                e.class_id,
                MIN(CASE 
                    WHEN e.$resultColumn = 0 THEN 0 
                    ELSE 1 
                END) as has_all_pass
            FROM eaf e
            WHERE e.student_id IN ($in)
            GROUP BY e.student_id, e.class_id
        ";

            $data = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            // Prepare bulk update for result_status
            $caseResultStatus = "CASE ";
            $studentClassMap = [];

            foreach ($data as $row) {
                $overallResult = ($row['has_all_pass'] == 1) ? 'pass' : 'fail';
                $resultValue = $this->db->quote($overallResult);
                $caseResultStatus .= "WHEN student_id = {$row['student_id']} AND class_id = {$row['class_id']} THEN $resultValue ";
                $studentClassMap[] = $row['student_id'] . '_' . $row['class_id'];
            }

            if (!empty($studentClassMap)) {
                $caseResultStatus .= "ELSE result_status END";

                $whereConditions = [];
                foreach ($data as $row) {
                    $whereConditions[] = "(student_id = {$row['student_id']} AND class_id = {$row['class_id']})";
                }
                $whereClause = implode(" OR ", $whereConditions);

                $updateSql = "UPDATE eaf SET result_status = $caseResultStatus WHERE $whereClause";
                $this->db->exec($updateSql);
            }
        }
    }
    public function getStudentAllMarks($studentId = null, $rollNo = null): array
    {
        $query = "
        SELECT
            e.*, 
            e.student_id,
            e.roll_no,
            e.class_id,
            e.subject_id,
            e.subject_name,

            e.fa1m, e.fa2m, e.fa3m, e.fa4m,
            e.sa1m, e.sa2m,

            e.fa1max, e.fa2max, e.fa3max, e.fa4max,
e.sa1max, e.sa2max,

            CONCAT(s.first_name, ' ', s.last_name) AS student_name,
            c.name AS class_name

        FROM eaf e
        INNER JOIN students s ON s.id = e.student_id
        INNER JOIN classes c ON c.id = e.class_id
        WHERE 1=1
    ";

        $params = [];

        if ($studentId) {
            $query .= " AND e.student_id = ?";
            $params[] = $studentId;
        }

        if ($rollNo) {
            $query .= " AND e.roll_no = ?";
            $params[] = $rollNo;
        }

        $query .= " ORDER BY e.subject_name ASC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function updateFinalSummary($examType, $records)
    {
        if (empty($records)) return;

        // Extract unique student IDs from flattened records
        $studentIds = [];
        foreach ($records as $record) {
            if (isset($record['student_id'])) {
                $studentIds[$record['student_id']] = true;
            }
        }

        $studentIds = array_keys($studentIds);
        if (empty($studentIds)) return;

        error_log("Updating final summary for students: " . json_encode($studentIds));

        $marksColumn = match ($examType) {
            'fa1' => 'fa1m',
            'fa2' => 'fa2m',
            'fa3' => 'fa3m',
            'fa4' => 'fa4m',
            'sa1' => 'sa1m',
            'sa2' => 'sa2m',
        };

        $maxColumn = match ($examType) {
            'fa1' => 'fa1max',
            'fa2' => 'fa2max',
            'fa3' => 'fa3max',
            'fa4' => 'fa4max',
            'sa1' => 'sa1max',
            'sa2' => 'sa2max',
        };

        $in = implode(',', array_map('intval', $studentIds));

        // Aggregate per student
        $sql = "
        SELECT 
            e.student_id,
            e.class_id,
            e.roll_no,
            SUM(CASE 
                WHEN COALESCE(e.$marksColumn, -1) = -1 THEN 0 
                ELSE COALESCE(e.$marksColumn, 0) 
            END) as total_secured,
            SUM(COALESCE(e.$maxColumn, 0)) as total_max
        FROM eaf e
        WHERE e.student_id IN ($in)
        GROUP BY e.student_id, e.class_id, e.roll_no
    ";

        $data = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        error_log("Final summary data: " . json_encode($data));

        foreach ($data as $row) {
            $percentage = $row['total_max'] > 0
                ? ($row['total_secured'] / $row['total_max']) * 100
                : 0;

            // Grade logic
            $grade = match (true) {
                $percentage >= 90 => 'A+',
                $percentage >= 75 => 'A',
                $percentage >= 60 => 'B',
                $percentage >= 50 => 'C',
                $percentage >= 35 => 'D',
                default => 'F',
            };

            $result = $percentage >= 35 ? 'PASS' : 'FAIL';

            // Get admission number
            $stmt = $this->db->prepare("SELECT admission_number FROM students WHERE id = ?");
            $stmt->execute([$row['student_id']]);
            $admissionNo = $stmt->fetchColumn();

            $insert = "
            INSERT INTO eaf_final (
                student_id, admission_no, roll_no, class_id, exam_type,
                total_max, total_secured, percentage, grade, result
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_max = VALUES(total_max),
                total_secured = VALUES(total_secured),
                percentage = VALUES(percentage),
                grade = VALUES(grade),
                result = VALUES(result),
                updated_at = CURRENT_TIMESTAMP
        ";

            $stmt = $this->db->prepare($insert);
            $stmt->execute([
                $row['student_id'],
                $admissionNo,
                $row['roll_no'],
                $row['class_id'],
                $examType,
                $row['total_max'],
                $row['total_secured'],
                $percentage,
                $grade,
                $result
            ]);

            error_log("Updated final summary for student_id: {$row['student_id']}, grade: $grade, result: $result");
        }
    }
}