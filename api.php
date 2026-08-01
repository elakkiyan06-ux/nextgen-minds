<?php
/*
  FarmFleet REST API Controller
  Provides JSON endpoints for all frontend interactive states,
  synced directly with the SQLite database.
*/

session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

// Utility: response helper
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Utility: get post data
function getPostData() {
    $raw = file_get_contents('php://input');
    if ($raw) {
        return json_decode($raw, true) ?: $_POST;
    }
    return $_POST;
}

// Get logged-in user helpers
function getCurrentUser($pdo) {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

try {
    switch ($action) {
        
        case 'get_session':
            $user = getCurrentUser($pdo);
            if ($user) {
                sendResponse([
                    'loggedIn' => true,
                    'user' => $user
                ]);
            } else {
                sendResponse([
                    'loggedIn' => false,
                    'user' => null
                ]);
            }
            break;

        case 'login':
            $data = getPostData();
            $phone = isset($data['phone']) ? trim($data['phone']) : '';
            $role = isset($data['role']) ? trim($data['role']) : 'farmer';
            
            if (!$phone) {
                sendResponse(['error' => 'Phone number is required'], 400);
            }
            
            // Check for admin override (phone/email check before stripping non-digits)
            $phoneLower = strtolower($phone);
            if ($phoneLower === 'elakkiyan6627@gmail.com' || $phoneLower === 'admin' || $phone === '9876543212') {
                $role = 'admin';
                // Find or create admin user in DB
                $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
                $stmt->execute();
                $user = $stmt->fetch();
                if (!$user) {
                    $stmtInsert = $pdo->prepare("INSERT INTO users (phone, name, name_ta, role, city, state, avatar) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmtInsert->execute(['9876543212', 'Power Admin', 'நிர்வாகி', 'admin', 'Coimbatore', 'Tamil Nadu', 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=100&h=100&q=80']);
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
                    $stmt->execute();
                    $user = $stmt->fetch();
                }

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = 'admin';

                sendResponse([
                    'success' => true,
                    'user' => $user
                ]);
            }

            // Format phone number to standard format
            $phone = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($phone) > 10) {
                $phone = substr($phone, -10);
            }

            // Find or create user
            $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            $user = $stmt->fetch();

            if (!$user) {
                // Determine user details based on typical Tamil Nadu names for seeding/demo
                $name = "Farmer " . substr($phone, -4);
                $nameTa = "விவசாயி " . substr($phone, -4);
                $avatar = "https://images.unsplash.com/photo-1542909168-82c3e7fdca5c?auto=format&fit=crop&w=100&h=100&q=80";
                
                if ($role === 'owner') {
                    $name = "Owner " . substr($phone, -4);
                    $nameTa = "உரிமையாளர் " . substr($phone, -4);
                    $avatar = "https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=100&h=100&q=80";
                } else if ($role === 'admin') {
                    $name = "Admin " . substr($phone, -4);
                    $nameTa = "நிர்வாகி " . substr($phone, -4);
                }

                $stmtInsert = $pdo->prepare("INSERT INTO users (phone, name, name_ta, role, city, state, avatar) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtInsert->execute([$phone, $name, $nameTa, $role, 'Erode', 'Tamil Nadu', $avatar]);
                
                $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ?");
                $stmt->execute([$phone]);
                $user = $stmt->fetch();
            } else {
                // If user exists, update their role if they selected a different role in the dropdown for testing
                $stmtUpdateRole = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmtUpdateRole->execute([$role, $user['id']]);
                $user['role'] = $role;
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            sendResponse([
                'success' => true,
                'user' => $user
            ]);
            break;

        case 'register':
            $data = getPostData();
            $phone = isset($data['phone']) ? trim($data['phone']) : '';
            $role = isset($data['role']) ? trim($data['role']) : 'farmer';
            $name = isset($data['name']) ? trim($data['name']) : '';
            $area = isset($data['area']) ? trim($data['area']) : '';
            $address = isset($data['address']) ? trim($data['address']) : '';
            $avatar = isset($data['avatar']) ? trim($data['avatar']) : '';

            if (!$phone || !$name || !$area || !$address) {
                sendResponse(['error' => 'All registration fields are required'], 400);
            }

            if ($role === 'admin') {
                if (strtolower($phone) !== 'elakkiyan6627@gmail.com') {
                    sendResponse(['error' => 'Access Denied: Unauthorized Admin Email Address'], 403);
                }
            } else {
                // Format phone number
                $phone = preg_replace('/[^0-9]/', '', $phone);
                if (strlen($phone) > 10) {
                    $phone = substr($phone, -10);
                }
            }

            // Check if exists
            $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            $user = $stmt->fetch();

            if ($user) {
                sendResponse(['error' => 'Mobile number already registered'], 400);
            }

            // Insert user
            $stmtInsert = $pdo->prepare("INSERT INTO users (phone, name, name_ta, role, city, state, avatar, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtInsert->execute([$phone, $name, $name, $role, $area, 'Tamil Nadu', $avatar, $address]);

            $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            $user = $stmt->fetch();

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            sendResponse([
                'success' => true,
                'user' => $user
            ]);
            break;

        case 'logout':
            session_destroy();
            sendResponse(['success' => true]);
            break;

        case 'update_profile':
            $user = getCurrentUser($pdo);
            if (!$user) {
                sendResponse(['error' => 'Authentication required'], 401);
            }

            $data = getPostData();
            $name = isset($data['name']) ? trim($data['name']) : '';
            $area = isset($data['area']) ? trim($data['area']) : '';
            $address = isset($data['address']) ? trim($data['address']) : '';
            $avatar = isset($data['avatar']) ? trim($data['avatar']) : '';

            if (!$name || !$area || !$address) {
                sendResponse(['error' => 'All fields are required'], 400);
            }

            // Update user in DB
            $stmt = $pdo->prepare("UPDATE users SET name = ?, city = ?, address = ?, avatar = ? WHERE id = ?");
            $stmt->execute([$name, $area, $address, $avatar, $user['id']]);

            // Fetch updated user details
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $updatedUser = $stmt->fetch();

            sendResponse([
                'success' => true,
                'user' => $updatedUser
            ]);
            break;

        case 'get_listings':
            $type = isset($_GET['type']) ? trim($_GET['type']) : '';
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $mode = isset($_GET['mode']) ? trim($_GET['mode']) : 'rent'; // rent, buy
            $status = isset($_GET['status']) ? trim($_GET['status']) : 'approved'; // approved, pending, all

            $query = "SELECT l.*, u.name as owner_name, u.avatar as owner_avatar 
                      FROM listings l 
                      JOIN users u ON l.owner_id = u.id 
                      WHERE 1=1";
            $params = [];

            if ($status !== 'all') {
                $query .= " AND l.status = ?";
                $params[] = $status;
            }

            if ($mode === 'rent') {
                $query .= " AND l.price IS NOT NULL";
            } else if ($mode === 'buy') {
                $query .= " AND l.sale_price IS NOT NULL";
            }

            if ($type) {
                $query .= " AND l.type = ?";
                $params[] = $type;
            }

            if ($search) {
                $query .= " AND (l.name LIKE ? OR l.name_ta LIKE ? OR l.location LIKE ?)";
                $searchParam = "%$search%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }

            $query .= " ORDER BY l.id DESC";

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll();

            // Decode lists of tags
            foreach ($results as &$item) {
                $item['tags'] = $item['tags'] ? explode(',', $item['tags']) : [];
                $item['tagsTa'] = $item['tags_ta'] ? explode(',', $item['tags_ta']) : [];
                $item['smartFeatures'] = $item['smart_features'] ? explode(',', $item['smart_features']) : [];
                $item['verified'] = (bool)$item['verified'];
            }

            sendResponse($results);
            break;

        case 'add_listing':
            $user = getCurrentUser($pdo);
            if (!$user) {
                sendResponse(['error' => 'Authentication required'], 401);
            }

            $data = getPostData();
            
            $name = isset($data['name']) ? trim($data['name']) : '';
            $nameTa = isset($data['nameTa']) ? trim($data['nameTa']) : $name;
            $type = isset($data['type']) ? trim($data['type']) : 'tractor';
            $price = isset($data['price']) ? floatval($data['price']) : null;
            $priceUnit = isset($data['priceUnit']) ? trim($data['priceUnit']) : 'day';
            $salePrice = isset($data['salePrice']) ? floatval($data['salePrice']) : null;
            $location = isset($data['location']) ? trim($data['location']) : 'Erode, Tamil Nadu';
            $distance = isset($data['distance']) ? floatval($data['distance']) : 5.0;
            $suitableCrop = isset($data['suitableCrop']) ? trim($data['suitableCrop']) : 'Any';
            $details = isset($data['details']) ? trim($data['details']) : '';
            
            $horsepower = isset($data['horsepower']) ? intval($data['horsepower']) : null;
            $fuelType = isset($data['fuelType']) ? trim($data['fuelType']) : 'Diesel';
            $transmission = isset($data['transmission']) ? trim($data['transmission']) : 'Manual';
            $hours = isset($data['hours']) ? intval($data['hours']) : 0;
            $maintenance = isset($data['maintenance']) ? trim($data['maintenance']) : '';
            
            $tags = isset($data['tags']) ? (is_array($data['tags']) ? implode(',', $data['tags']) : $data['tags']) : 'Verified Owner';
            $tagsTa = isset($data['tagsTa']) ? (is_array($data['tagsTa']) ? implode(',', $data['tagsTa']) : $data['tagsTa']) : 'சரிபார்க்கப்பட்டது';
            $smartFeatures = isset($data['smartFeatures']) ? (is_array($data['smartFeatures']) ? implode(',', $data['smartFeatures']) : $data['smartFeatures']) : '';

            $image = isset($data['image']) ? trim($data['image']) : '';
            if (empty($image) || $image === 'https://images.unsplash.com/photo-1595246140625-573b715d11dc?auto=format&fit=crop&w=600&q=80') {
                if ($type === 'harvester') {
                    $image = 'images/harvester.png';
                } else if ($type === 'sprayer') {
                    $image = 'images/sprayer.png';
                } else if ($type === 'transport') {
                    $image = 'images/transport.png';
                } else if ($type === 'earthmover' || $type === 'jcb') {
                    $image = 'images/jcb.png';
                } else {
                    $image = 'images/tractor.png'; // default/tractor
                }
            }

            // Auto approve for admin or owner (so it shows immediately). Let's make it approved by default for better demo workflow
            $status = 'approved'; 
            
            $stmt = $pdo->prepare("INSERT INTO listings (
                owner_id, name, name_ta, type, price, price_unit, sale_price, rating, reviews_count, image, 
                tags, tags_ta, location, distance, suitable_crop, details, horsepower, fuel_type, transmission, 
                hours_of_usage, maintenance_history, smart_features, verified, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $user['id'], $name, $nameTa, $type, $price, $priceUnit, $salePrice, 5.0, 0, $image,
                $tags, $tagsTa, $location, $distance, $suitableCrop, $details, $horsepower, $fuelType, $transmission,
                $hours, $maintenance, $smartFeatures, 1, $status
            ]);

            $newId = $pdo->lastInsertId();

            // Notify admin of new listing
            $stmtAdmin = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
            $adminId = $stmtAdmin->fetchColumn();
            if ($adminId) {
                $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, text, time_text, unread) VALUES (?, ?, ?, ?)");
                $stmtNotif->execute([$adminId, "New equipment listing added: $name", "Just now", 1]);
            }

            sendResponse([
                'success' => true,
                'listingId' => $newId
            ]);
            break;

        case 'book_listing':
            $user = getCurrentUser($pdo);
            if (!$user) {
                sendResponse(['error' => 'Authentication required'], 401);
            }

            $data = getPostData();
            $listingId = isset($data['listingId']) ? intval($data['listingId']) : 0;
            $type = isset($data['type']) ? trim($data['type']) : 'rent'; // rent, buy
            $startDate = isset($data['startDate']) ? trim($data['startDate']) : 'Today';
            $endDate = isset($data['endDate']) ? trim($data['endDate']) : null;
            $amount = isset($data['amount']) ? floatval($data['amount']) : 0.0;
            
            // Get listing details to find owner
            $stmtListing = $pdo->prepare("SELECT * FROM listings WHERE id = ?");
            $stmtListing->execute([$listingId]);
            $listing = $stmtListing->fetch();

            if (!$listing) {
                sendResponse(['error' => 'Listing not found'], 404);
            }

            // Create booking record
            $status = ($type === 'buy') ? 'completed' : 'pending';
            $paymentMethod = isset($data['paymentMethod']) ? trim($data['paymentMethod']) : 'escrow';
            $paymentDetails = isset($data['paymentDetails']) ? trim($data['paymentDetails']) : '';
            
            $stmt = $pdo->prepare("INSERT INTO bookings (listing_id, user_id, type, start_date, end_date, amount, status, payment_method, payment_details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$listingId, $user['id'], $type, $startDate, $endDate, $amount, $status, $paymentMethod, $paymentDetails]);
            $bookingId = $pdo->lastInsertId();

            // Create notification for Farmer (Booker)
            $stmtNotifFarmer = $pdo->prepare("INSERT INTO notifications (user_id, text, time_text, unread) VALUES (?, ?, ?, ?)");
            if ($type === 'rent') {
                $stmtNotifFarmer->execute([$user['id'], "Your booking request for {$listing['name']} has been submitted!", "Just now", 1]);
            } else {
                $stmtNotifFarmer->execute([$user['id'], "Congratulations! You have purchased {$listing['name']} for ₹" . number_format($amount) . "!", "Just now", 1]);
            }

            // Create notification for Owner
            $stmtNotifOwner = $pdo->prepare("INSERT INTO notifications (user_id, text, time_text, unread) VALUES (?, ?, ?, ?)");
            if ($type === 'rent') {
                $stmtNotifOwner->execute([$listing['owner_id'], "New rental request from {$user['name']} for {$listing['name']}", "Just now", 1]);
            } else {
                $stmtNotifOwner->execute([$listing['owner_id'], "Your equipment {$listing['name']} was purchased by {$user['name']}!", "Just now", 1]);
            }

            sendResponse([
                'success' => true,
                'bookingId' => $bookingId,
                'status' => $status
            ]);
            break;

        case 'get_dashboard_data':
            $user = getCurrentUser($pdo);
            if (!$user) {
                sendResponse(['error' => 'Authentication required'], 401);
            }

            // Fetch rentals (historical + current)
            $stmtRentals = $pdo->prepare("
                SELECT b.*, l.name as listing_name, l.name_ta as listing_name_ta, l.image, l.type as listing_type, 
                       u.name as owner_name, u.phone as owner_phone
                FROM bookings b
                JOIN listings l ON b.listing_id = l.id
                JOIN users u ON l.owner_id = u.id
                WHERE b.user_id = ? AND b.type = 'rent'
                ORDER BY b.id DESC
            ");
            $stmtRentals->execute([$user['id']]);
            $rentals = $stmtRentals->fetchAll();

            // Fetch purchases
            $stmtPurchases = $pdo->prepare("
                SELECT b.*, l.name as listing_name, l.name_ta as listing_name_ta, l.image, 
                       u.name as seller_name, u.phone as seller_phone
                FROM bookings b
                JOIN listings l ON b.listing_id = l.id
                JOIN users u ON l.owner_id = u.id
                WHERE b.user_id = ? AND b.type = 'buy'
                ORDER BY b.id DESC
            ");
            $stmtPurchases->execute([$user['id']]);
            $purchases = $stmtPurchases->fetchAll();

            // Total spent calculator
            $totalSpent = 0;
            foreach ($rentals as $r) {
                if ($r['status'] === 'approved' || $r['status'] === 'completed') {
                    $totalSpent += $r['amount'];
                }
            }
            foreach ($purchases as $p) {
                $totalSpent += $p['amount'];
            }

            // Settings (like commission info)
            $stmtSettings = $pdo->query("SELECT * FROM settings");
            $settingsList = $stmtSettings->fetchAll();
            $settings = [];
            foreach ($settingsList as $s) {
                $settings[$s['key']] = $s['value'];
            }

            sendResponse([
                'rentals' => $rentals,
                'purchases' => $purchases,
                'totalSpent' => $totalSpent,
                'settings' => $settings
            ]);
            break;

        case 'owner_data':
            $user = getCurrentUser($pdo);
            if (!$user || $user['role'] !== 'owner') {
                sendResponse(['error' => 'Owner portal access restricted'], 403);
            }

            // Get Owner Listings
            $stmtListings = $pdo->prepare("SELECT * FROM listings WHERE owner_id = ?");
            $stmtListings->execute([$user['id']]);
            $listings = $stmtListings->fetchAll();

            // Get Pending & Active Booking Requests
            $stmtRequests = $pdo->prepare("
                SELECT b.*, l.name as equipment_name, l.name_ta as equipment_name_ta, l.image,
                       u.name as farmer_name, u.avatar as farmer_avatar, u.city as farmer_city
                FROM bookings b
                JOIN listings l ON b.listing_id = l.id
                JOIN users u ON b.user_id = u.id
                WHERE l.owner_id = ?
                ORDER BY b.status = 'pending' DESC, b.id DESC
            ");
            $stmtRequests->execute([$user['id']]);
            $requests = $stmtRequests->fetchAll();

            // Calculate Earnings (Completed/approved amounts minus platform commission)
            // Default rental platform commission is stored in setting 'rent_commission'
            $commRate = intval($pdo->query("SELECT value FROM settings WHERE key = 'rent_commission'")->fetchColumn() ?: 10);
            $totalEarnings = 0;
            $commissionPaid = 0;

            foreach ($requests as $r) {
                if ($r['status'] === 'approved' || $r['status'] === 'completed') {
                    $gross = $r['amount'];
                    $fee = $gross * ($commRate / 100);
                    $totalEarnings += ($gross - $fee);
                    $commissionPaid += $fee;
                }
            }

            sendResponse([
                'listings' => $listings,
                'requests' => $requests,
                'totalEarnings' => $totalEarnings,
                'commissionPaid' => $commissionPaid,
                'commissionRate' => $commRate
            ]);
            break;

        case 'respond_request':
            $user = getCurrentUser($pdo);
            if (!$user || $user['role'] !== 'owner') {
                sendResponse(['error' => 'Authentication required'], 401);
            }

            $data = getPostData();
            $requestId = isset($data['requestId']) ? intval($data['requestId']) : 0;
            $status = isset($data['status']) ? trim($data['status']) : 'approved'; // approved, rejected

            // Verify booking exists and user owns the listing
            $stmtVerify = $pdo->prepare("
                SELECT b.*, l.name, l.owner_id 
                FROM bookings b 
                JOIN listings l ON b.listing_id = l.id 
                WHERE b.id = ?
            ");
            $stmtVerify->execute([$requestId]);
            $booking = $stmtVerify->fetch();

            if (!$booking || $booking['owner_id'] != $user['id']) {
                sendResponse(['error' => 'Unauthorized or request not found'], 403);
            }

            // Update status
            $stmtUpdate = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $stmtUpdate->execute([$status, $requestId]);

            // Notify renter
            $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, text, time_text, unread) VALUES (?, ?, ?, ?)");
            $notifText = ($status === 'approved') 
                ? "Your booking request for {$booking['name']} has been approved by the owner!" 
                : "Your booking request for {$booking['name']} was declined.";
            $stmtNotif->execute([$booking['user_id'], $notifText, "Just now", 1]);

            sendResponse(['success' => true]);
            break;

        case 'get_messages':
            $user = getCurrentUser($pdo);
            if (!$user) {
                sendResponse(['error' => 'Authentication required'], 401);
            }

            // Fetch all messages involving user
            $stmt = $pdo->prepare("
                SELECT m.*, 
                       s.name as sender_name, s.avatar as sender_avatar,
                       r.name as receiver_name, r.avatar as receiver_avatar
                FROM messages m
                JOIN users s ON m.sender_id = s.id
                JOIN users r ON m.receiver_id = r.id
                WHERE m.sender_id = ? OR m.receiver_id = ?
                ORDER BY m.id ASC
            ");
            $stmt->execute([$user['id'], $user['id']]);
            $messages = $stmt->fetchAll();

            // Group messages into chats
            $chats = [];
            foreach ($messages as $msg) {
                $otherId = ($msg['sender_id'] == $user['id']) ? $msg['receiver_id'] : $msg['sender_id'];
                $otherName = ($msg['sender_id'] == $user['id']) ? $msg['receiver_name'] : $msg['sender_name'];
                $otherAvatar = ($msg['sender_id'] == $user['id']) ? $msg['receiver_avatar'] : $msg['sender_avatar'];
                
                if (!isset($chats[$otherId])) {
                    $chats[$otherId] = [
                        'threadId' => $otherId,
                        'name' => $otherName,
                        'avatar' => $otherAvatar,
                        'preview' => $msg['text'],
                        'messages' => []
                    ];
                }
                
                $chats[$otherId]['preview'] = $msg['text'];
                $chats[$otherId]['messages'][] = [
                    'sender' => ($msg['sender_id'] == $user['id']) ? 'farmer' : 'owner', // Relative to client view role
                    'text' => $msg['text'],
                    'time' => date('H:i A', strtotime($msg['created_at']))
                ];
            }

            sendResponse(array_values($chats));
            break;

        case 'send_message':
            $user = getCurrentUser($pdo);
            if (!$user) {
                sendResponse(['error' => 'Authentication required'], 401);
            }

            $data = getPostData();
            $receiverId = isset($data['receiverId']) ? intval($data['receiverId']) : 0;
            $text = isset($data['text']) ? trim($data['text']) : '';

            if (!$receiverId || !$text) {
                sendResponse(['error' => 'Missing message recipient or content'], 400);
            }

            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, text) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $receiverId, $text]);

            sendResponse(['success' => true]);
            break;

        case 'get_notifications':
            $user = getCurrentUser($pdo);
            if (!$user) {
                sendResponse(['user' => null, 'notifications' => []]);
            }

            $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC");
            $stmt->execute([$user['id']]);
            $notifs = $stmt->fetchAll();

            sendResponse($notifs);
            break;

        case 'clear_notifications':
            $user = getCurrentUser($pdo);
            if ($user) {
                $stmt = $pdo->prepare("UPDATE notifications SET unread = 0 WHERE user_id = ?");
                $stmt->execute([$user['id']]);
            }
            sendResponse(['success' => true]);
            break;

        case 'admin_metrics':
            $user = getCurrentUser($pdo);
            if (!$user || $user['role'] !== 'admin') {
                sendResponse(['error' => 'Admin portal access restricted'], 403);
            }

            // Total users count
            $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            
            // Total active listings
            $totalListings = $pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'approved'")->fetchColumn();

            // Total commission rates
            $rentComm = intval($pdo->query("SELECT value FROM settings WHERE key = 'rent_commission'")->fetchColumn() ?: 10);
            $buyComm = intval($pdo->query("SELECT value FROM settings WHERE key = 'buy_commission'")->fetchColumn() ?: 5);

            // Get total platform revenue simulation (Base base $1,284,500 + dynamic commissions from database)
            $stmtCompleted = $pdo->query("
                SELECT b.amount, b.type 
                FROM bookings b 
                WHERE b.status = 'completed' OR b.status = 'approved'
            ");
            $dynamicRevenue = 0;
            while ($row = $stmtCompleted->fetch()) {
                $rate = ($row['type'] === 'buy') ? $buyComm : $rentComm;
                $dynamicRevenue += $row['amount'] * ($rate / 100);
            }

            $totalRevenue = 1284500 + $dynamicRevenue; // Base static + dynamic addition

            // Verification Queue (Pending listings)
            $stmtPending = $pdo->query("
                SELECT l.*, u.name as owner_name 
                FROM listings l
                JOIN users u ON l.owner_id = u.id
                WHERE l.status = 'pending'
                ORDER BY l.id ASC
            ");
            $pendingQueue = $stmtPending->fetchAll();

            // Transaction log history
            $stmtTransactions = $pdo->query("
                SELECT b.*, l.name as equipment_name, u.name as user_name
                FROM bookings b
                JOIN listings l ON b.listing_id = l.id
                JOIN users u ON b.user_id = u.id
                ORDER BY b.id DESC
                LIMIT 15
            ");
            $transactions = $stmtTransactions->fetchAll();

            sendResponse([
                'totalRevenue' => $totalRevenue,
                'activeUsers' => 42890 + $totalUsers - 3, // Base static + new users offset
                'totalListings' => 12402 + $totalListings - 12, // Base static + offset
                'rentCommission' => $rentComm,
                'buyCommission' => $buyComm,
                'pendingQueue' => $pendingQueue,
                'transactions' => $transactions
            ]);
            break;

        case 'admin_verify_listing':
            $user = getCurrentUser($pdo);
            if (!$user || $user['role'] !== 'admin') {
                sendResponse(['error' => 'Admin authentication required'], 403);
            }

            $data = getPostData();
            $listingId = isset($data['listingId']) ? intval($data['listingId']) : 0;
            $status = isset($data['status']) ? trim($data['status']) : 'approved'; // approved, rejected

            $stmt = $pdo->prepare("UPDATE listings SET status = ?, verified = ? WHERE id = ?");
            $stmt->execute([$status, ($status === 'approved' ? 1 : 0), $listingId]);

            // Notify Owner
            $stmtListing = $pdo->prepare("SELECT name, owner_id FROM listings WHERE id = ?");
            $stmtListing->execute([$listingId]);
            $listing = $stmtListing->fetch();

            if ($listing) {
                $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, text, time_text, unread) VALUES (?, ?, ?, ?)");
                $notifText = ($status === 'approved') 
                    ? "Your listing {$listing['name']} has been approved and is now live!"
                    : "Your listing {$listing['name']} was rejected by administration.";
                $stmtNotif->execute([$listing['owner_id'], $notifText, "Just now", 1]);
            }

            sendResponse(['success' => true]);
            break;

        case 'update_commissions':
            $user = getCurrentUser($pdo);
            if (!$user || $user['role'] !== 'admin') {
                sendResponse(['error' => 'Admin authentication required'], 403);
            }

            $data = getPostData();
            $rentComm = isset($data['rentCommission']) ? intval($data['rentCommission']) : 10;
            $buyComm = isset($data['buyCommission']) ? intval($data['buyCommission']) : 5;

            $stmtRent = $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'rent_commission'");
            $stmtRent->execute([$rentComm]);

            $stmtBuy = $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'buy_commission'");
            $stmtBuy->execute([$buyComm]);

            sendResponse(['success' => true]);
            break;

        default:
            sendResponse(['error' => 'Endpoint action not defined'], 404);
            break;
    }
} catch (Exception $e) {
    sendResponse(['error' => 'Server Error: ' . $e->getMessage()], 500);
}
?>
