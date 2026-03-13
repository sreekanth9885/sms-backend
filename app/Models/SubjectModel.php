<?php
// No need to require Database.php as it's already included in index.php

class SubjectModel
{
    private PDO $db;
    private const VALID_PRIORITY_RANGE = ['min' => 1, 'max' => 999];
    private const DEFAULT_FA_MAX = 50;
    private const DEFAULT_SA_MAX = 100;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Create a new subject
     */
    public function create(array $data): array
    {
        $this->validateSubjectData($data);

        // Check if subject already exists for this class
        if ($this->subjectExists($data['class_id'], $data['subject_name'])) {
            throw new Exception("Subject '{$data['subject_name']}' already exists for this class");
        }

        // Check priority uniqueness within class
        if ($this->priorityExists($data['class_id'], $data['priority'])) {
            throw new Exception("Priority {$data['priority']} is already assigned to another subject in this class");
        }

        $sql = "INSERT INTO submaster (class_id, subject_name, priority, fa_max, sa_max, created_at) 
                VALUES (:class_id, :subject_name, :priority, :fa_max, :sa_max, NOW())";

        $stmt = $this->db->prepare($sql);
        
        $stmt->execute([
            ':class_id' => $data['class_id'],
            ':subject_name' => strtoupper(trim($data['subject_name'])),
            ':priority' => $data['priority'],
            ':fa_max' => $data['fa_max'] ?? self::DEFAULT_FA_MAX,
            ':sa_max' => $data['sa_max'] ?? self::DEFAULT_SA_MAX
        ]);

        $id = $this->db->lastInsertId();
        
        return $this->findById($id);
    }

    /**
     * Update an existing subject
     */
    public function update(int $id, array $data): array
    {
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception("Subject not found");
        }

        // Validate only provided fields
        $updateData = [];

        if (isset($data['subject_name'])) {
            $data['subject_name'] = strtoupper(trim($data['subject_name']));
            
            // Check if new name conflicts with existing subject (excluding current)
            if ($this->subjectExists($existing['class_id'], $data['subject_name'], $id)) {
                throw new Exception("Subject '{$data['subject_name']}' already exists for this class");
            }
            $updateData['subject_name'] = $data['subject_name'];
        }

        if (isset($data['priority'])) {
            $this->validatePriority($data['priority']);
            
            // Check if new priority conflicts (excluding current)
            if ($this->priorityExists($existing['class_id'], $data['priority'], $id)) {
                throw new Exception("Priority {$data['priority']} is already assigned to another subject in this class");
            }
            $updateData['priority'] = $data['priority'];
        }

        if (isset($data['fa_max'])) {
            $this->validateMaxMarks($data['fa_max'], 'FA');
            $updateData['fa_max'] = $data['fa_max'];
        }

        if (isset($data['sa_max'])) {
            $this->validateMaxMarks($data['sa_max'], 'SA');
            $updateData['sa_max'] = $data['sa_max'];
        }

        if (empty($updateData)) {
            return $existing;
        }

        $sql = "UPDATE submaster SET ";
        $fields = [];
        foreach (array_keys($updateData) as $field) {
            $fields[] = "$field = :$field";
        }
        $sql .= implode(', ', $fields) . " WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $updateData['id'] = $id;
        
        $stmt->execute($updateData);
        
