<?php

class AgencyStatementModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getStatement(
        int $schoolId,
        ?int $agencyId,
        ?string $fromDate,
        ?string $toDate,
        ?string $invoiceNo
    ): array {

        $sql = "
            SELECT
                se.id,
                se.invoice_no,
                se.invoice_date,
                se.total_amount,

                a.id AS agency_id,
                a.name AS agency_name

            FROM stock_entries se

            JOIN agencies a
                ON a.id = se.agency_id

            WHERE se.school_id = ?
              AND se.is_active = 1
        ";

        $params = [$schoolId];

        // =========================
        // FILTERS
        // =========================

        if ($agencyId) {
            $sql .= " AND se.agency_id = ?";
            $params[] = $agencyId;
        }

        if ($fromDate) {
            $sql .= " AND se.invoice_date >= ?";
            $params[] = $fromDate;
        }

        if ($toDate) {
            $sql .= " AND se.invoice_date <= ?";
            $params[] = $toDate;
        }

        if ($invoiceNo) {
            $sql .= " AND se.invoice_no = ?";
            $params[] = $invoiceNo;
        }

        $sql .= " ORDER BY se.invoice_date DESC";

        $stmt = $this->db->prepare($sql);

        $stmt->execute($params);

        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grandTotal = 0;

        foreach ($bills as &$bill) {

            $grandTotal += (float)$bill['total_amount'];

            // =========================
            // BILL ITEMS
            // =========================

            $itemsStmt = $this->db->prepare("
                SELECT

                    sei.id,
                    sei.quantity,
                    sei.price AS rate,
                    sei.total,

                    p.name AS product_name,
                    p.unit

                FROM stock_entry_items sei

                JOIN products p
                    ON p.id = sei.product_id

                WHERE sei.stock_entry_id = ?
                  AND sei.is_active = 1
            ");

            $itemsStmt->execute([$bill['id']]);

            $bill['items'] =
                $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return [
            "agency" => [
                "id" => $bills[0]['agency_id'] ?? null,
                "name" => $bills[0]['agency_name'] ?? null
            ],

            "bills" => $bills,

            "grand_total" => number_format($grandTotal, 2, '.', '')
        ];
    }
}