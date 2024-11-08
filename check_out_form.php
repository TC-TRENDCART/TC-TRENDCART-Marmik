<?php
session_start();
include $_SERVER['DOCUMENT_ROOT'] . '/TC1/Customer/phpqrcode/qrlib.php';
include "db.php";

$error = array();
$showQRCode = false;
$filePath = '';

function input($data) {
    return trim(htmlspecialchars(stripslashes($data)));
}

// Fetch product details from POST and store them in session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['next'])) {
    $_SESSION['product_name'] = input($_POST['product_name'] ?? '');
    $_SESSION['size'] = input($_POST['size'] ?? '');
    $_SESSION['color'] = input($_POST['color'] ?? '');
    $_SESSION['type'] = input($_POST['type'] ?? '');
    $_SESSION['quantity'] = (int)($_POST['quantity'] ?? 1);
    $_SESSION['price'] = (float)($_POST['price'] ?? 0);
    $_SESSION['total_price'] = $_SESSION['price'] * $_SESSION['quantity'];
    $_SESSION['vendor_id'] = input($_POST['vendor_id'] ?? 0);
}

// Retrieve product details from session for display
$productName = $_SESSION['product_name'] ?? '';
$size = $_SESSION['size'] ?? '';
$color = $_SESSION['color'] ?? '';
$type = $_SESSION['type'] ?? '';
$quantity = $_SESSION['quantity'] ?? 1;
$price = $_SESSION['price'] ?? 0;
$totalPrice = $_SESSION['total_price'] ?? 0;
$vendorId = $_SESSION['vendor_id'] ?? 0;

// Fetch the vendor's UPI ID
$vendorUpi = '';
if ($vendorId) {
    $vendorQuery = "SELECT upi_id FROM vendors WHERE id = ?";
    $stmt = $conn->prepare($vendorQuery);
    $stmt->bind_param('i', $vendorId);
    $stmt->execute();
    $vendorResult = $stmt->get_result();
    if ($vendorResult->num_rows > 0) {
        $vendor = $vendorResult->fetch_assoc();
        $vendorUpi = $vendor['upi_id'];
    }
    $stmt->close();
}

// Ensure the 'qrcodes' directory exists
if (!is_dir('qrcodes')) {
    mkdir('qrcodes', 0777, true);
}

// Process form submission to generate QR code only after validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['next'])) {
    $name = input($_POST['name'] ?? '');
    $email = input($_POST['email'] ?? '');
    $contact = input($_POST['contact'] ?? '');
    $address = input($_POST['address'] ?? '');

    // Validate fields
    if (empty($name)) $error[] = "Name is required.";
    if (empty($email)) $error[] = "Email is required.";
    if (empty($contact)) $error[] = "Contact is required.";
    if (empty($address)) $error[] = "Address is required.";

    // If no errors, generate the QR code
    if (empty($error) && !empty($vendorUpi)) {
        // $sql = "INSERT INTO customer_order_info(name, email, contact, address) VALUES ('$name', '$email', '$contact', '$address')";
        $sql = "INSERT INTO customer_order_info(name, email, contact, address, item_name, price, total_price, payment_status) VALUES ('$name', '$email', '$contact', '$address', '$productName', '$price', '$totalPrice', 'pending')";

        if ($conn->query($sql)) {
            try {
                // Generate UPI QR Code
                $upiUrl = "upi://pay?pa=$vendorUpi&am=$totalPrice&cu=INR";
                $filePath = 'qrcodes/' . uniqid() . '.png';
                QRcode::png($upiUrl, $filePath, QR_ECLEVEL_L, 4);
                $_SESSION['filePath'] = $filePath;
                $showQRCode = true;
            } catch (Exception $e) {
                echo "Error generating QR code: " . $e->getMessage();
            }
        }
    }
}

// Check if QR code should be displayed (after 'next' button is clicked)
if ($showQRCode || (isset($_SESSION['filePath']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['next']))) {
    $filePath = $_SESSION['filePath'] ?? $filePath;
    $showQRCode = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Form</title>
    <style>
        /* Basic Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f4f4f9;
        }
        .container {
            display: flex;
            background-color: #fff;
            width: 80%;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .form-container {
            width: 50%;
            padding: 20px;
        }
        .form-container h2 {
            margin-bottom: 20px;
            color: #333;
            font-size: 20px;
            font-weight: bold;
            text-align: left;
        }
        .input-group {
            position: relative;
            margin-bottom: 15px;
            text-align: left;
        }
        .input-group label {
            font-size: 14px;
            color: #555;
            display: block;
            margin-bottom: 5px;
        }
        .input-group input {
            width: 100%;
            padding: 10px;
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .QRCode-button {
            width: 100%;
            padding: 12px;
            background-color: #4CAF50;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 10px;
        }
        .QRCode-button:hover {
            background-color: #45a049;
        }
        .divider {
            width: 1px;
            background-color: #ddd;
            margin: 0 20px;
        }
        .product-details {
            width: 40%;
            height: 20%;
            background-color: #f9f9f9;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: left;
        }
        .product-details h2 {
            margin-bottom: 15px;
            font-size: 18px;
            color: #333;
            font-weight: bold;
        }
        .product-details p {
            margin-bottom: 10px;
            font-size: 14px;
            color: #555;
        }
        .qr-code-container {
            text-align: center;
            margin-top: 20px;
        }
        .error-message {
            color: red;
            font-size: 14px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2>Checkout Form</h2>
            <form method="POST">
                <?php if (!empty($error)): ?>
                    <div class="error-message"><?php echo implode('<br>', $error); ?></div>
                <?php endif; ?>
                <div class="input-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" placeholder="John M. Doe" required>
                </div>
                <div class="input-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" placeholder="john@example.com" required>
                </div>
                <div class="input-group">
                    <label for="contact">Contact Number</label>
                    <input type="tel" id="contact" name="contact" value="<?php echo htmlspecialchars($contact ?? ''); ?>" placeholder="Contact Number" required>
                </div>
                <div class="input-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($address ?? ''); ?>" placeholder="542 W. 15th Street" required>
                </div>
                <button type="submit" name="next" class="QRCode-button">Next</button>
            </form>
        </div>

        <div class="divider"></div>
        <div class="product-details">
            <h2>Order Summary</h2>
            <p><strong>Product Name:</strong> <?php echo $productName; ?></p>
            <p><strong>Size:</strong> <?php echo $size; ?></p>
            <p><strong>Color:</strong> <?php echo $color; ?></p>
            <p><strong>Type:</strong> <?php echo $type; ?></p>
            <p><strong>Quantity:</strong> <?php echo $quantity; ?></p>
            <p><strong>Price per Item:</strong> ₹<?php echo number_format($price, 2); ?></p>
            <p><strong>Total Price:</strong> ₹<?php echo number_format($totalPrice, 2); ?></p>
        </div>
    </div>

    <?php if ($showQRCode): ?>
    <div class="qr-code-container" style="transform: translate(-300%,65%);" >
        <h2>Scan to Pay</h2>
        <img src="<?php echo $filePath; ?>" alt="UPI QR Code">
    </div>
    <?php endif; ?>

</body>
</html>