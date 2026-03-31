<?php
class MasterClassController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function index()
    {
        $stmt = $this->db->query("
            SELECT id, name FROM master_classes ORDER BY order_no
        ");

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::json($data);
    }
}