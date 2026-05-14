<?php

class StockEntryModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function all($schoolId)
    {
        $stmt = $this->db->prepare("
        SELECT 
            se.*,
            a.name as agency_name
        FROM stock_entries se
        JOIN agencies a ON a.id = se.agency_id
        WHERE se.school_id = ?
          AND se.is_active = 1
        ORDER BY se.id DESC
    ");

        $stmt->execute([$schoolId]);

        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($entries as &$entry) {

            $itemsStmt = $this->db->prepare("
            SELECT 
                sei.id,
                sei.product_id,
                sei.quantity,
                sei.price,
                sei.total,

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

            $itemsStmt->execute([$entry['id']]);

            $entry['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

        return $entries;
    }

    public function find($id, $schoolId)
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM stock_entries
            WHERE id = ?
              AND school_id = ?
              AND is_active = 1
        ");

        $stmt->execute([$id, $schoolId]);

        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entry) {
            throw new Exception("Stock entry not found");
        }

        $itemsStmt = $this->db->prepare("
            SELECT sei.*,
                   p.name as product_name
            FROM stock_entry_items sei
            JOIN products p ON p.id = sei.product_id
            WHERE sei.stock_entry_id = ?
              AND sei.is_active = 1
        ");

        $itemsStmt->execute([$id]);

        $entry['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        return $entry;
    }

    public function create($schoolId, $data, $userId)
    {
        $this->db->beginTransaction();

        try {

            $total = 0;

            foreach ($data['items'] as $item) {
                $total += $item['quantity'] * $item['price'];
            }

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
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");

            $stmt->execute([
                $schoolId,
                $data['agency_id'],
                $data['invoice_no'],
                $data['invoice_date'],
                $total,
                $data['notes'] ?? null,
                $userId
            ]);

            $stockEntryId = $this->db->lastInsertId();

            foreach ($data['items'] as $item) {

                $itemTotal = $item['quantity'] * $item['price'];

                $itemStmt = $this->db->prepare("
                    INSERT INTO stock_entry_items
                    (
                        stock_entry_id,
                        product_id,
                        quantity,
                        price,
                        total,
                        is_active
                    )
                    VALUES (?, ?, ?, ?, ?, 1)
                ");

                $itemStmt->execute([
                    $stockEntryId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price'],
                    $itemTotal
                ]);

                // Update stock

                $stockStmt = $this->db->prepare("
                    UPDATE products
                    SET quantity = quantity + ?
                    WHERE id = ?
                      AND school_id = ?
                ");

                $stockStmt->execute([
                    $item['quantity'],
                    $item['product_id'],
                    $schoolId
                ]);
            }

            $this->db->commit();

            return $stockEntryId;
        } catch (Exception $e) {

            $this->db->rollBack();

            throw $e;
        }
    }

    public function update($id, $schoolId, $data)
    {
        $this->db->beginTransaction();

        try {

            // Reverse old stock

            $oldItemsStmt = $this->db->prepare("
                SELECT *
                FROM stock_entry_items
                WHERE stock_entry_id = ?
                  AND is_active = 1
            ");

            $oldItemsStmt->execute([$id]);

            $oldItems = $oldItemsStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($oldItems as $item) {

                $reverseStmt = $this->db->prepare("
                    UPDATE products
                    SET quantity = quantity - ?
                    WHERE id = ?
                      AND school_id = ?
                ");

                $reverseStmt->execute([
                    $item['quantity'],
                    $item['product_id'],
                    $schoolId
                ]);
            }

            // Delete old items

            $this->db->prepare("
                DELETE FROM stock_entry_items
                WHERE stock_entry_id = ?
            ")->execute([$id]);

            // New total

            $total = 0;

            foreach ($data['items'] as $item) {
                $total += $item['quantity'] * $item['price'];
            }

            // Update header

            $stmt = $this->db->prepare("
                UPDATE stock_entries
                SET agency_id = ?,
                    invoice_no = ?,
                    invoice_date = ?,
                    total_amount = ?,
                    notes = ?
                WHERE id = ?
                  AND school_id = ?
            ");

            $stmt->execute([
                $data['agency_id'],
                $data['invoice_no'],
                $data['invoice_date'],
                $total,
                $data['notes'] ?? null,
                $id,
                $schoolId
            ]);

            // Insert new items

            foreach ($data['items'] as $item) {

                $itemTotal = $item['quantity'] * $item['price'];

                $this->db->prepare("
                    INSERT INTO stock_entry_items
                    (
                        stock_entry_id,
                        product_id,
                        quantity,
                        price,
                        total,
                        is_active
                    )
                    VALUES (?, ?, ?, ?, ?, 1)
                ")->execute([
                    $id,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price'],
                    $itemTotal
                ]);

                // Add stock

                $this->db->prepare("
                    UPDATE products
                    SET quantity = quantity + ?
                    WHERE id = ?
                      AND school_id = ?
                ")->execute([
                    $item['quantity'],
                    $item['product_id'],
                    $schoolId
                ]);
            }

            $this->db->commit();

            return true;
        } catch (Exception $e) {

            $this->db->rollBack();

            throw $e;
        }
    }

    public function delete($id, $schoolId)
    {
        $this->db->beginTransaction();

        try {

            $itemsStmt = $this->db->prepare("
                SELECT *
                FROM stock_entry_items
                WHERE stock_entry_id = ?
            ");

            $itemsStmt->execute([$id]);

            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $item) {

                $this->db->prepare("
                    UPDATE products
                    SET quantity = quantity - ?
                    WHERE id = ?
                      AND school_id = ?
                ")->execute([
                    $item['quantity'],
                    $item['product_id'],
                    $schoolId
                ]);
            }

            $this->db->prepare("
                UPDATE stock_entry_items
                SET is_active = 0
                WHERE stock_entry_id = ?
            ")->execute([$id]);

            $this->db->prepare("
                UPDATE stock_entries
                SET is_active = 0
                WHERE id = ?
                  AND school_id = ?
            ")->execute([$id, $schoolId]);

            $this->db->commit();

            return true;
        } catch (Exception $e) {

            $this->db->rollBack();

            throw $e;
        }
    }
}