<?php

require_once __DIR__ . '/../Models/StockTransactionModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class StockTransactionController
{
    private $model;

    public function __construct(PDO $db)
    {
        $this->model = new StockTransactionModel($db);
    }

    public function delete($id)
    {
        $user = JwtHelper::getUserFromToken();

        if (!in_array($user['role'], ['ADMIN', 'STORE_ADMIN'])) {
            Response::json(["message" => "Forbidden"], 403);
        }

        try {

            $this->model->delete(
                $id,
                $user['school_id']
            );

            Response::json([
                "message" => "Transaction deleted"
            ]);

        } catch (Exception $e) {

            Response::json([
                "message" => $e->getMessage()
            ], 400);
        }
    }
}