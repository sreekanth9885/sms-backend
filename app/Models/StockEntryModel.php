<?php
class StockEntryModel
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        // Ensure exceptions are thrown for errors
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function create($schoolId, $data, $userId)
    {
        // Validate input first (before starting transaction)
        $this->validateStockData($data);

        try {
            // Check for duplicate invoice (before transaction)
            $this->checkDuplicateInvoice($schoolId, $data['agency_id'], $data['invoice_no']);

            // Start transaction
            $this->db->beginTransaction();

            // Calculate total amount
            $totalAmount = $this->calculateTotalAmount($data['items']);

            // Insert stock entry header
            $stockEntryId = $this->insertStockEntry($schoolId, $data, $totalAmount, $userId);

            // Insert items and update stock
            $this->insertStockItems($stockEntryId, $data['items'], $schoolId);

            // Commit transaction
            $this->db->commit();
            return $stockEntryId;
        } catch (Exception $e) {
            // Only rollback if a transaction is active
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function update($id, $schoolId, $data, $userId)
    {
        // Validate input first
        $this->validateStockData($data);

        try {
            // Check if entry exists and is active (before transaction)
            if (!$this->stockEntryExists($id, $schoolId)) {
                throw new Exception("Stock entry not found");
            }

            // Start transaction
            $this->db->beginTransaction();

            // Get old items before updating
            $oldItems = $this->getStockItems($id);

            // Reverse old stock
            foreach ($oldItems as $item) {
                $this->updateProductStock($item['product_id'], $item['quantity'], $schoolId, 'subtract');
            }

            // Soft delete old items
            $this->softDeleteStockItems($id);

            // Calculate new total
            $totalAmount = $this->calculateTotalAmount($data['items']);

            // Update header
            $this->updateStockEntry($id, $schoolId, $data, $totalAmount);

            // Insert new items and update stock
            $this->insertStockItems($id, $data['items'], $schoolId);

            // Commit transaction
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            // Only rollback if a transaction is active
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function delete($id, $schoolId)
    {
        try {
            // Check if entry exists and is active (before transaction)
            $entry = $this->getActiveStockEntry($id, $schoolId);
            if (!$entry) {
                throw new Exception("Stock entry not found or already deleted");
            }

            // Start transaction
            $this->db->beginTransaction();

            // Get active items before soft deletion
            $items = $this->getStockItems($id);

            // Reverse stock (subtract quantities)
            foreach ($items as $item) {
                $this->updateProductStock($item['product_id'], $item['quantity'], $schoolId, 'subtract');
            }

            // Soft delete items
            $this->softDeleteStockItems($id);

            // Soft delete the entry
            $this->softDeleteStockEntry($id, $schoolId);

            // Commit transaction
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            // Only rollback if a transaction is active
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // Permanent hard delete (use with caution)
    public function permanentDelete($id, $schoolId)
    {
        try {
            // Check if entry exists
            if (!$this->stockEntryExists($id, $schoolId)) {
                throw new Exception("Stock entry not found");
            }

            // Start transaction
            $this->db->beginTransaction();

            // Get items (even inactive ones) to reverse stock
            $items = $this->getAllStockItems($id);

            // Reverse stock if items are still active in stock
            foreach ($items as $item) {
                if ($item['is_active'] == 1) {
                    $this->updateProductStock($item['product_id'], $item['quantity'], $schoolId, 'subtract');
                }
            }

            // Permanently delete items
            $this->deleteStockItems($id);

            // Permanently delete the entry
            $this->deleteStockEntry($id, $schoolId);

            // Commit transaction
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            // Only rollback if a transaction is active
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // Restore a soft-deleted stock entry
    public function restore($id, $schoolId)
    {
        try {
            // Check if entry exists and is inactive
            $stmt = $this->db->prepare("
                SELECT id, is_active 
                FROM stock_entries 
                WHERE id = ? AND school_id = ? AND is_active = 0
            ");
            $stmt->execute([$id, $schoolId]);
            $entry = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$entry) {
                throw new Exception("No deleted stock entry found with this ID");
            }

            // Start transaction
            $this->db->beginTransaction();

            // Restore the entry
            $stmt = $this->db->prepare("
                UPDATE stock_entries 
                SET is_active = 1 
                WHERE id = ? AND school_id = ?
            ");
            $stmt->execute([$id, $schoolId]);

            // Restore items and update stock
            $stmt = $this->db->prepare("
                UPDATE stock_entry_items 
                SET is_active = 1 
                WHERE stock_entry_id = ?
            ");
            $stmt->execute([$id]);

            // Add stock back
            $items = $this->getAllStockItems($id);
            foreach ($items as $item) {
                if ($item['is_active'] == 1) {
                    $this->updateProductStock($item['product_id'], $item['quantity'], $schoolId, 'add');
                }
            }

            // Commit transaction
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            // Only rollback if a transaction is active
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function getById($id, $schoolId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT se.id, se.invoice_no, se.invoice_date, se.total_amount, se.notes, se.is_active,
                       a.name as agency_name, a.id as agency_id
                FROM stock_entries se
                JOIN agencies a ON a.id = se.agency_id
                WHERE se.id = ? AND se.school_id = ?
            ");
            $stmt->execute([$id, $schoolId]);

            $entry = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($entry) {
                $entry['items'] = $this->getStockItems($id);
            }

            return $entry;
        } catch (Exception $e) {
            throw new Exception("Error fetching stock entry: " . $e->getMessage());
        }
    }

    public function allBySchool($schoolId, $includeInactive = false)
    {
        try {
            $sql = "
                SELECT se.id, se.invoice_no, se.invoice_date, se.total_amount, se.created_at, se.is_active,
                       a.id as agency_id, a.name as agency_name
                FROM stock_entries se
                JOIN agencies a ON a.id = se.agency_id
                WHERE se.school_id = ?
            ";

            if (!$includeInactive) {
                $sql .= " AND se.is_active = 1";
            }

            $sql .= " ORDER BY se.id DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$schoolId]);

            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($entries as &$entry) {
                $entry['items'] = $this->getStockItems($entry['id']);
            }

            return $entries;
        } catch (Exception $e) {
            throw new Exception("Error fetching stock entries: " . $e->getMessage());
        }
    }

    public function getDeletedEntries($schoolId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT se.id, se.invoice_no, se.invoice_date, se.total_amount, se.created_at,
                       a.name as agency_name
                FROM stock_entries se
                JOIN agencies a ON a.id = se.agency_id
                WHERE se.school_id = ? AND se.is_active = 0
                ORDER BY se.id DESC
            ");
            $stmt->execute([$schoolId]);

            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($entries as &$entry) {
                $entry['items'] = $this->getAllStockItems($entry['id']);
            }

            return $entries;
        } catch (Exception $e) {
            throw new Exception("Error fetching deleted entries: " . $e->getMessage());
        }
    }

    public function getByInvoiceNo($schoolId, $agencyId, $invoiceNo)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT se.*, a.name as agency_name
                FROM stock_entries se
                JOIN agencies a ON a.id = se.agency_id
                WHERE se.school_id = ? 
                  AND se.agency_id = ? 
                  AND se.invoice_no = ?
                  AND se.is_active = 1
            ");
            $stmt->execute([$schoolId, $agencyId, $invoiceNo]);

            $entry = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($entry) {
                $entry['items'] = $this->getStockItems($entry['id']);
            }

            return $entry;
        } catch (Exception $e) {
            throw new Exception("Error fetching by invoice: " . $e->getMessage());
        }
    }

    // Private helper methods
    private function validateStockData($data)
    {
        if (empty($data['agency_id'])) {
            throw new Exception("Agency is required");
        }
        if (empty($data['invoice_no'])) {
            throw new Exception("Invoice number is required");
        }
        if (empty($data['invoice_date'])) {
            throw new Exception("Invoice date is required");
        }
        if (empty($data['items']) || !is_array($data['items'])) {
            throw new Exception("At least one item is required");
        }

        foreach ($data['items'] as $index => $item) {
            if (empty($item['product_id'])) {
                throw new Exception("Product is required for item #" . ($index + 1));
            }
            if (empty($item['quantity']) || $item['quantity'] <= 0) {
                throw new Exception("Valid quantity is required for item #" . ($index + 1));
            }
            if (!isset($item['price']) || $item['price'] < 0) {
                throw new Exception("Valid price is required for item #" . ($index + 1));
            }
        }
    }

    private function getActiveStockEntry($id, $schoolId)
    {
        $stmt = $this->db->prepare("
            SELECT id, is_active 
            FROM stock_entries 
            WHERE id = ? AND school_id = ? AND is_active = 1
        ");
        $stmt->execute([$id, $schoolId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function stockEntryExists($id, $schoolId)
    {
        $stmt = $this->db->prepare("
            SELECT id FROM stock_entries WHERE id = ? AND school_id = ?
        ");
        $stmt->execute([$id, $schoolId]);
        return $stmt->fetch() !== false;
    }

    private function checkDuplicateInvoice($schoolId, $agencyId, $invoiceNo)
    {
        $stmt = $this->db->prepare("
            SELECT id 
            FROM stock_entries 
            WHERE school_id = ? 
              AND agency_id = ? 
              AND invoice_no = ?
              AND is_active = 1
        ");
        $stmt->execute([$schoolId, $agencyId, $invoiceNo]);

        if ($stmt->fetch()) {
            throw new Exception("Invoice number '{$invoiceNo}' already exists for this agency");
        }
    }

    private function calculateTotalAmount($items)
    {
        $total = 0;
        foreach ($items as $item) {
            $total += $item['quantity'] * $item['price'];
        }
        return $total;
    }

    private function insertStockEntry($schoolId, $data, $totalAmount, $userId)
    {
        $stmt = $this->db->prepare("
            INSERT INTO stock_entries 
            (school_id, agency_id, invoice_no, invoice_date, total_amount, notes, created_by, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");

        $notes = $data['notes'] ?? null;

        $stmt->execute([
            $schoolId,
            $data['agency_id'],
            $data['invoice_no'],
            $data['invoice_date'],
            $totalAmount,
            $notes,
            $userId
        ]);

        return $this->db->lastInsertId();
    }

    private function updateStockEntry($id, $schoolId, $data, $totalAmount)
    {
        $stmt = $this->db->prepare("
            UPDATE stock_entries 
            SET agency_id = ?, invoice_no = ?, invoice_date = ?, 
                total_amount = ?, notes = ?
            WHERE id = ? AND school_id = ? AND is_active = 1
        ");

        $notes = $data['notes'] ?? null;

        return $stmt->execute([
            $data['agency_id'],
            $data['invoice_no'],
            $data['invoice_date'],
            $totalAmount,
            $notes,
            $id,
            $schoolId
        ]);
    }

    private function softDeleteStockEntry($id, $schoolId)
    {
        $stmt = $this->db->prepare("
            UPDATE stock_entries 
            SET is_active = 0 
            WHERE id = ? AND school_id = ?
        ");
        return $stmt->execute([$id, $schoolId]);
    }

    private function insertStockItems($stockEntryId, $items, $schoolId)
    {
        foreach ($items as $item) {
            $total = $item['quantity'] * $item['price'];

            // Insert item
            $stmt = $this->db->prepare("
                INSERT INTO stock_entry_items 
                (stock_entry_id, product_id, quantity, price, total, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $stockEntryId,
                $item['product_id'],
                $item['quantity'],
                $item['price'],
                $total
            ]);

            // Update product stock (add quantity)
            $this->updateProductStock($item['product_id'], $item['quantity'], $schoolId, 'add');
        }
    }

    private function updateProductStock($productId, $quantity, $schoolId, $operation = 'add')
    {
        $sql = $operation === 'add'
            ? "UPDATE products SET quantity = quantity + ? WHERE id = ? AND school_id = ?"
            : "UPDATE products SET quantity = quantity - ? WHERE id = ? AND school_id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$quantity, $productId, $schoolId]);
    }

    private function getStockItems($stockEntryId)
    {
        // Only get active items (is_active = 1)
        $stmt = $this->db->prepare("
            SELECT 
                sei.product_id,
                sei.quantity,
                sei.price,
                sei.total,
                sei.is_active,
                p.name AS product_name,
                c.id AS category_id,
                c.name AS category_name,
                sc.id AS sub_category_id,
                sc.name AS sub_category_name,
                p.unit
            FROM stock_entry_items sei
            JOIN products p ON p.id = sei.product_id
            JOIN categories c ON c.id = p.category_id
            JOIN sub_categories sc ON sc.id = p.sub_category_id
            WHERE sei.stock_entry_id = ? AND sei.is_active = 1
        ");
        $stmt->execute([$stockEntryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getAllStockItems($stockEntryId)
    {
        // Get all items including inactive ones
        $stmt = $this->db->prepare("
            SELECT 
                sei.product_id,
                sei.quantity,
                sei.price,
                sei.total,
                sei.is_active,
                p.name AS product_name,
                c.id AS category_id,
                c.name AS category_name,
                sc.id AS sub_category_id,
                sc.name AS sub_category_name,
                p.unit
            FROM stock_entry_items sei
            JOIN products p ON p.id = sei.product_id
            JOIN categories c ON c.id = p.category_id
            JOIN sub_categories sc ON sc.id = p.sub_category_id
            WHERE sei.stock_entry_id = ?
        ");
        $stmt->execute([$stockEntryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function softDeleteStockItems($stockEntryId)
    {
        $stmt = $this->db->prepare("
            UPDATE stock_entry_items 
            SET is_active = 0 
            WHERE stock_entry_id = ?
        ");
        return $stmt->execute([$stockEntryId]);
    }

    private function deleteStockItems($stockEntryId)
    {
        // Permanent delete - use with caution
        $stmt = $this->db->prepare("DELETE FROM stock_entry_items WHERE stock_entry_id = ?");
        return $stmt->execute([$stockEntryId]);
    }

    private function deleteStockEntry($id, $schoolId)
    {
        // Permanent delete - use with caution
        $stmt = $this->db->prepare("DELETE FROM stock_entries WHERE id = ? AND school_id = ?");
        return $stmt->execute([$id, $schoolId]);
    }

    // Additional utility method to get stock history for a product
    public function getStockHistoryByProduct($schoolId, $productId, $limit = 50)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    sei.*,
                    se.invoice_no,
                    se.invoice_date,
                    se.agency_id,
                    a.name as agency_name
                FROM stock_entry_items sei
                JOIN stock_entries se ON se.id = sei.stock_entry_id
                JOIN agencies a ON a.id = se.agency_id
                WHERE se.school_id = ? 
                  AND sei.product_id = ?
                  AND sei.is_active = 1
                  AND se.is_active = 1
                ORDER BY se.invoice_date DESC, se.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$schoolId, $productId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error fetching stock history: " . $e->getMessage());
        }
    }
}