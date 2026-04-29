<?php
class StockEntryModel
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create($schoolId, $data, $userId)
    {
        $this->db->beginTransaction();

        // 🔴 Duplicate invoice check
        $stmt = $this->db->prepare("
            SELECT id FROM stock_entries 
            WHERE school_id=? AND invoice_no=? AND is_active=1
        ");
        $stmt->execute([$schoolId, $data['invoice_no']]);

        if ($stmt->fetch()) {
            throw new Exception("Invoice already exists");
        }

        // ✅ Insert header
        $stmt = $this->db->prepare("
            INSERT INTO stock_entries 
            (school_id, agency_id, invoice_no, invoice_date, total_amount, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $totalAmount = 0;

        foreach ($data['items'] as $item) {
            $totalAmount += $item['quantity'] * $item['price'];
        }

        $stmt->execute([
            $schoolId,
            $data['agency_id'],
            $data['invoice_no'],
            $data['invoice_date'],
            $totalAmount,
            $userId
        ]);

        $stockEntryId = $this->db->lastInsertId();

        // ✅ Insert items + update stock
        foreach ($data['items'] as $item) {

            $total = $item['quantity'] * $item['price'];

            // Insert item
            $stmt = $this->db->prepare("
                INSERT INTO stock_entry_items 
                (stock_entry_id, product_id, quantity, price, total)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $stockEntryId,
                $item['product_id'],
                $item['quantity'],
                $item['price'],
                $total
            ]);

            // 🔥 Update product stock
            $stmt = $this->db->prepare("
                UPDATE products 
                SET quantity = quantity + ? 
                WHERE id = ? AND school_id = ?
            ");

            $stmt->execute([
                $item['quantity'],
                $item['product_id'],
                $schoolId
            ]);
        }

        $this->db->commit();

        return $stockEntryId;
    }
    public function allBySchool($schoolId)
{
    // 🔹 Step 1: Get stock entries
    $stmt = $this->db->prepare("
        SELECT se.id, se.invoice_no, se.invoice_date, se.total_amount,
               a.name as agency_name
        FROM stock_entries se
        JOIN agencies a ON a.id = se.agency_id
        WHERE se.school_id = ? AND se.is_active = 1
        ORDER BY se.id DESC
    ");
    $stmt->execute([$schoolId]);

    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 🔹 Step 2: Attach items for each entry
    foreach ($entries as &$entry) {

        $stmt = $this->db->prepare("
            SELECT 
                sei.product_id,
                sei.quantity,
                sei.price,
                sei.total,
                c.name AS category_name,
                sc.name AS sub_category_name,
                p.unit
            FROM stock_entry_items sei
            JOIN products p ON p.id = sei.product_id
            JOIN categories c ON c.id = p.category_id
            JOIN sub_categories sc ON sc.id = p.sub_category_id
            WHERE sei.stock_entry_id = ? AND sei.is_active = 1
        ");

        $stmt->execute([$entry['id']]);

        $entry['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return $entries;
}
public function delete($id, $schoolId)
{
    $this->db->beginTransaction();

        // 🔁 Step 1: Get active items only
        $stmt = $this->db->prepare("
        SELECT product_id, quantity 
        FROM stock_entry_items 
        WHERE stock_entry_id = ? AND is_active = 1
    ");
    $stmt->execute([$id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 🔁 Step 2: Reverse stock
        foreach ($items as $item) {
            $this->db->prepare("
            UPDATE products 
            SET quantity = quantity - ? 
            WHERE id = ? AND school_id = ?
        ")->execute([
            $item['quantity'],
            $item['product_id'],
            $schoolId
        ]);
    }

        // 🔁 Step 3: Soft delete items
        $this->db->prepare("
        UPDATE stock_entry_items 
        SET is_active = 0 
        WHERE stock_entry_id = ?
    ")->execute([$id]);

        // 🔁 Step 4: Soft delete entry
        $this->db->prepare("
        UPDATE stock_entries 
        SET is_active = 0 
        WHERE id = ? AND school_id = ?
    ")->execute([$id, $schoolId]);

    $this->db->commit();

    return true;
}
public function update($id, $schoolId, $data, $userId)
{
    $this->db->beginTransaction();

    // 🔁 Step 1: Reverse old stock
    $stmt = $this->db->prepare("
        SELECT product_id, quantity FROM stock_entry_items 
        WHERE stock_entry_id = ? AND is_active = 1
    ");
    $stmt->execute([$id]);
    $oldItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($oldItems as $item) {
        $stmt = $this->db->prepare("
            UPDATE products 
            SET quantity = quantity - ?
            WHERE id = ? AND school_id = ?
        ");
        $stmt->execute([
            $item['quantity'],
            $item['product_id'],
            $schoolId
        ]);
    }

    // 🔁 Step 2: Delete old items
    $this->db->prepare("DELETE FROM stock_entry_items WHERE stock_entry_id=?")
        ->execute([$id]);

    // 🔁 Step 3: Recalculate total
    $totalAmount = 0;
    foreach ($data['items'] as $item) {
        $totalAmount += $item['quantity'] * $item['price'];
    }

    // 🔁 Step 4: Update header
    $stmt = $this->db->prepare("
        UPDATE stock_entries 
        SET agency_id=?, invoice_no=?, invoice_date=?, total_amount=?
        WHERE id=? AND school_id=?
    ");

    $stmt->execute([
        $data['agency_id'],
        $data['invoice_no'],
        $data['invoice_date'],
        $totalAmount,
        $id,
        $schoolId
    ]);

    // 🔁 Step 5: Insert new items + update stock
    foreach ($data['items'] as $item) {
        $total = $item['quantity'] * $item['price'];

        $this->db->prepare("
            INSERT INTO stock_entry_items 
            (stock_entry_id, product_id, quantity, price, total)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([
            $id,
            $item['product_id'],
            $item['quantity'],
            $item['price'],
            $total
        ]);

        $this->db->prepare("
            UPDATE products 
            SET quantity = quantity + ?
            WHERE id = ? AND school_id = ?
        ")->execute([
            $item['quantity'],
            $item['product_id'],
            $schoolId
        ]);
    }

    $this->db->commit();
}
}