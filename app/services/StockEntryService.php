<?php

require_once __DIR__ . '/../Models/StockEntryModel.php';

class StockEntryService
{
    private $db;
    private $model;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->model = new StockEntryModel($db);
    }

    /*
    |--------------------------------------------------------------------------
    | ALL
    |--------------------------------------------------------------------------
    */

    public function all($user)
    {
        $this->authorize($user);

        return $this->model->all(
            $user['school_id']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create($data, $user)
    {
        $this->authorize($user);

        $this->validate($data);

        $this->db->beginTransaction();

        try {

            $entryId = $this->model->createEntry([
                'school_id'    => $user['school_id'],
                'agency_id'    => $data['agency_id'],
                'invoice_no'   => $data['invoice_no'],
                'invoice_date' => $data['invoice_date'],
                'notes'        => $data['notes'] ?? null,
                'total_amount' => $this->calculateTotal($data['items']),
                'created_by'   => $user['id']
            ]);

            $this->applyItems(
                $entryId,
                $user['school_id'],
                $data['items'],
                $user['id']
            );

            $this->db->commit();

            return $entryId;

        } catch (PDOException $e) {

            $this->db->rollBack();

            if ($e->getCode() == 23000) {
                throw new Exception(
                    "Invoice already exists"
                );
            }

            throw new Exception(
                "Unable to create stock entry"
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update($entryId, $data, $user)
    {
        $this->authorize($user);

        $this->validate($data);

        $this->db->beginTransaction();

        try {

            $oldItems = $this->model->getItems(
                $entryId
            );

            // reverse old stock

            foreach ($oldItems as $item) {

                $this->updateStock(
                    $user['school_id'],
                    $item['product_id'],
                    -$item['quantity'],
                    -$item['total'],
                    $item['price']
                );
            }

            // delete old items + transactions

            $this->model->deleteItems($entryId);

            $this->model->deleteTransactions($entryId);

            // update entry

            $this->model->updateEntry($entryId, [
                'agency_id'    => $data['agency_id'],
                'invoice_no'   => $data['invoice_no'],
                'invoice_date' => $data['invoice_date'],
                'notes'        => $data['notes'] ?? null,
                'total_amount' => $this->calculateTotal($data['items'])
            ]);

            // apply new stock

            $this->applyItems(
                $entryId,
                $user['school_id'],
                $data['items'],
                $user['id']
            );

            $this->db->commit();

        } catch (PDOException $e) {

            $this->db->rollBack();

            if ($e->getCode() == 23000) {
                throw new Exception(
                    "Invoice already exists"
                );
            }

            throw new Exception(
                "Unable to update stock entry"
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function delete($entryId, $user)
    {
        $this->authorize($user);

        $this->db->beginTransaction();

        try {

            $items = $this->model->getItems(
                $entryId
            );

            foreach ($items as $item) {

                $this->updateStock(
                    $user['school_id'],
                    $item['product_id'],
                    -$item['quantity'],
                    -$item['total'],
                    $item['price']
                );
            }

            $this->model->softDelete(
                $entryId
            );

            $this->db->commit();

        } catch (Exception $e) {

            $this->db->rollBack();

            throw new Exception(
                "Unable to delete entry"
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | APPLY ITEMS
    |--------------------------------------------------------------------------
    */

    private function applyItems(
        $entryId,
        $schoolId,
        $items,
        $userId
    ) {

        foreach ($items as $item) {

            $total = (
                $item['quantity'] *
                $item['price']
            );

            $this->model->insertItem([
                'stock_entry_id' => $entryId,
                'product_id'     => $item['product_id'],
                'quantity'       => $item['quantity'],
                'price'          => $item['price'],
                'total'          => $total
            ]);

            $this->model->insertTransaction([
                'school_id'    => $schoolId,
                'reference_id' => $entryId,
                'product_id'   => $item['product_id'],
                'quantity'     => $item['quantity'],
                'rate'         => $item['price'],
                'total'        => $total,
                'created_by'   => $userId
            ]);

            $this->updateStock(
                $schoolId,
                $item['product_id'],
                $item['quantity'],
                $total,
                $item['price']
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STOCK ENGINE
    |--------------------------------------------------------------------------
    */

    private function updateStock(
        $schoolId,
        $productId,
        $qtyChange,
        $valueChange,
        $lastRate
    ) {

        $stock = $this->model->getStock(
            $schoolId,
            $productId
        );

        // create stock

        if (!$stock) {

            if ($qtyChange < 0) {
                throw new Exception(
                    "Invalid stock operation"
                );
            }

            $this->model->createStock([
                'school_id'          => $schoolId,
                'product_id'         => $productId,
                'quantity'           => $qtyChange,
                'avg_rate'           => $lastRate,
                'last_purchase_rate' => $lastRate,
                'total_value'        => $valueChange
            ]);

            return;
        }

        $newQty = (
            $stock['quantity'] + $qtyChange
        );

        $newValue = (
            $stock['total_value'] + $valueChange
        );

        if ($newQty < 0) {
            throw new Exception(
                "Stock cannot go negative"
            );
        }

        $avgRate = $newQty > 0
            ? round($newValue / $newQty, 2)
            : 0;

        $this->model->updateStock([
            'id'                 => $stock['id'],
            'quantity'           => $newQty,
            'avg_rate'           => $avgRate,
            'last_purchase_rate' => $lastRate,
            'total_value'        => $newValue
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    private function validate($data)
    {
        if (empty($data['agency_id'])) {
            throw new Exception(
                "Agency required"
            );
        }

        if (empty($data['invoice_no'])) {
            throw new Exception(
                "Invoice required"
            );
        }

        if (empty($data['invoice_date'])) {
            throw new Exception(
                "Invoice date required"
            );
        }

        if (empty($data['items'])) {
            throw new Exception(
                "Items required"
            );
        }

        $products = [];

        foreach ($data['items'] as $item) {

            if (empty($item['product_id'])) {
                throw new Exception(
                    "Product required"
                );
            }

            if ($item['quantity'] <= 0) {
                throw new Exception(
                    "Quantity must be greater than 0"
                );
            }

            if ($item['price'] < 0) {
                throw new Exception(
                    "Invalid price"
                );
            }

            if (
                in_array(
                    $item['product_id'],
                    $products
                )
            ) {
                throw new Exception(
                    "Duplicate products not allowed"
                );
            }

            $products[] = $item['product_id'];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | TOTAL
    |--------------------------------------------------------------------------
    */

    private function calculateTotal($items)
    {
        $total = 0;

        foreach ($items as $item) {

            $total += (
                $item['quantity'] *
                $item['price']
            );
        }

        return $total;
    }

    /*
    |--------------------------------------------------------------------------
    | AUTH
    |--------------------------------------------------------------------------
    */

    private function authorize($user)
    {
        if (!isset($user['school_id'])) {
            throw new Exception(
                "School missing"
            );
        }

        if (
            !in_array(
                $user['role'],
                ['ADMIN', 'STORE_ADMIN']
            )
        ) {
            throw new Exception(
                "Unauthorized"
            );
        }
    }
}