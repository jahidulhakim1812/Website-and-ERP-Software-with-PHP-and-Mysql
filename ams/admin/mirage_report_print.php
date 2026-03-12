<?php
/**
 * mirage_report_print.php
 * Print‑optimized MIRAGE report – matches mirage_report.php layout.
 * A4 format with company letterhead, subcontractor details, and full batch breakdown.
 * Batch total now shows only additional costs (matching grand total logic).
 */

session_start();

// --- Authentication (optional, for session variables) ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'Alexander Pierce';
    $_SESSION['role'] = 'admin';
}

// --- Database connection ---
$DB_HOST = 'localhost';
$DB_NAME = 'alihairw_alisoft';
$DB_USER = 'alihairw_ali';
$DB_PASS = 'x5.H(8xkh3H7EY';

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $ex) {
    die("Database connection failed: " . $ex->getMessage());
}

// --- Get subcontractor ID from URL ---
$selected_sub_id = isset($_GET['subcontractor_id']) ? (int)$_GET['subcontractor_id'] : 0;
$subcontractor = null;
$batches = [];
$grand_pieces = 0;
$grand_costs = 0;      // Only additional costs (as in mirage_report.php)

if ($selected_sub_id > 0) {
    // Fetch subcontractor details
    $stmt = $pdo->prepare("SELECT * FROM subcontractors WHERE id = ?");
    $stmt->execute([$selected_sub_id]);
    $subcontractor = $stmt->fetch();

    if ($subcontractor) {
        // Fetch batches for this subcontractor
        $stmt = $pdo->prepare("
            SELECT 
                b.id,
                b.lod_name,
                b.production_date
            FROM wigs_batches b
            WHERE b.subcontractor_id = ?
            ORDER BY b.production_date DESC, b.id DESC
        ");
        $stmt->execute([$selected_sub_id]);
        $batches = $stmt->fetchAll();

        // For each batch, get items and costs
        foreach ($batches as &$batch) {
            // Items (Top & Skin) – same as mirage_report.php
            $stmt = $pdo->prepare("
                SELECT 
                    type,
                    size,
                    per_piece,
                    unit,
                    quantity,
                    (per_piece * quantity) AS line_total
                FROM wigs_batch_items
                WHERE batch_id = ?
                ORDER BY type, size
            ");
            $stmt->execute([$batch['id']]);
            $batch['items'] = $stmt->fetchAll();

            // Additional costs
            $stmt = $pdo->prepare("
                SELECT 
                    size,
                    description,
                    quantity,
                    unit_price,
                    (quantity * unit_price) AS line_total
                FROM wigs_batch_costs
                WHERE batch_id = ?
                ORDER BY id
            ");
            $stmt->execute([$batch['id']]);
            $batch['costs'] = $stmt->fetchAll();

            // Calculate totals
            $batch['items_subtotal'] = array_sum(array_column($batch['items'], 'line_total'));
            $batch['costs_subtotal'] = array_sum(array_column($batch['costs'], 'line_total'));
            $batch['total_pieces'] = array_sum(array_column($batch['items'], 'quantity'));
        }
        unset($batch);

        // Grand totals
        $grand_pieces = array_sum(array_column($batches, 'total_pieces'));
        $grand_costs  = array_sum(array_column($batches, 'costs_subtotal'));  // only additional costs, as in original report
    }
}

function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MIRAGE Report – Professional Print</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Reset and base styles – A4 friendly */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif;
            background: white; 
            color: #1e293b; 
            font-size: 11pt;
            line-height: 1.5;
            padding: 20px;
        }
        .print-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
        }
        
        /* Letterhead header */
        .letterhead {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #4F46E5;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .company-info h1 {
            font-size: 24pt;
            font-weight: 700;
            color: #4F46E5;
            margin-bottom: 5px;
        }
        .company-info .tagline {
            font-size: 10pt;
            color: #64748b;
        }
        .report-meta {
            text-align: right;
        }
        .report-meta .report-title {
            font-size: 18pt;
            font-weight: 600;
            color: #0f172a;
        }
        .report-meta .report-date {
            font-size: 10pt;
            color: #64748b;
            margin-top: 5px;
        }
        
        /* Subcontractor details card */
        .subcontractor-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            display: flex;
            gap: 20px;
            align-items: start;
        }
        .sub-photo {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #cbd5e1;
            background: #fff;
        }
        .sub-details {
            flex: 1;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px 20px;
        }
        .detail-item {
            display: flex;
            gap: 5px;
        }
        .detail-label {
            font-weight: 600;
            color: #334155;
            min-width: 100px;
        }
        .detail-value {
            color: #0f172a;
        }
        
        /* Batch cards – matches mirage_report.php card style */
        .batch-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            page-break-inside: avoid;
            background: white;
        }
        .batch-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f1f5f9;
            margin: -15px -15px 15px -15px;
            padding: 10px 15px;
            border-radius: 8px 8px 0 0;
            font-weight: 600;
            font-size: 12pt;
            color: #0f172a;
        }
        .batch-header .date {
            font-weight: normal;
            color: #475569;
        }
        
        /* Tables – same as mirage_report.php but print‑optimised */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            border: 1px solid #cbd5e1;
            margin-bottom: 10px;
        }
        th {
            background: #f1f5f9;
            font-weight: 600;
            padding: 8px 4px;
            text-align: center;
            border: 1px solid #cbd5e1;
            color: #1e293b;
        }
        td {
            padding: 6px 4px;
            border: 1px solid #cbd5e1;
            text-align: center;
        }
        td:first-child {
            text-align: left;
        }
        .totals-row {
            background: #fef9c3;
            font-weight: 600;
        }
        
        /* Batch total line – now only additional costs */
        .batch-total-line {
            margin-top: 10px;
            font-weight: 600;
            display: flex;
            justify-content: flex-end;
            border-top: 1px dashed #cbd5e1;
            padding-top: 8px;
        }
        
        /* Grand total – matches mirage_report.php grand-total */
        .grand-total {
            background: #4F46E5;
            color: white;
            font-size: 14pt;
            font-weight: 700;
            padding: 15px 20px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        
        /* Print button and back link (hidden when printing) */
        .action-bar {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            background: #4F46E5;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11pt;
        }
        .btn:hover { background: #4338ca; }
        .btn-secondary {
            background: #64748b;
        }
        .btn-secondary:hover { background: #475569; }
        .back-link {
            color: #4F46E5;
            text-decoration: none;
            font-weight: 500;
        }
        
        /* A4 print settings */
        @page {
            size: A4;
            margin: 2cm;
        }
        @media print {
            .action-bar {
                display: none;
            }
            body { padding: 0; background: white; }
            .subcontractor-card { background: none; border: 1px solid #000; }
            .batch-header { background: #eee; }
            th { background: #eee; }
            .grand-total { background: #333; color: white; }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <!-- Action buttons (hidden when printing) -->
        <div class="action-bar">
            <a href="mirage_reports.php?subcontractor_id=<?= $selected_sub_id ?>" class="back-link">← Back to Report</a>
            <button class="btn" onclick="window.print()"><span style="margin-right:5px;">🖨️</span> Print / Save as PDF</button>
        </div>

        <!-- Letterhead -->
        <div class="letterhead">
            <div class="company-info">
                <h1>NEXUS ADMIN</h1>
                <div class="tagline">Precision Manufacturing Reports</div>
            </div>
            <div class="report-meta">
                <div class="report-title">MIRAGE PRODUCTION REPORT</div>
                <div class="report-date">Generated: <?= date('F j, Y') ?></div>
            </div>
        </div>

        <?php if ($subcontractor): ?>
            <!-- Subcontractor Details Card -->
            <div class="subcontractor-card">
                <?php if (!empty($subcontractor['photo'])): ?>
                    <img src="<?= e($subcontractor['photo']) ?>" alt="Subcontractor Photo" class="sub-photo">
                <?php else: ?>
                    <div class="sub-photo" style="background: #e2e8f0; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 10pt;">No Photo</div>
                <?php endif; ?>
                <div class="sub-details">
                    <div class="detail-item"><span class="detail-label">Company:</span><span class="detail-value"><?= e($subcontractor['company_name']) ?></span></div>
                    <div class="detail-item"><span class="detail-label">Contact Person:</span><span class="detail-value"><?= e($subcontractor['contact_person'] ?? '—') ?></span></div>
                    <div class="detail-item"><span class="detail-label">Phone:</span><span class="detail-value"><?= e($subcontractor['phone'] ?? '—') ?></span></div>
                    <div class="detail-item"><span class="detail-label">Email:</span><span class="detail-value"><?= e($subcontractor['email'] ?? '—') ?></span></div>
                    <div class="detail-item"><span class="detail-label">Address:</span><span class="detail-value"><?= e($subcontractor['address'] ?? '—') ?></span></div>
                    <div class="detail-item"><span class="detail-label">NID/Registration:</span><span class="detail-value"><?= e($subcontractor['nid'] ?? '—') ?></span></div>
                </div>
            </div>

            <?php if (empty($batches)): ?>
                <div style="text-align: center; padding: 40px; color: #64748b;">No batches found for this subcontractor.</div>
            <?php else: ?>
                <?php foreach ($batches as $batch): ?>
                    <div class="batch-card">
                        <div class="batch-header">
                            <span><?= e($batch['lod_name']) ?> (Batch #<?= $batch['id'] ?>)</span>
                            <span class="date">Production Date: <?= e($batch['production_date']) ?></span>
                        </div>

                        <!-- Items Table – exactly as mirage_report.php -->
                        <?php if (!empty($batch['items'])): ?>
                            <h4 style="margin-bottom: 5px;">Wig Pieces</h4>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Size</th>
                                        <th>Per Piece</th>
                                        <th>Unit</th>
                                        <th>Quantity</th>
                                        <th>Hair Total</th>   <!-- matches "Hair Total" column in mirage_report.php -->
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $item_total = 0;
                                    foreach ($batch['items'] as $item): 
                                        $item_total += $item['line_total'];
                                    ?>
                                        <tr>
                                            <td><?= e($item['type']) ?></td>
                                            <td><?= e($item['size']) ?></td>
                                            <td><?= e($item['per_piece']) ?></td>
                                            <td><?= e($item['unit']) ?></td>
                                            <td><?= e($item['quantity']) ?></td>
                                            <td><?= number_format($item['line_total'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="totals-row">
                                    <tr>
                                        <td colspan="5" style="text-align: right;">Items Subtotal:</td>
                                        <td><?= number_format($item_total, 2) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        <?php endif; ?>

                        <!-- Additional Costs Table -->
                        <?php if (!empty($batch['costs'])): ?>
                            <h4 style="margin: 10px 0 5px;">Additional Costs</h4>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Size</th>
                                        <th>Description</th>
                                        <th>Quantity</th>
                                        <th>Unit Price (BDT)</th>
                                        <th>Line Total (BDT)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $cost_total = 0;
                                    foreach ($batch['costs'] as $cost): 
                                        $cost_total += $cost['line_total'];
                                    ?>
                                        <tr>
                                            <td><?= e($cost['size']) ?></td>
                                            <td><?= e($cost['description']) ?></td>
                                            <td><?= e($cost['quantity']) ?></td>
                                            <td><?= number_format($cost['unit_price'], 2) ?></td>
                                            <td><?= number_format($cost['line_total'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="totals-row">
                                    <tr>
                                        <td colspan="4" style="text-align: right;">Costs Subtotal:</td>
                                        <td><?= number_format($cost_total, 2) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        <?php endif; ?>

                        <!-- Batch total line – now only additional costs -->
                        <div class="batch-total-line">
                            Batch Additional Costs Total: <?= number_format($batch['costs_subtotal'], 2) ?> BDT
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Grand Totals – exactly as mirage_report.php (only additional costs) -->
                <div class="grand-total">
                    <span>GRAND TOTAL (additional costs only)</span>
                    <span><?= number_format($grand_costs, 2) ?> BDT</span>
                </div>
                <div style="margin-top: 10px; text-align: right; color: #64748b;">
                    Total pieces: <?= number_format($grand_pieces) ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #64748b;">Invalid subcontractor selection. Please go back and choose a subcontractor.</div>
        <?php endif; ?>

        <!-- Footer (optional) -->
        <div style="margin-top: 30px; text-align: center; font-size: 9pt; color: #94a3b8; border-top: 1px dashed #cbd5e1; padding-top: 10px;">
            This is a computer‑generated report. Valid without signature.
        </div>
    </div>
</body>
</html>