<?php

class StoreDashboardModel
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getStats($schoolId)
    {
        // 🔹 Agencies
        $agencies = $this->db->prepare("
            SELECT COUNT(*) FROM agencies 
            WHERE school_id=? AND is_active=1
        ");
        $agencies->execute([$schoolId]);

        // 🔹 Categories
        $categories = $this->db->prepare("
            SELECT COUNT(*) FROM categories 
            WHERE school_id=? AND is_active=1
        ");
        $categories->execute([$schoolId]);

        // 🔹 Sub Categories
        $subCategories = $this->db->prepare("
            SELECT COUNT(*) FROM sub_categories 
            WHERE school_id=? AND is_active=1
        ");
        $subCategories->execute([$schoolId]);

        // 🔹 Products
        $products = $this->db->prepare("
            SELECT COUNT(*) FROM products 
            WHERE school_id=? AND is_active=1
        ");
        $products->execute([$schoolId]);

        // 🔥 Total stock quantity
        $stockQty = $this->db->prepare("
            SELECT COALESCE(SUM(quantity),0) FROM products 
            WHERE school_id=?
        ");
        $stockQty->execute([$schoolId]);

        // 🔥 Total purchase amount (from entries)
        $purchase = $this->db->prepare("
            SELECT COALESCE(SUM(total_amount),0) FROM stock_entries 
            WHERE school_id=?
        ");
        $purchase->execute([$schoolId]);

        // 🔥 Total stock value (important)
        $stockValue = $this->db->prepare("
            SELECT COALESCE(SUM(sei.quantity * sei.price),0)
            FROM stock_entry_items sei
            JOIN stock_entries se ON se.id = sei.stock_entry_id
            WHERE se.school_id = ?
        ");
        $stockValue->execute([$schoolId]);

        return [
            "total_agencies" => (int)$agencies->fetchColumn(),
            "total_categories" => (int)$categories->fetchColumn(),
            "total_sub_categories" => (int)$subCategories->fetchColumn(),
            "total_products" => (int)$products->fetchColumn(),
            "total_stock_quantity" => (int)$stockQty->fetchColumn(),
            "total_stock_value" => (float)$stockValue->fetchColumn(),
            "total_purchase_amount" => (float)$purchase->fetchColumn(),
        ];
    }
}