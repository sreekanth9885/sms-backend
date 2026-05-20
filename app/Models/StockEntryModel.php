<?php

class StockEntryModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /*
    |--------------------------------------------------------------------------
    | ALL
    |--------------------------------------------------------------------------
    */

    public function all($schoolId)
    {
        $stmt = $this->db->prepare("
            SELECT
                se.*,
                a.name as agency_name
            FROM stock_entries se
            JOIN agencies a
                ON a.id = se.agency_id
            WHERE se.school_id = ?
              AND se.is_active = 1
            ORDER BY se.id DESC
        ");

        $stmt->execute([$schoolId]);

        $entries = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        foreach ($entries as &$entry) {

            $entry['items'] = $this->getItems(
                $entry['id']
            );
        }

        return $entries;
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE ENTRY
    |--------------------------------------------------------------------------
    */

    public function createEntry($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO stock_entries
            (
                school_id,
                agency_id,
                invoice_no,
                invoice_date,
                total_amount,
                notes,
                created_by,
                is_active
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?, 1)
        ");

        $stmt->execute([
            $data['school_id'],
            $data['agency_id'],
            $data['invoice_no'],
            $data['invoice_date'],
            $data['total_amount'],
            $data['notes'],
            $data['created_by']
        ]);

        return $this->db->lastInsertId();
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ENTRY
    |--------------------------------------------------------------------------
    */

    public function updateEntry($entryId, $data)
    {
        $stmt = $this->db->prepare("
            UPDATE stock_entries
            SET
                agency_id = ?,
                invoice_no = ?,
                invoice_date = ?,
                total_amount = ?,
                notes = ?
            WHERE id = ?
              AND is_active = 1
        ");

        $stmt->execute([
            $data['agency_id'],
            $data['invoice_no'],
            $data['invoice_date'],
            $data['total_amount'],
            $data['notes'],
            $entryId
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ITEMS
    |--------------------------------------------------------------------------
    */

    public function insertItem($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO stock_entry_items
            (
                stock_entry_id,
                product_id,
                quantity,
                price,
                total,
                is_active
            )
            VALUES
            (?, ?, ?, ?, ?, 1)
        ");

        $stmt->execute([
            $data['stock_entry_id'],
            $data['product_id'],
            $data['quantity'],
            $data['price'],
            $data['total']
        ]);
    }

    public function getItems($entryId)
    {
        $stmt = $this->db->prepare("
        SELECT
            sei.*,

            p.name as product_name,
            p.unit,

            c.id as category_id,
            c.name as category_name,

            sc.id as sub_category_id,
            sc.name as sub_category_name

        FROM stock_entry_items sei

        JOIN products p
            ON p.id = sei.product_id

        LEFT JOIN categories c
            ON c.id = p.category_id

        LEFT JOIN sub_categories sc
            ON sc.id = p.sub_category_id

        WHERE sei.stock_entry_id = ?
          AND sei.is_active = 1
    ");

        $stmt->execute([$entryId]);

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    public function deleteItems($entryId)
    {
        $stmt = $this->db->prepare("
            UPDATE stock_entry_items
            SET is_active = 0
            WHERE stock_entry_id = ?
        ");

        $stmt->execute([$entryId]);
    }

    /*
    |--------------------------------------------------------------------------
    | TRANSACTIONS
    |--------------------------------------------------------------------------
    */

    public function insertTransaction($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO stock_transactions
            (
                school_id,
                reference_type,
                reference_id,
                transaction_type,
                product_id,
                quantity,
                rate,
                total,
                created_by,
                is_active
            )
            VALUES
            (?, 'PURCHASE', ?, 'IN', ?, ?, ?, ?, ?, 1)
        ");

        $stmt->execute([
            $data['school_id'],
            $data['reference_id'],
            $data['product_id'],
            $data['quantity'],
            $data['rate'],
            $data['total'],
            $data['created_by']
        ]);
    }

    public function deleteTransactions($entryId)
    {
        $stmt = $this->db->prepare("
            UPDATE stock_transactions
            SET is_active = 0
            WHERE reference_type = 'PURCHASE'
              AND reference_id = ?
        ");

        $stmt->execute([$entryId]);
    }

    /*
    |--------------------------------------------------------------------------
    | STOCK
    |--------------------------------------------------------------------------
    */

    public function getStock(
        $schoolId,
        $productId
    ) {

        $stmt = $this->db->prepare("
            SELECT *
            FROM store_stock
            WHERE school_id = ?
              AND product_id = ?
            FOR UPDATE
        ");

        $stmt->execute([
            $schoolId,
            $productId
        ]);

        return $stmt->fetch(
            PDO::FETCH_ASSOC
        );
    }

    public function createStock($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO store_stock
            (
                school_id,
                product_id,
                quantity,
                avg_rate,
                last_purchase_rate,
                total_value
            )
            VALUES
            (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['school_id'],
            $data['product_id'],
            $data['quantity'],
            $data['avg_rate'],
            $data['last_purchase_rate'],
            $data['total_value']
        ]);
    }

    public function updateStock($data)
    {
        $stmt = $this->db->prepare("
            UPDATE store_stock
            SET
                quantity = ?,
                avg_rate = ?,
                last_purchase_rate = ?,
                total_value = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $stmt->execute([
            $data['quantity'],
            $data['avg_rate'],
            $data['last_purchase_rate'],
            $data['total_value'],
            $data['id']
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function softDelete($entryId)
    {
        $this->deleteItems($entryId);

        $this->deleteTransactions($entryId);

        $stmt = $this->db->prepare("
            UPDATE stock_entries
            SET is_active = 0
            WHERE id = ?
        ");

        $stmt->execute([$entryId]);
    }
}