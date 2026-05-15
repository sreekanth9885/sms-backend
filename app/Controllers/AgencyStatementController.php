<?php

require_once __DIR__ . '/../Models/AgencyStatementModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class AgencyStatementController
{
    private AgencyStatementModel $model;

    public function __construct(PDO $db)
    {
        $this->model = new AgencyStatementModel($db);
    }

    public function index()
    {
        $user = JwtHelper::getUserFromToken();

        $agencyId =
            isset($_GET['agency_id'])
            ? (int)$_GET['agency_id']
            : null;

        $fromDate =
            $_GET['from_date'] ?? null;

        $toDate =
            $_GET['to_date'] ?? null;

        $invoiceNo =
            $_GET['invoice_no'] ?? null;

        $data = $this->model->getStatement(
            (int)$user['school_id'],
            $agencyId,
            $fromDate,
            $toDate,
            $invoiceNo
        );

        Response::json([
            "data" => $data
        ]);
    }
}