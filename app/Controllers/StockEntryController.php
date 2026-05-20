<?php

require_once __DIR__ . '/../services/StockEntryService.php';

class StockEntryController
{
    private $service;

    public function __construct(PDO $db)
    {
        $this->service = new StockEntryService($db);
    }

    public function index()
    {
        try {

            $user = JwtHelper::getUserFromToken();

            $data = $this->service->all($user);

            Response::json([
                "data" => $data
            ]);
        } catch (Exception $e) {

            Response::json([
                "message" => $e->getMessage()
            ], 422);
        }
    }

    public function create()
    {
        $this->handle(function () {

            $user = JwtHelper::getUserFromToken();

            $data = json_decode(
                file_get_contents("php://input"),
                true
            );

            $id = $this->service->create(
                $data,
                $user
            );

            Response::json([
                "message" => "Created successfully",
                "id" => $id
            ], 201);
        });
    }

    public function update($id)
    {
        $this->handle(function () use ($id) {

            $user = JwtHelper::getUserFromToken();

            $data = json_decode(
                file_get_contents("php://input"),
                true
            );

            $this->service->update(
                $id,
                $data,
                $user
            );

            Response::json([
                "message" => "Updated successfully"
            ]);
        });
    }

    public function delete($id)
    {
        $this->handle(function () use ($id) {

            $user = JwtHelper::getUserFromToken();

            $this->service->delete(
                $id,
                $user
            );

            Response::json([
                "message" => "Deleted successfully"
            ]);
        });
    }

    private function handle($callback)
    {
        try {

            $callback();
        } catch (Exception $e) {

            Response::json([
                "message" => $e->getMessage()
            ], 422);
        }
    }
}