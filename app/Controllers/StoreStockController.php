<?php

require_once __DIR__ . '/../Models/StoreStockModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class StoreStockController
{
    private StoreStockModel $storeStockModel;

    public function __construct(PDO $db)
    {
        $this->storeStockModel = new StoreStockModel($db);
    }

    // =====================================
    // GET ALL STOCKS
    // =====================================

    public function index()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json([
                "message" => "School context missing"
            ], 403);
        }

        $stocks = $this->storeStockModel->allBySchool(
            (int)$user['school_id']
        );

        Response::json([
            "data" => $stocks
        ]);
    }

    // =====================================
    // GET SINGLE STOCK DETAILS
    // =====================================

    public function show($productId)
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json([
                "message" => "School context missing"
            ], 403);
        }

        if (!$productId) {
            Response::json([
                "message" => "Product ID required"
            ], 422);
        }

        try {

            $stock = $this->storeStockModel->find(
                (int)$user['school_id'],
                (int)$productId
            );

            Response::json([
                "data" => $stock
            ]);

        } catch (Exception $e) {

            Response::json([
                "message" => $e->getMessage()
            ], 404);
        }
    }
}