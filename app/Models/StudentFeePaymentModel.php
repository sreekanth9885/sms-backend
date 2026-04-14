<?php

class StudentFeePaymentModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function createPayment(array $data)
    {
        $sql = "INSERT INTO student_fee_payments 
    (
        student_fee_id, student_id, paid_amount, payment_method,
        transaction_id, remarks, collected_by, collected_by_name, collected_at
    )
    VALUES 
    (
        :student_fee_id, :student_id, :paid_amount, :payment_method,
        :transaction_id, :remarks, :collected_by, :collected_by_name, :collected_at
    )";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':student_fee_id' => $data['student_fee_id'],
            ':student_id' => $data['student_id'],
            ':paid_amount' => $data['paid_amount'],
            ':payment_method' => $data['payment_method'],
            ':transaction_id' => $data['transaction_id'] ?? null,
            ':remarks' => $data['remarks'] ?? null,
            ':collected_by' => $data['collected_by'],
            ':collected_by_name' => $data['collected_by_name'],
            ':collected_at' => $data['collected_at'] ?? date('Y-m-d H:i:s'), // ✅ fallback
        ]);

        return $this->db->lastInsertId();
    }

    public function getPaymentsByStudentFeeId(int $studentFeeId)
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM student_fee_payments
            WHERE student_fee_id = ?
            ORDER BY created_at DESC
        ");

        $stmt->execute([$studentFeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}