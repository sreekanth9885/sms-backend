<?php

class StatusController
{
    public function index()
    {
        Response::json([
            "status" => "ok",
            "service" => "sms-backend",
            "environment" => "production",
            "timestamp" => date("Y-m-d H:i:s")
        ]);
    }
}