        return $this->findById($id);
    }

    /**
     * Delete a subject
     */
    public function delete(int $id): bool
    {
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception("Subject not found");
        }

        // Check if subject is being used in any marks table
        if ($this->isSubjectInUse($id)) {
            throw new Exception("Cannot delete subject as it has associated marks records");
        }

        $sql = "DELETE FROM submaster WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Get subject by ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT s.*, c.name as class_name 
                FROM submaster s
                JOIN classes c ON s.class_id = c.id
                WHERE s.id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get all subjects for a class
     */
    public function getByClass(int $classId, bool $includeClassInfo = true): array
    {
        $sql = "SELECT s.*";
        
        if ($includeClassInfo) {
            $sql .= ", c.name as class_name";
        }
        
        $sql .= " FROM submaster s";
        
        if ($includeClassInfo) {
            $sql .= " JOIN classes c ON s.class_id = c.id";
        }
        
        $sql .= " WHERE s.class_id = :class_id 
                  ORDER BY s.priority ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':class_id' => $classId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all subjects (optionally filtered by school)
     */
    public function getAll(int $schoolId = null, string $search = null): array
    {
        $sql = "SELECT s.*, c.name as class_name, c.school_id 
                FROM submaster s
                JOIN classes c ON s.class_id = c.id
                WHERE 1=1";
        
        $params = [];

        if ($schoolId !== null) {
            $sql .= " AND c.school_id = :school_id";
            $params['school_id'] = $schoolId;
        }

        if ($search !== null && trim($search) !== '') {
            $sql .= " AND (s.subject_name LIKE :search OR c.name LIKE :search)";
            $params['search'] = '%' . trim($search) . '%';
        }

        $sql .= " ORDER BY c.name, s.priority ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get subjects summary with statistics
     */
    public function getSummary(int $classId = null): array
    {
        $sql = "SELECT 
                    c.id as class_id,
                    c.name as class_name,
                    COUNT(s.id) as total_subjects,
                    AVG(s.priority) as avg_priority,
                    MIN(s.fa_max) as min_fa_marks,
                    MAX(s.fa_max) as max_fa_marks,
                    MIN(s.sa_max) as min_sa_marks,
                    MAX(s.sa_max) as max_sa_marks
                FROM classes c
                LEFT JOIN submaster s ON c.id = s.class_id";
        
        $params = [];
        
        if ($classId !== null) {
            $sql .= " WHERE c.id = :class_id";
            $params['class_id'] = $classId;
        }
        
        $sql .= " GROUP BY c.id, c.name
                  ORDER BY c.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bulk create subjects for a class
     */
    public function bulkCreate(int $classId, array $subjects): array
    {
        $this->db->beginTransaction();
        
        try {
            $created = [];
            $errors = [];

            foreach ($subjects as $index => $subject) {
                try {
                    // Add class_id to subject data
                    $subject['class_id'] = $classId;
                    
                    // Set default priority if not provided
                    if (!isset($subject['priority'])) {
                        $subject['priority'] = $index + 1;
                    }

                    $created[] = $this->create($subject);
                    
                } catch (Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'subject' => $subject,
                        'error' => $e->getMessage()
                    ];
                }
            }

            if (!empty($errors)) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'errors' => $errors,
                    'created' => []
                ];
            }

            $this->db->commit();
            
            return [
                'success' => true,
                'created' => $created,
                'count' => count($created)
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Reorder priorities for a class
     */
    public function reorderPriorities(int $classId, array $priorities): bool
    {
        $this->db->beginTransaction();
        
        try {
            foreach ($priorities as $subjectId => $newPriority) {
                $this->validatePriority($newPriority);
                
                $sql = "UPDATE submaster 
                        SET priority = :priority 
                        WHERE id = :id AND class_id = :class_id";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':priority' => $newPriority,
                    ':id' => $subjectId,
                    ':class_id' => $classId
                ]);
            }

            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Check if class exists and get its school_id
     */
    public function getClassSchoolId(int $classId): ?int
    {
        $sql = "SELECT school_id FROM classes WHERE id = :class_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':class_id' => $classId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (int)$result['school_id'] : null;
    }

    // ==================== PRIVATE VALIDATION METHODS ====================

    private function validateSubjectData(array $data): void
    {
        if (empty($data['class_id'])) {
            throw new Exception("Class ID is required");
        }

        if (empty($data['subject_name'])) {
            throw new Exception("Subject name is required");
        }

        if (strlen($data['subject_name']) > 150) {
            throw new Exception("Subject name must be less than 150 characters");
        }

        if (!isset($data['priority'])) {
            throw new Exception("Priority is required");
        }

        $this->validatePriority($data['priority']);

        if (isset($data['fa_max'])) {
            $this->validateMaxMarks($data['fa_max'], 'FA');
        }

        if (isset($data['sa_max'])) {
            $this->validateMaxMarks($data['sa_max'], 'SA');
        }
    }

    private function validatePriority(int $priority): void
    {
        if ($priority < self::VALID_PRIORITY_RANGE['min'] || 
            $priority > self::VALID_PRIORITY_RANGE['max']) {
            throw new Exception("Priority must be between " . 
                self::VALID_PRIORITY_RANGE['min'] . " and " . 
                self::VALID_PRIORITY_RANGE['max']);
        }
    }

    private function validateMaxMarks(int $marks, string $type): void
    {
        if ($marks <= 0) {
            throw new Exception("{$type} maximum marks must be greater than 0");
        }

        if ($marks > 1000) {
            throw new Exception("{$type} maximum marks cannot exceed 1000");
        }
    }

    private function subjectExists(int $classId, string $subjectName, int $excludeId = null): bool
    {
        $sql = "SELECT id FROM submaster 
                WHERE class_id = :class_id 
                AND subject_name = :subject_name";
        
        $params = [
            'class_id' => $classId,
            'subject_name' => strtoupper(trim($subjectName))
        ];

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch() !== false;
    }

    private function priorityExists(int $classId, int $priority, int $excludeId = null): bool
    {
        $sql = "SELECT id FROM submaster 
                WHERE class_id = :class_id 
                AND priority = :priority";
        
        $params = [
            'class_id' => $classId,
            'priority' => $priority
        ];

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch() !== false;
    }

    private function isSubjectInUse(int $subjectId): bool
    {
        // Check if subject is referenced in any marks table
        // Adjust table name based on your actual marks table
        $sql = "SELECT id FROM student_marks WHERE subject_id = :subject_id LIMIT 1";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':subject_id' => $subjectId]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            // If table doesn't exist, assume not in use
            return false;
        }
    }
}