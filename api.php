<?php
header('Content-Type: application/json');
require_once 'config.php';

$conn = getDbConnection();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Helper to get JSON input
function getJsonInput() {
    return json_decode(file_get_contents('php://input'), true);
}

switch ($action) {
    // --- CATEGORIES ---
    case 'get_categories':
        $result = $conn->query("SELECT * FROM categories ORDER BY id DESC");
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        break;

    case 'add_category':
        $data = getJsonInput();
        $name = $data['name'];
        $id = isset($data['id']) ? (int)$data['id'] : 0;

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE categories SET name=? WHERE id=?");
            $stmt->bind_param("si", $name, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->bind_param("s", $name);
        }

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Category saved"]);
        } else {
            echo json_encode(["success" => false, "message" => $stmt->error]);
        }
        break;

    case 'delete_category':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM categories WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) echo json_encode(["success" => true]);
        else echo json_encode(["success" => false, "message" => $stmt->error]);
        break;

    // --- SUPPLIERS ---
    case 'get_suppliers':
        $result = $conn->query("SELECT * FROM suppliers ORDER BY id DESC");
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        break;

    case 'add_supplier':
        $data = getJsonInput();
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE suppliers SET name=?, company=?, phone=?, email=?, address=? WHERE id=?");
            $stmt->bind_param("sssssi", $data['name'], $data['company'], $data['phone'], $data['email'], $data['address'], $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO suppliers (name, company, phone, email, address) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $data['name'], $data['company'], $data['phone'], $data['email'], $data['address']);
        }
        if ($stmt->execute()) echo json_encode(["success" => true]);
        else echo json_encode(["success" => false, "message" => $stmt->error]);
        break;

    case 'delete_supplier':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM suppliers WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) echo json_encode(["success" => true]);
        else echo json_encode(["success" => false, "message" => $stmt->error]);
        break;

    // --- PRODUCTS ---
    case 'get_products':
        $sql = "SELECT p.*, c.name as category_name, s.company as supplier_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN suppliers s ON p.supplier_id = s.id 
                ORDER BY p.id DESC";
        echo json_encode($conn->query($sql)->fetch_all(MYSQLI_ASSOC));
        break;

    case 'add_product':
        $data = getJsonInput();
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE products SET name=?, category_id=?, brand=?, purchase_price=?, sale_price=?, quantity=?, supplier_id=? WHERE id=?");
            $stmt->bind_param("sisdddii", $data['name'], $data['category_id'], $data['brand'], $data['purchase'], $data['sale'], $data['qty'], $data['supplier_id'], $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO products (name, category_id, brand, purchase_price, sale_price, quantity, supplier_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sisdddi", $data['name'], $data['category_id'], $data['brand'], $data['purchase'], $data['sale'], $data['qty'], $data['supplier_id']);
        }
        if ($stmt->execute()) echo json_encode(["success" => true]);
        else echo json_encode(["success" => false, "message" => $stmt->error]);
        break;

    case 'delete_product':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM products WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) echo json_encode(["success" => true]);
        else echo json_encode(["success" => false, "message" => $stmt->error]);
        break;

    // --- PURCHASES ---
    case 'get_purchases':
        $sql = "SELECT pu.*, s.company as supplier_name, pr.name as product_name 
                FROM purchases pu 
                LEFT JOIN suppliers s ON pu.supplier_id = s.id 
                LEFT JOIN products pr ON pu.product_id = pr.id 
                ORDER BY pu.id DESC";
        echo json_encode($conn->query($sql)->fetch_all(MYSQLI_ASSOC));
        break;

    case 'add_purchase':
        $data = getJsonInput();
        $db_id = isset($data['db_id']) ? (int)$data['db_id'] : 0;
        $sup_id = $data['supplier_id'];
        $prod_id = $data['product_id'];
        $qty = $data['qty'];
        $price = $data['price'];
        $total = $data['total'];
        $date = $data['date'];

        $conn->begin_transaction();
        try {
            if ($db_id > 0) {
                // Reverse old stock
                $stmt_old = $conn->prepare("SELECT product_id, quantity FROM purchases WHERE id=?");
                $stmt_old->bind_param("i", $db_id);
                $stmt_old->execute();
                $old_p = $stmt_old->get_result()->fetch_assoc();
                if ($old_p) {
                    $old_qty = $old_p['quantity'];
                    $old_prod = $old_p['product_id'];
                    $conn->query("UPDATE products SET quantity = quantity - $old_qty WHERE id=$old_prod");
                }
                
                $stmt = $conn->prepare("UPDATE purchases SET supplier_id=?, product_id=?, quantity=?, price=?, total=?, purchase_date=? WHERE id=?");
                $stmt->bind_param("iidddsi", $sup_id, $prod_id, $qty, $price, $total, $date, $db_id);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("INSERT INTO purchases (supplier_id, product_id, quantity, price, total, purchase_date) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iiddds", $sup_id, $prod_id, $qty, $price, $total, $date);
                $stmt->execute();
            }
            
            $stmt2 = $conn->prepare("UPDATE products SET quantity = quantity + ? WHERE id = ?");
            $stmt2->bind_param("ii", $qty, $prod_id);
            $stmt2->execute();
            
            $conn->commit();
            echo json_encode(["success" => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
        break;

    // --- SALES ---
    case 'get_sales':
        $sql = "SELECT sa.*, pr.name as product_name 
                FROM sales sa 
                LEFT JOIN products pr ON sa.product_id = pr.id 
                ORDER BY sa.id DESC";
        echo json_encode($conn->query($sql)->fetch_all(MYSQLI_ASSOC));
        break;

    case 'add_sale':
        $data = getJsonInput();
        $db_id = isset($data['db_id']) ? (int)$data['db_id'] : 0;
        $customer = $data['customer'];
        $prod_id = (int)$data['product_id'];
        $qty = (int)$data['qty'];
        $price = $data['price'];
        $total = $data['total'];
        $date = $data['date'];

        $conn->begin_transaction();
        try {
            if ($db_id > 0) {
                // Reverse old stock
                $stmt_old = $conn->prepare("SELECT product_id, quantity FROM sales WHERE id=?");
                $stmt_old->bind_param("i", $db_id);
                $stmt_old->execute();
                $old_s = $stmt_old->get_result()->fetch_assoc();
                if ($old_s) {
                    $old_qty = $old_s['quantity'];
                    $old_prod = $old_s['product_id'];
                    $conn->query("UPDATE products SET quantity = quantity + $old_qty WHERE id=$old_prod");
                }
            }

            $stmt_stock = $conn->prepare("SELECT quantity FROM products WHERE id = ?");
            $stmt_stock->bind_param("i", $prod_id);
            $stmt_stock->execute();
            $curr_stock = $stmt_stock->get_result()->fetch_assoc()['quantity'];
            
            if ($curr_stock < $qty) throw new Exception("Insufficient stock");

            if ($db_id > 0) {
                $stmt = $conn->prepare("UPDATE sales SET customer_name=?, product_id=?, quantity=?, price=?, total=?, sale_date=? WHERE id=?");
                $stmt->bind_param("siiddsi", $customer, $prod_id, $qty, $price, $total, $date, $db_id);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("INSERT INTO sales (customer_name, product_id, quantity, price, total, sale_date) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("siidds", $customer, $prod_id, $qty, $price, $total, $date);
                $stmt->execute();
            }
            
            $stmt2 = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
            $stmt2->bind_param("ii", $qty, $prod_id);
            $stmt2->execute();
            
            $conn->commit();
            echo json_encode(["success" => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
        break;

    // --- DASHBOARD ---
    case 'get_stats':
        $total_p = $conn->query("SELECT COUNT(*) FROM products")->fetch_row()[0];
        $total_s = $conn->query("SELECT COUNT(*) FROM suppliers")->fetch_row()[0];
        $active_o = $conn->query("SELECT COUNT(*) FROM sales WHERE sale_date = CURDATE()")->fetch_row()[0];
        $earnings = $conn->query("SELECT SUM(total) FROM sales")->fetch_row()[0];
        
        echo json_encode([
            "total_products" => $total_p,
            "total_suppliers" => $total_s,
            "active_orders" => $active_o,
            "total_earnings" => (float)($earnings ?? 0)
        ]);
        break;

    case 'get_recent_activity':
        $sales = $conn->query("SELECT 'sale' as type, customer_name as party, total, sale_date as date FROM sales ORDER BY id DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
        $purchases = $conn->query("SELECT 'purchase' as type, s.company as party, total, purchase_date as date FROM purchases pu JOIN suppliers s ON pu.supplier_id=s.id ORDER BY pu.id DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
        $activity = array_merge($sales, $purchases);
        usort($activity, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
        echo json_encode(array_slice($activity, 0, 8));
        break;

    case 'update_profile':
        $data = getJsonInput();
        $id = (int)$data['id'];
        if (isset($data['password']) && !empty($data['password'])) {
            $hashed = password_hash($data['password'], PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, password=? WHERE id=?");
            $stmt->bind_param("sssi", $data['name'], $data['email'], $hashed, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name=?, email=? WHERE id=?");
            $stmt->bind_param("ssi", $data['name'], $data['email'], $id);
        }
        if ($stmt->execute()) echo json_encode(["success" => true]);
        else echo json_encode(["success" => false, "message" => $stmt->error]);
        break;

    default:
        echo json_encode(["message" => "Action not found"]);
        break;
}
$conn->close();
