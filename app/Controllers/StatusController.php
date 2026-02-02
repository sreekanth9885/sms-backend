<?php

class StatusController
{
    public function index()
    {
        header("Content-Type: text/html; charset=UTF-8");

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>API Status</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #0f172a;
            color: #e5e7eb;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .card {
            background: #020617;
            border-radius: 12px;
            padding: 30px 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6);
            width: 420px;
        }

        h1 {
            margin-top: 0;
            color: #38bdf8;
            font-size: 22px;
            text-align: center;
        }

        .row {
            display: flex;
            justify-content: space-between;
            margin: 12px 0;
            font-size: 14px;
        }

        .label {
            color: #94a3b8;
        }

        .value.ok {
            color: #22c55e;
            font-weight: bold;
        }

        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>API Status</h1>

        <div class="row">
            <span class="label">Service</span>
            <span class="value">sms-backend</span>
        </div>

        <div class="row">
            <span class="label">Environment</span>
            <span class="value">production</span>
        </div>

        <div class="row">
            <span class="label">Status</span>
            <span class="value ok">OK</span>
        </div>

        <div class="row">
            <span class="label">Timestamp</span>
            <span class="value">{$this->now()}</span>
        </div>

        <div class="footer">
            © Academic Projects API
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function now(): string
    {
        return date("Y-m-d H:i:s");
    }
}
