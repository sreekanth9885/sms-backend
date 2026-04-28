<?php

require_once __DIR__ . '/../Models/StoreDashboardModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class StoreDashboardController
{
    private $model;

    public function __construct(PDO $db)
    {
        $this->model = new StoreDashboardModel($db);
    }

    public function stats()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        $data = $this->model->getStats($user['school_id']);

        Response::json([
            "data" => $data
        ]);
    }
}