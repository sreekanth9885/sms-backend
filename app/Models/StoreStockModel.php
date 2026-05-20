<?php

class StoreStockModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // =====================================
    // STOCK LIST
    // =====================================

    public function allBySchool(int $schoolId): array
    {
        $stmt = $this->db->prepare("
            SELECT

                ss.id,
                ss.product_id,

                p.name AS product_name,
                p.unit,

                c.id AS category_id,
                c.name AS category_name,

                sc.id AS sub_category_id,
                sc.name AS sub_category_name,

                ss.quantity,
                ss.avg_rate,
                ss.last_purchase_rate,
                ss.total_value,

                ss.updated_at

            FROM store_stock ss

            JOIN products p
                ON p.id = ss.product_id

            LEFT JOIN categories c
                ON c.id = p.category_id

            LEFT JOIN sub_categories sc
                ON sc.id = p.sub_category_id

            WHERE ss.school_id = ?
              AND p.is_active = 1
              AND c.is_active = 1
              AND sc.is_active = 1
              AND ss.quantity > 0

            ORDER BY p.name ASC
        ");

        $stmt->execute([$schoolId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =====================================
    // SINGLE STOCK DETAILS
    // =====================================

    public function find(int $schoolId, int $productId): array
    {
        $stmt = $this->db->prepare("
            SELECT

                ss.id,
                ss.product_id,

                p.name AS product_name,
                p.unit,

                c.id AS category_id,
                c.name AS category_name,

                sc.id AS sub_category_id,
                sc.name AS sub_category_name,

                ss.quantity,
                ss.avg_rate,
                ss.last_purchase_rate,
                ss.total_value,

                ss.updated_at

            FROM store_stock ss

            JOIN products p
                ON p.id = ss.product_id

            LEFT JOIN categories c
                ON c.id = p.category_id

            LEFT JOIN sub_categories sc
                ON sc.id = p.sub_category_id

            WHERE ss.school_id = ?
              AND ss.product_id = ?
              AND p.is_active = 1
              AND c.is_active = 1
              AND sc.is_active = 1
              AND ss.quantity > 0
        ");

        $stmt->execute([
            $schoolId,
            $productId
        ]);

        $stock = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stock) {
            throw new Exception("Stock not found");
        }

        // =====================================
        // TRANSACTIONS
        // =====================================

        $txStmt = $this->db->prepare("
            SELECT

                st.id,
                st.reference_type,
                st.reference_id,
                st.transaction_type,

                st.quantity,
                st.rate,
                st.total,

                st.created_at,

                se.invoice_no,
                se.invoice_date,

                a.name AS agency_name

            FROM stock_transactions st

            LEFT JOIN stock_entries se
                ON se.id = st.reference_id
                AND st.reference_type = 'PURCHASE'

            LEFT JOIN agencies a
                ON a.id = se.agency_id

            WHERE st.school_id = ?
              AND st.product_id = ?
              AND st.is_active = 1

            ORDER BY st.id DESC
        ");

        $txStmt->execute([
            $schoolId,
            $productId
        ]);

        $stock['transactions'] =
            $txStmt->fetchAll(PDO::FETCH_ASSOC);

        return $stock;
    }
}