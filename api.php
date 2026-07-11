<?php
// ============================================================
// ShopMate API — PHP + MySQL Backend
// Place this file alongside shopmate.html in htdocs/shopmate/
// ============================================================

// Allow cross-origin requests and all methods
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Suppress warnings from showing in JSON output
error_reporting(0);
ini_set('display_errors', 0);

// ── Read DB credentials from query params ──
$DB_HOST = $_GET['db_host'] ?? 'localhost';
$DB_PORT = $_GET['db_port'] ?? '3306';
$DB_NAME = $_GET['db_name'] ?? 'shopmate';
$DB_USER = $_GET['db_user'] ?? 'root';
$DB_PASS = $_GET['db_pass'] ?? '';

function db() {
    global $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASS;
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (Exception $e) {
        resp(500, ['error' => 'DB Error: ' . $e->getMessage()]);
    }
    return $pdo;
}

function resp($code, $data) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function body() {
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? []) : [];
}

$method = $_SERVER['REQUEST_METHOD'];
$table  = $_GET['table'] ?? '';
$id     = isset($_GET['id']) ? intval($_GET['id']) : null;

switch ($table) {

    // ════════════════════════════════
    // PING — test connection
    // ════════════════════════════════
    case 'ping':
        db();
        resp(200, [
            'status' => 'ok',
            'db'     => $DB_NAME,
            'host'   => $DB_HOST,
            'time'   => date('Y-m-d H:i:s')
        ]);

    // ════════════════════════════════
    // PRODUCTS
    // ════════════════════════════════
    case 'products':
        if ($method === 'GET') {
            $rows = db()->query("SELECT * FROM products ORDER BY name")->fetchAll();
            resp(200, $rows);
        }
        if ($method === 'POST') {
            $d  = body();
            $bc = !empty($d['barcode']) ? $d['barcode'] : ('SBG' . substr(time(), -7) . rand(10,99));
            $st = db()->prepare(
                "INSERT INTO products (name,category,buy_price,sell_price,stock,min_stock,unit,barcode,gst_rate,hsn_code)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            );
            $st->execute([
                $d['name'], $d['category'] ?? 'Other',
                $d['buy_price'] ?? 0, $d['sell_price'] ?? 0,
                $d['stock'] ?? 0, $d['min_stock'] ?? 5,
                $d['unit'] ?? 'pcs', $bc, $d['gst_rate'] ?? 0, $d['hsn_code'] ?? ''
            ]);
            $row = db()->query("SELECT * FROM products WHERE id=".db()->lastInsertId())->fetch();
            resp(201, $row);
        }
        if ($method === 'PUT' && $id) {
            $d  = body();
            $bc = !empty($d['barcode']) ? $d['barcode'] : ('SBG'.substr(time(),-7));
            $st = db()->prepare(
                "UPDATE products SET name=?,category=?,buy_price=?,sell_price=?,stock=?,min_stock=?,unit=?,barcode=?,gst_rate=?,hsn_code=?
                 WHERE id=?"
            );
            $st->execute([
                $d['name'], $d['category'] ?? 'Other',
                $d['buy_price'] ?? 0, $d['sell_price'] ?? 0,
                $d['stock'] ?? 0, $d['min_stock'] ?? 5,
                $d['unit'] ?? 'pcs', $bc, $d['gst_rate'] ?? 0, $d['hsn_code'] ?? '', $id
            ]);
            resp(200, ['success' => true]);
        }
        if ($method === 'DELETE' && $id) {
            db()->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
            resp(200, ['success' => true]);
        }
        resp(400, ['error' => 'Bad request']);

    // ════════════════════════════════
    // SALES
    // ════════════════════════════════
    case 'sales':
        if ($method === 'GET') {
            $rows = db()->query("SELECT * FROM sales ORDER BY created_at DESC")->fetchAll();
            foreach ($rows as &$s) {
                $st = db()->prepare("SELECT * FROM sale_items WHERE sale_id=?");
                $st->execute([$s['id']]);
                $s['items'] = $st->fetchAll();
            }
            resp(200, $rows);
        }
        if ($method === 'POST') {
            $d = body();
            db()->beginTransaction();
            try {
                $st = db()->prepare(
                    "INSERT INTO sales (invoice_no,customer_name,customer_phone,customer_address,customer_gstin,customer_state,subtotal,discount,gst_amount,total,payment_method,payment_status,narration)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
                );
                $st->execute([
                    $d['invoice_no'], $d['customer_name'] ?? '',
                    $d['customer_phone'] ?? '', $d['customer_address'] ?? '',
                    $d['customer_gstin'] ?? '', $d['customer_state'] ?? '',
                    $d['subtotal'] ?? 0, $d['discount'] ?? 0, $d['gst_amount'] ?? 0,
                    $d['total'] ?? 0, $d['payment_method'] ?? 'cash',
                    $d['payment_status'] ?? 'paid', $d['narration'] ?? ''
                ]);
                $sid = db()->lastInsertId();
                foreach (($d['items'] ?? []) as $item) {
                    $si = db()->prepare(
                        "INSERT INTO sale_items (sale_id,product_id,product_name,quantity,unit_price,total_price,gst_rate,hsn_code)
                         VALUES (?,?,?,?,?,?,?,?)"
                    );
                    // get hsn from products if not provided
                    $hsn = $item['hsn_code'] ?? '';
                    if(empty($hsn) && !empty($item['product_id'])) {
                        $ph = db()->prepare("SELECT hsn_code FROM products WHERE id=?");
                        $ph->execute([$item['product_id']]);
                        $pr = $ph->fetch();
                        if($pr) $hsn = $pr['hsn_code'] ?? '';
                    }
                    $si->execute([
                        $sid, $item['product_id'] ?? null,
                        $item['product_name'], $item['quantity'],
                        $item['unit_price'], $item['total_price'],
                        $item['gst_rate'] ?? 0, $hsn
                    ]);
                    if (!empty($item['product_id'])) {
                        db()->prepare("UPDATE products SET stock=GREATEST(0,stock-?) WHERE id=?")
                            ->execute([$item['quantity'], $item['product_id']]);
                    }
                }
                db()->commit();
                $sale = db()->query("SELECT * FROM sales WHERE id=$sid")->fetch();
                $sti  = db()->prepare("SELECT * FROM sale_items WHERE sale_id=?");
                $sti->execute([$sid]);
                $sale['items'] = $sti->fetchAll();
                resp(201, $sale);
            } catch (Exception $e) {
                db()->rollBack();
                resp(500, ['error' => $e->getMessage()]);
            }
        }
        if ($method === 'PUT' && $id) {
            $d = body();
            $st = db()->prepare("UPDATE sales SET customer_name=?,customer_phone=?,customer_address=?,customer_gstin=?,customer_state=?,payment_method=?,payment_status=?,discount=?,total=?,narration=? WHERE id=?");
            $st->execute([$d['customer_name']??'',$d['customer_phone']??'',$d['customer_address']??'',$d['customer_gstin']??'',$d['customer_state']??'',$d['payment_method']??'cash',$d['payment_status']??'paid',$d['discount']??0,$d['total']??0,$d['narration']??'',$id]);
            resp(200, ['success' => true]);
        }
        if ($method === 'DELETE' && $id) {
            db()->prepare("DELETE FROM sale_items WHERE sale_id=?")->execute([$id]);
            db()->prepare("DELETE FROM sales WHERE id=?")->execute([$id]);
            resp(200, ['success' => true]);
        }
        resp(400, ['error' => 'Bad request']);

    // ════════════════════════════════
    // PURCHASES
    // ════════════════════════════════
    case 'purchases':
        if ($method === 'GET') {
            $rows = db()->query("SELECT * FROM purchases ORDER BY created_at DESC")->fetchAll();
            foreach ($rows as &$p) {
                $st = db()->prepare("SELECT * FROM purchase_items WHERE purchase_id=?");
                $st->execute([$p['id']]);
                $p['items'] = $st->fetchAll();
            }
            resp(200, $rows);
        }
        if ($method === 'POST') {
            $d = body();
            db()->beginTransaction();
            try {
                $st = db()->prepare(
                    "INSERT INTO purchases (supplier_name,bill_no,total,payment_status,purchase_date,narration)
                     VALUES (?,?,?,?,?,?)"
                );
                $st->execute([
                    $d['supplier_name'] ?? '', $d['bill_no'] ?? '',
                    $d['total'] ?? 0, $d['payment_status'] ?? 'paid',
                    $d['purchase_date'] ?? date('Y-m-d'), $d['narration'] ?? ''
                ]);
                $pid = db()->lastInsertId();
                foreach (($d['items'] ?? []) as $item) {
                    $si = db()->prepare(
                        "INSERT INTO purchase_items (purchase_id,product_id,product_name,quantity,unit_price,total_price)
                         VALUES (?,?,?,?,?,?)"
                    );
                    $si->execute([
                        $pid, $item['product_id'] ?? null,
                        $item['product_name'], $item['quantity'],
                        $item['unit_price'], $item['total_price']
                    ]);
                    if (!empty($item['product_id'])) {
                        db()->prepare("UPDATE products SET stock=stock+? WHERE id=?")
                            ->execute([$item['quantity'], $item['product_id']]);
                    }
                }
                db()->commit();
                $pur = db()->query("SELECT * FROM purchases WHERE id=$pid")->fetch();
                $sti = db()->prepare("SELECT * FROM purchase_items WHERE purchase_id=?");
                $sti->execute([$pid]);
                $pur['items'] = $sti->fetchAll();
                resp(201, $pur);
            } catch (Exception $e) {
                db()->rollBack();
                resp(500, ['error' => $e->getMessage()]);
            }
        }
        if ($method === 'PUT' && $id) {
            $d = body();
            db()->beginTransaction();
            try {
                // If items are supplied, replace the line items and correct stock:
                // reverse the stock added by the old items, then apply the new items.
                if (isset($d['items']) && is_array($d['items'])) {
                    $old = db()->prepare("SELECT product_id, quantity FROM purchase_items WHERE purchase_id=?");
                    $old->execute([$id]);
                    foreach ($old->fetchAll() as $oi) {
                        if (!empty($oi['product_id'])) {
                            db()->prepare("UPDATE products SET stock=GREATEST(0,stock-?) WHERE id=?")
                                ->execute([$oi['quantity'], $oi['product_id']]);
                        }
                    }
                    db()->prepare("DELETE FROM purchase_items WHERE purchase_id=?")->execute([$id]);

                    foreach ($d['items'] as $item) {
                        $si = db()->prepare(
                            "INSERT INTO purchase_items (purchase_id,product_id,product_name,quantity,unit_price,total_price)
                             VALUES (?,?,?,?,?,?)"
                        );
                        $si->execute([
                            $id, $item['product_id'] ?? null,
                            $item['product_name'], $item['quantity'],
                            $item['unit_price'], $item['total_price']
                        ]);
                        if (!empty($item['product_id'])) {
                            db()->prepare("UPDATE products SET stock=stock+? WHERE id=?")
                                ->execute([$item['quantity'], $item['product_id']]);
                        }
                    }
                }
                $st = db()->prepare("UPDATE purchases SET supplier_name=?,bill_no=?,purchase_date=?,total=?,payment_status=?,narration=? WHERE id=?");
                $st->execute([$d['supplier_name']??'',$d['bill_no']??'',$d['purchase_date']??date('Y-m-d'),$d['total']??0,$d['payment_status']??'paid',$d['narration']??'',$id]);
                db()->commit();
                resp(200, ['success' => true]);
            } catch (Exception $e) {
                db()->rollBack();
                resp(500, ['error' => $e->getMessage()]);
            }
        }
        if ($method === 'DELETE' && $id) {
            db()->prepare("DELETE FROM purchase_items WHERE purchase_id=?")->execute([$id]);
            db()->prepare("DELETE FROM purchases WHERE id=?")->execute([$id]);
            resp(200, ['success' => true]);
        }
        resp(400, ['error' => 'Bad request']);

    // ════════════════════════════════
    // PAYMENTS
    // ════════════════════════════════
    case 'payments':
        if ($method === 'GET') {
            $rows = db()->query("SELECT * FROM payments ORDER BY created_at DESC")->fetchAll();
            resp(200, $rows);
        }
        if ($method === 'POST') {
            $d  = body();
            $st = db()->prepare(
                "INSERT INTO payments (type,party_name,amount,method,reference,notes,payment_date)
                 VALUES (?,?,?,?,?,?,?)"
            );
            $st->execute([
                $d['type'] ?? 'received', $d['party_name'] ?? '',
                $d['amount'] ?? 0, $d['method'] ?? 'Cash',
                $d['reference'] ?? '', $d['notes'] ?? '',
                $d['payment_date'] ?? date('Y-m-d')
            ]);
            resp(201, ['id' => db()->lastInsertId(), 'success' => true]);
        }
        if ($method === 'DELETE' && $id) {
            db()->prepare("DELETE FROM payments WHERE id=?")->execute([$id]);
            resp(200, ['success' => true]);
        }
        resp(400, ['error' => 'Bad request']);

    // ════════════════════════════════
    // SHOP SETTINGS
    // ════════════════════════════════
    case 'shop_settings':
        if ($method === 'GET') {
            $rows = db()->query("SELECT setting_key, setting_val FROM shop_settings")->fetchAll();
            $result = [];
            foreach ($rows as $r) $result[$r['setting_key']] = $r['setting_val'];
            resp(200, $result);
        }
        if ($method === 'POST') {
            $d = body();
            $st = db()->prepare("INSERT INTO shop_settings (setting_key, setting_val) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_val=?, updated_at=NOW()");
            foreach ($d as $key => $val) {
                $st->execute([$key, $val, $val]);
            }
            resp(200, ['success' => true]);
        }
        resp(400, ['error' => 'Bad request']);

    default:
        resp(404, ['error' => 'Unknown endpoint: ' . $table]);
}
