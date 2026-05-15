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

            // =========================
            // 1. CALCULATE TOTAL
            // =========================

            $grandTotal = 0;

            foreach ($data['items'] as $item) {

                $grandTotal += (
                    $item['quantity'] * $item['price']
                );
            }

            // =========================
            // 2. CREATE STOCK ENTRY
            // =========================

            $entryStmt = $this->db->prepare("
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

            $entryStmt->execute([
                $schoolId,
                $data['agency_id'],
                $data['invoice_no'],
                $data['invoice_date'],
                $grandTotal,
                $data['notes'] ?? null,
                $userId
            ]);

            $stockEntryId = $this->db->lastInsertId();

            // =========================
            // 3. PROCESS ITEMS
            // =========================

            foreach ($data['items'] as $item) {

                $productId = $item['product_id'];
                $qty       = $item['quantity'];
                $rate      = $item['price'];

                $itemTotal = $qty * $rate;

                // =========================
                // 4. GET PRODUCT DETAILS
                // =========================

                $productStmt = $this->db->prepare("
                SELECT
                    id,
                    category_id,
                    sub_category_id
                FROM products
                WHERE id = ?
                  AND school_id = ?
            ");

                $productStmt->execute([
                    $productId,
                    $schoolId
                ]);

                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new Exception("Invalid product");
                }

                // =========================
                // 5. INSERT ENTRY ITEM
                // =========================

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
                VALUES
                (?, ?, ?, ?, ?, 1)
            ");

                $itemStmt->execute([
                    $stockEntryId,
                    $productId,
                    $qty,
                    $rate,
                    $itemTotal
                ]);

                // =========================
                // 6. INSERT STOCK TRANSACTION
                // =========================

                $transactionStmt = $this->db->prepare("
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
                    created_by
                )
                VALUES
                (?, 'PURCHASE', ?, 'IN', ?, ?, ?, ?, ?)
            ");

                $transactionStmt->execute([
                    $schoolId,
                    $stockEntryId,
                    $productId,
                    $qty,
                    $rate,
                    $itemTotal,
                    $userId
                ]);

                // =========================
                // 7. UPDATE LAST PURCHASE RATE
                // =========================

                $lastRateStmt = $this->db->prepare("
                UPDATE products
                SET last_purchase_rate = ?
                WHERE id = ?
            ");

                $lastRateStmt->execute([
                    $rate,
                    $productId
                ]);

                // =========================
                // 8. CHECK STORE STOCK
                // =========================

                $stockStmt = $this->db->prepare("
                SELECT *
                FROM store_stock
                WHERE school_id = ?
                  AND product_id = ?
            ");

                $stockStmt->execute([
                    $schoolId,
                    $productId
                ]);

                $existingStock = $stockStmt->fetch(PDO::FETCH_ASSOC);

                // =========================
                // 9. UPDATE EXISTING STOCK
                // =========================

                if ($existingStock) {

                    $oldQty   = $existingStock['quantity'];
                    $oldValue = $existingStock['total_value'];

                    $newQty = $oldQty + $qty;

                    $newValue = $oldValue + $itemTotal;

                    $avgRate = $newValue / $newQty;

                    $updateStockStmt = $this->db->prepare("
                    UPDATE store_stock
                    SET
                        quantity = ?,
                        avg_rate = ?,
                        last_purchase_rate = ?,
                        total_value = ?
                    WHERE id = ?
                ");

                    $updateStockStmt->execute([
                        $newQty,
                        $avgRate,
                        $rate,
                        $newValue,
                        $existingStock['id']
                    ]);
                }

                // =========================
                // 10. INSERT NEW STOCK
                // =========================

                else {

                    $insertStockStmt = $this->db->prepare("
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

                    $insertStockStmt->execute([
                        $schoolId,
                        $productId,
                        $qty,
                        $rate,
                        $rate,
                        $itemTotal
                ]);
            }
            }

            // =========================
            // 11. COMMIT
            // =========================

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