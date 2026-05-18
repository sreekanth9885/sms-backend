<?php

class StockTransactionModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function delete($transactionId, $schoolId)
    {
        $this->db->beginTransaction();

        try {

            // =========================
            // GET TRANSACTION
            // =========================

            $stmt = $this->db->prepare("
                SELECT *
                FROM stock_transactions
                WHERE id = ?
                  AND school_id = ?
                  AND is_active = 1
            ");

            $stmt->execute([
                $transactionId,
                $schoolId
            ]);

            $tx = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tx) {
                throw new Exception("Transaction not found");
            }

            // =========================
            // GET STORE STOCK
            // =========================

            $stockStmt = $this->db->prepare("
                SELECT *
                FROM store_stock
                WHERE school_id = ?
                  AND product_id = ?
            ");

            $stockStmt->execute([
                $schoolId,
                $tx['product_id']
            ]);

            $stock = $stockStmt->fetch(PDO::FETCH_ASSOC);

            if (!$stock) {
                throw new Exception("Stock not found");
            }

            // =========================
            // REVERSE STOCK
            // =========================

            $newQty =
                $stock['quantity'] - $tx['quantity'];

            $newValue =
                $stock['total_value'] - $tx['total'];

            if ($newQty < 0) {
                $newQty = 0;
            }

            if ($newValue < 0) {
                $newValue = 0;
            }

            $avgRate =
                $newQty > 0
                ? $newValue / $newQty
                : 0;

            // =========================
            // UPDATE STORE STOCK
            // =========================

            $updateStmt = $this->db->prepare("
                UPDATE store_stock
                SET
                    quantity = ?,
                    avg_rate = ?,
                    total_value = ?
                WHERE id = ?
            ");

            $updateStmt->execute([
                $newQty,
                $avgRate,
                $newValue,
                $stock['id']
            ]);

            // =========================
            // SOFT DELETE TRANSACTION
            // =========================

            $deleteStmt = $this->db->prepare("
                UPDATE stock_transactions
                SET is_active = 0
                WHERE id = ?
            ");

            $deleteStmt->execute([
                $transactionId
            ]);

            $this->db->commit();

        } catch (Exception $e) {

            $this->db->rollBack();

            throw $e;
        }
    }
}