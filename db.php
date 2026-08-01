<?php
/*
  FarmFleet Database Connection and Seeding Layer (Coimbatore & Erode Districts)
  Creates SQLite database and seeds default items on first run.
*/

$dbPath = __DIR__ . '/farmfleet.db';
$dbExists = file_exists($dbPath);
$forceReset = isset($_GET['reset']) && $_GET['reset'] == '1';

try {
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Dynamic schema update if database already exists on disk
    if ($dbExists && !$forceReset) {
        try {
            $stmt = $pdo->query("PRAGMA table_info(bookings)");
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('payment_method', $cols)) {
                $pdo->exec("ALTER TABLE bookings ADD COLUMN payment_method TEXT DEFAULT 'escrow'");
            }
            if (!in_array('payment_details', $cols)) {
                $pdo->exec("ALTER TABLE bookings ADD COLUMN payment_details TEXT DEFAULT ''");
            }
        } catch (Exception $schemaEx) {
            // Silence schema exception if table bookings doesn't exist yet
        }
    }

    if (!$dbExists || $forceReset) {
        // Force drop tables to refresh with both districts seed data
        $pdo->exec("DROP TABLE IF EXISTS users");
    $pdo->exec("DROP TABLE IF EXISTS listings");
    $pdo->exec("DROP TABLE IF EXISTS bookings");
    $pdo->exec("DROP TABLE IF EXISTS messages");
    $pdo->exec("DROP TABLE IF EXISTS notifications");
    $pdo->exec("DROP TABLE IF EXISTS settings");

    // Create Tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        phone TEXT UNIQUE,
        name TEXT,
        name_ta TEXT,
        role TEXT, -- farmer, owner, admin
        city TEXT,
        state TEXT,
        avatar TEXT,
        address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS listings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        owner_id INTEGER,
        name TEXT,
        name_ta TEXT,
        type TEXT, -- tractor, harvester, sprayer, plough, transport, other
        price REAL, -- daily rate for rental
        price_unit TEXT, -- day, hour
        sale_price REAL, -- purchase price (null if only rent)
        rating REAL,
        reviews_count INTEGER,
        image TEXT,
        tags TEXT, -- Comma-separated tags
        tags_ta TEXT,
        location TEXT,
        distance REAL,
        suitable_crop TEXT,
        details TEXT,
        horsepower INTEGER,
        fuel_type TEXT,
        transmission TEXT,
        hours_of_usage INTEGER,
        maintenance_history TEXT,
        smart_features TEXT, -- Comma-separated
        verified INTEGER, -- 0 or 1
        status TEXT, -- approved, pending, rejected
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        listing_id INTEGER,
        user_id INTEGER,
        type TEXT, -- rent, buy
        start_date TEXT,
        end_date TEXT,
        amount REAL,
        status TEXT, -- pending, approved, rejected, completed
        payment_method TEXT DEFAULT 'escrow',
        payment_details TEXT DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sender_id INTEGER,
        receiver_id INTEGER,
        text TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        text TEXT,
        time_text TEXT,
        unread INTEGER, -- 0 or 1
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )");

    // Seed default settings
    $stmt = $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?)");
    $stmt->execute(['rent_commission', '10']); // 10%
    $stmt->execute(['buy_commission', '5']);   // 5%

    // Seed default users (Localized to Erode & Coimbatore)
    $stmt = $pdo->prepare("INSERT INTO users (id, phone, name, name_ta, role, city, state, avatar) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Ramesh Kumar (Farmer - Coimbatore)
    $stmt->execute([
        1, 
        '9876543210', 
        'Ramesh Kumar', 
        'ரமேஷ் குமார்', 
        'farmer', 
        'Coimbatore', 
        'Tamil Nadu', 
        'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=100&h=100&q=80'
    ]);

    // Senthil Raja (Owner - Erode)
    $stmt->execute([
        2, 
        '9876543211', 
        'Senthil Raja', 
        'செந்தில் ராஜா', 
        'owner', 
        'Bhavani, Erode', 
        'Tamil Nadu', 
        'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=100&h=100&q=80'
    ]);

    // Admin User
    $stmt->execute([
        3, 
        '9876543212', 
        'Power Admin', 
        'நிர்வாகி', 
        'admin', 
        'Coimbatore', 
        'Tamil Nadu', 
        'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=100&h=100&q=80'
    ]);

    // Seed listings across Coimbatore and Erode Districts
    $stmt = $pdo->prepare("INSERT INTO listings (
        id, owner_id, name, name_ta, type, price, price_unit, sale_price, rating, reviews_count, image, 
        tags, tags_ta, location, distance, suitable_crop, details, horsepower, fuel_type, transmission, 
        hours_of_usage, maintenance_history, smart_features, verified, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    // 1. John Deere 8R 410 (Coimbatore - Rent)
    $stmt->execute([
        1, 2, 
        'John Deere 8R 410', 'ஜான் டீரெ 8R 410', 
        'tractor', 850, 'day', null, 4.9, 36, 
        'images/tractor.png',
        'With Driver,Fuel Included,Verified Owner,Available Today', 'டிரைவருடன்,எரிபொருள் உட்பட,சரிபார்க்கப்பட்டது,இன்று கிடைக்கும்',
        'Gandhipuram, Coimbatore', 4.2, 'Sugarcane', 
        '410 HP heavy duty tractor for high-capacity farming and row crop operations. Features full auto-guidance system.',
        410, 'Diesel', 'AutoPowr CVT', 120, 'Serviced in Oct 2026. Engine and tracks in excellent condition.', 
        'GPS Autosteer,Telematics,Hydraulic Flow Control', 1, 'approved'
    ]);

    // 2. Claas Lexion 8900 (Coimbatore - Rent)
    $stmt->execute([
        2, 2, 
        'Claas Lexion 8900', 'கிளாஸ் லெக்சியன் 8900', 
        'harvester', 1400, 'day', null, 4.8, 32, 
        'images/harvester.png',
        'Self-Drive,Condition: Excellent,Verified Owner', 'சுய ஓட்டுதல்,சிறந்த நிலை,சரிபார்க்கப்பட்டது',
        'Peelamedu, Coimbatore', 12.0, 'Rice', 
        '790 HP high performance combine harvester for large fields. Equipped with precision mapping capabilities.',
        790, 'Diesel', 'Hydrostatic', 340, 'Cleaned and blades sharpened before harvest season.', 
        'Precision Map,ISOBUS Ready', 1, 'approved'
    ]);

    // 3. Fendt 1050 Vario (Coimbatore - Rent)
    $stmt->execute([
        3, 2, 
        'Fendt 1050 Vario', 'ஃபெண்ட் 1050 வேரியோ', 
        'tractor', 950, 'day', null, 5.0, 48, 
        'images/tractor.png',
        'FendtONE,With Driver,Verified Owner', 'ஃபெண்ட்டோன்,டிரைவருடன்,சரிபார்க்கப்பட்டது',
        'Pollachi, Coimbatore', 25.5, 'Sugarcane', 
        '517 HP high horsepower tractor. Uses variable transmission and FendtONE smart display board system.',
        517, 'Diesel', 'VarioDrive CVT', 85, 'Perfect working order, standard warranty active.', 
        'GPS Autosteer,Telematics,Hydraulic Flow Control,ISOBUS Ready', 1, 'approved'
    ]);

    // 4. Case IH Steiger 620 Quadtrac (Coimbatore - Rent)
    $stmt->execute([
        4, 2, 
        'Case IH Steiger 620 Quadtrac', 'கேஸ் IH ஸ்டீகர் 620 குவாட்டிராக்', 
        'tractor', 1250, 'day', null, 4.9, 14, 
        'images/tractor.png',
        'Featured Asset,High Capacity,Heavy Cultivation', 'சிறப்பு உபகரணம்,உயர் திறன்,கனரக உழவு',
        'Saravanampatti, Coimbatore', 18.0, 'Rice', 
        '620 HP heavy tillage tractor with unique quad-track steering for low soil compaction.',
        620, 'Diesel', 'PowerShift', 410, 'Full track inspection and drive-sprocket replacement completed.', 
        'GPS Autosteer,Telematics,Hydraulic Flow Control', 1, 'approved'
    ]);

    // 5. Massey Ferguson 7208S (Coimbatore - Rent)
    $stmt->execute([
        5, 2, 
        'Massey Ferguson 7208S', 'மேஸ்ஸி பெர்குசன் 7208S', 
        'harvester', 720, 'day', null, 4.7, 29, 
        'images/harvester.png',
        'Dyna-T,360° Vision,Verified Owner', 'டைனா-டி,360° பார்வை,சரிபார்க்கப்பட்டது',
        'Thudiyalur, Coimbatore', 5.4, 'Cotton', 
        '280 HP multipurpose machine with advanced active drive control and spacious layout.',
        280, 'Diesel', 'Dyna-VT', 180, 'Routine service done. Hydraulics running at full pressure.', 
        'Telematics,ISOBUS Ready', 1, 'approved'
    ]);

    // 6. 2024 John Deere 8R 370 (Coimbatore - Buy)
    $stmt->execute([
        6, 2, 
        '2024 John Deere 8R 370', '2024 ஜான் டீரெ 8R 370', 
        'tractor', null, null, 384500, 4.9, 124, 
        'images/tractor.png',
        'Pre-order available,New,Dealer Insured', 'முன்பதிவு கிடைக்கிறது,புதியது,காப்பீடு செய்யப்பட்டது',
        'Singanallur, Coimbatore', 2.0, 'Sugarcane', 
        '370 HP premium model tractor. Zero hours, full manufacturer warranty included. Custom configurations available.',
        370, 'Diesel', 'e23 PowerShift', 0, 'Brand new in crate.', 
        'GPS Autosteer,Telematics,Hydraulic Flow Control', 1, 'approved'
    ]);

    // 7. 2021 Case IH 9250 Axial-Flow (Erode - Buy)
    $stmt->execute([
        7, 2, 
        '2021 Case IH 9250', '2021 கேஸ் IH 9250', 
        'harvester', null, null, 249000, 4.7, 82, 
        'images/harvester.png',
        'Certified Refurbished,Used,Great Value', 'சான்றளிக்கப்பட்ட புதுப்பிக்கப்பட்டது,பயன்படுத்தப்பட்டது',
        'Perundurai, Erode', 15.0, 'Rice', 
        'High-capacity combine harvester. 1200 hours logged. Complete inspection and dealer certified refurbishing.',
        625, 'Diesel', 'Hydrostatic', 1200, 'Certified refurbishment completed in September 2025.', 
        'Precision Map,ISOBUS Ready', 1, 'approved'
    ]);

    // 8. JCB 3DX Backhoe Loader (Erode - Rent)
    $stmt->execute([
        8, 2, 
        'JCB 3DX Backhoe Loader', 'ஜேசிபி 3DX லோடர்', 
        'earthmover', 1100, 'day', null, 5.0, 42, 
        'images/jcb.png',
        'In Stock,Heavy Digging,Verified Owner', 'கையிருப்பில் உள்ளது,கனரக தோண்டுதல்,சரிபார்க்கப்பட்டது',
        'Bhavani, Erode', 14.2, 'General', 
        '76 HP backhoe loader. Perfect for layout shaping, excavation, and farm land preparation.',
        76, 'Diesel', 'Manual', 320, 'Engine and hydraulics fully verified.', 
        'Hydraulic Flow Control', 1, 'approved'
    ]);

    // 9. New Holland CR10.90 (Erode - Rent)
    $stmt->execute([
        9, 2, 
        'New Holland CR10.90', 'நியூ ஹாலந்து CR10.90', 
        'harvester', 1400, 'day', null, 4.9, 20, 
        'images/harvester.png',
        'Twin Rotor,Available Today', 'இரட்டை சுழலி,இன்று கிடைக்கும்',
        'Modakurichi, Erode', 11.0, 'Rice', 
        'Flagship twin-rotor combine harvester with maximum harvesting capacity and yield sensor modules.',
        700, 'Diesel', 'Hydrostatic', 150, 'Regular checks complete. Clean records.', 
        'Precision Map,GPS Autosteer,ISOBUS Ready', 1, 'approved'
    ]);

    // 10. Väderstad Tempo L 16 (Erode - Buy)
    $stmt->execute([
        10, 2, 
        'Väderstad Tempo L 16', 'வேடர்ஸ்டாட் டெம்போ L 16', 
        'plough', null, null, 125000, 4.8, 12, 
        'images/tractor.png',
        'High-Speed Planter,Precision Seeder', 'அதிவேக விதைப்பு,துல்லியமான விதைப்பான்',
        'Gobichettipalayam, Erode', 28.0, 'Vegetables', 
        '16-row high-speed precision planter. Enables seed placement at twice the speed of traditional planters.',
        250, 'N/A', 'Mechanical Drive', 0, 'Delivered brand new to buyer.', 
        'ISOBUS Ready', 1, 'approved'
    ]);

    // 11. Case IH Magnum 340 (Erode - Rent)
    $stmt->execute([
        11, 2, 
        'Case IH Magnum 340', 'கேஸ் IH மேக்னம் 340', 
        'tractor', 450, 'day', null, 4.6, 8, 
        'images/tractor.png',
        'Row Crop Special,Vetted Owner', 'ரோ கிராப் ஸ்பெஷல்,சரிபார்க்கப்பட்டவர்',
        'Erode South', 9.0, 'Sugarcane', 
        '340 HP row-crop tractor with active cabin suspension and telemetry connectivity.',
        340, 'Diesel', 'CVT', 220, 'Last maintenance check in July 2026.', 
        'GPS Autosteer,Telematics', 1, 'approved'
    ]);
    
    // 12. John Deere S780 Combine (Erode - Rent)
    $stmt->execute([
        12, 2, 
        'John Deere S780 Combine', 'ஜான் டீரெ S780 அறுவடை இயந்திரம்', 
        'harvester', 1300, 'day', null, 4.9, 15, 
        'images/harvester.png',
        'High Capacity,Active Yield', 'அதிவேக அறுவடை,துல்லியமான கண்காணிப்பு',
        'Bhavani, Erode', 14.5, 'Rice', 
        'Combine harvester with active yield monitoring and automatic grain adjustments.',
        473, 'Diesel', 'ProDrive', 180, 'Recently serviced, fully functioning.', 
        'Precision Map,GPS Autosteer,ISOBUS Ready', 1, 'approved'
    ]);

    // 13. Aspee HTP Tractor Sprayer (Coimbatore - Rent)
    $stmt->execute([
        13, 2, 
        'Aspee HTP Tractor Sprayer', 'அஸ்பி HTP டிராக்டர் தெளிப்பான்', 
        'sprayer', 320, 'day', null, 4.8, 14, 
        'images/sprayer.png',
        'High Pressure,Tractor Mounted,Verified Owner', 'உயர் அழுத்தம்,டிராக்டரில் பொருத்தப்பட்டது,சரிபார்க்கப்பட்டது',
        'Peelamedu, Coimbatore', 3.5, 'Sugarcane', 
        'Professional tractor-mounted high-pressure sprayer for pest control and crop watering operations.',
        25, 'N/A', 'PTO Driven', 80, 'Sprayer nozzles replaced and calibrated.', 
        'Hydraulic Flow Control', 1, 'approved'
    ]);

    // 14. Tata Yodha Pickup Truck (Erode - Rent)
    $stmt->execute([
        14, 2, 
        'Tata Yodha Pickup Truck', 'டாடா யோதா பிக்கப் டிரக்', 
        'transport', 650, 'day', null, 4.9, 19, 
        'images/transport.png',
        'With Driver,Cargo Load,Verified Owner', 'டிரைவருடன்,சரக்கு ஏற்றுதல்,சரிபார்க்கப்பட்டது',
        'Perundurai, Erode', 8.2, 'General', 
        'Tata Yodha pickup with robust cargo loading deck. High ground clearance, suitable for transporting farm produce.',
        85, 'Diesel', 'Manual', 450, 'Excellent suspension, loaded with commercial safety checks.', 
        'GPS Autosteer', 1, 'approved'
    ]);

    // 15. JCB 3DX Eco Backhoe Loader (Coimbatore - Rent)
    $stmt->execute([
        15, 2, 
        'JCB 3DX Eco Backhoe Loader', 'ஜேசிபி 3DX ஈகோ லோடர்', 
        'earthmover', 1200, 'day', null, 5.0, 24, 
        'images/jcb.png',
        'With Driver,Heavy Excavator,Verified Owner', 'டிரைவருடன்,கனரக தோண்டுதல்,சரிபார்க்கப்பட்டது',
        'Gandhipuram, Coimbatore', 4.8, 'General', 
        '76 HP fuel-efficient excavator loader for high-capacity digging, leveling, and canal clearing.',
        76, 'Diesel', 'Manual', 110, 'Full maintenance service done in Nov 2026.', 
        'Hydraulic Flow Control', 1, 'approved'
    ]);

    // 16. Hitachi EX200 Excavator (Coimbatore - Rent)
    $stmt->execute([
        16, 2, 
        'Hitachi EX200 Excavator', 'ஹிட்டாச்சி EX200 அகழ்வாராய்ச்சி இயந்திரம்', 
        'earthmover', 1800, 'day', null, 4.9, 12, 
        'images/hitachi.png',
        'Featured Asset,With Driver,Heavy Excavation', 'சிறப்பு உபகரணம்,டிரைவருடன்,கனரக அகழ்வாராய்ச்சி',
        'Pollachi, Coimbatore', 26.0, 'General', 
        '200 HP heavy tracked excavator for large land preparation, leveling, and pond excavation.',
        200, 'Diesel', 'Hydraulic', 220, 'Perfect tracks, serviced recently.', 
        'Hydraulic Flow Control', 1, 'approved'
    ]);

    // 17. DJI Agras T40 Spraying Drone (Coimbatore - Rent)
    $stmt->execute([
        17, 2, 
        'DJI Agras T40 Spraying Drone', 'டிஜேஐ அக்ராஸ் T40 தெளிப்பு ட்ரோன்', 
        'sprayer', 1500, 'day', null, 4.9, 18, 
        'images/drone.png',
        'Smart Farming,Precision Spraying,Verified Owner', 'ஸ்மார்ட் விவசாயம்,துல்லியமான தெளிப்பு,சரிபார்க்கப்பட்டது',
        'Peelamedu, Coimbatore', 5.0, 'Rice', 
        '40-liter payload spraying drone. Precision autonomous spraying with high crop penetration and radar safety features.',
        0, 'Electric / Battery', 'N/A', 15, 'Batteries and nozzles checked in Nov 2026. Excellent condition.', 
        'Precision Map,GPS Autosteer,Telematics', 1, 'approved'
    ]);

    // 18. Swaraj 744 FE (Coimbatore - Rent - Pending)
    $stmt->execute([
        18, 2, 
        'Swaraj 744 FE', 'சுவராஜ் 744 FE', 
        'tractor', 600, 'hour', null, 4.5, 0, 
        'images/tractor.png',
        'Economical,Self-Drive', 'சிக்கனமானது,சுய ஓட்டுதல்',
        'Pollachi, Coimbatore', 22.0, 'Sugarcane', 
        '48 HP multi-purpose tractor suitable for light cultivation and haulage.',
        48, 'Diesel', 'Manual', 250, 'Regular servicing, excellent condition.', 
        'Telematics', 0, 'pending'
    ]);

    // 19. Falcon Agri Drone (Erode - Rent - Pending)
    $stmt->execute([
        19, 2, 
        'Falcon Agri Drone', 'பால்கன் தெளிப்பு ட்ரோன்', 
        'sprayer', 1200, 'day', null, 4.6, 0, 
        'images/drone.png',
        'Smart Farming,Self-Drive', 'ஸ்மார்ட் விவசாயம்,சுய ஓட்டுதல்',
        'Bhavani, Erode', 10.5, 'Cotton', 
        'High precision spraying drone with terrain scanning features.',
        0, 'Electric / Battery', 'N/A', 40, 'Ready to be verified.', 
        'Precision Map,GPS Autosteer', 0, 'pending'
    ]);

    // 20. PRD Autodrill Borewell Rig (Coimbatore - Rent - Active)
    $stmt->execute([
        20, 2, 
        'PRD Autodrill Borewell Rig', 'பிஆர்டி ஆட்டோட்ரில் போர்வெல் ரிக்', 
        'plough', 1500, 'hour', null, 4.9, 12, 
        'images/borewell_rig.png',
        'High Capacity,Depth Max 1200ft,Verified Owner', 'உயர் திறன்,அதிகபட்ச ஆழம் 1200 அடி,சரிபார்க்கப்பட்டது',
        'Thondamuthur, Coimbatore', 4.5, 'General', 
        'PRD Autodrill rig mounted on robust commercial truck body. Drills down to 1200 feet deep in high hardness soil.',
        450, 'Diesel', 'Manual', 850, 'Rig hydraulic hoses replaced, certified safe.', 
        'Telematics', 1, 'approved'
    ]);

    // 21. L&T Borewell Drilling Machine (Erode - Sale - Active)
    $stmt->execute([
        21, 2, 
        'L&T Borewell Drilling Machine', 'எல் & டி போர்வெல் துளையிடும் இயந்திரம்', 
        'plough', null, null, 6800000, 4.8, 4, 
        'images/borewell_rig.png',
        'Heavy Duty,Truck Mounted,High Torque', 'கனரக வாகனம்,டிரக்கில் பொருத்தப்பட்டது,அதிவேகம்',
        'Bhavani, Erode', 12.0, 'General', 
        'Used L&T drilling truck-mounted rig, highly efficient compressor system, immediate delivery.',
        520, 'Diesel', 'Manual', 3200, 'Compressor pressure check, ready to operate.', 
        'GPS Autosteer', 1, 'approved'
    ]);

    // Seed default bookings
    $stmt = $pdo->prepare("INSERT INTO bookings (id, listing_id, user_id, type, start_date, end_date, amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    // John Deere 8R 410 Active Rental
    $stmt->execute([
        201, 1, 1, 'rent', 'Oct 12, 2026', 'Oct 28, 2026', 11050, 'approved'
    ]);

    // Case IH Magnum 340 Pending Request
    $stmt->execute([
        202, 11, 1, 'rent', 'May 14', 'May 17', 1350, 'pending'
    ]);

    // John Deere S780 Combine Pending Request
    $stmt->execute([
        203, 12, 1, 'rent', 'May 22', 'May 23', 2600, 'pending'
    ]);

    // Fendt 1050 Vario Completed Rental
    $stmt->execute([
        204, 3, 1, 'rent', 'Jul 12', 'Jul 13', 1900, 'completed'
    ]);

    // Väderstad Tempo L 16 Purchase
    $stmt->execute([
        205, 10, 1, 'buy', 'Apr 15', null, 125000, 'completed'
    ]);

    // DJI Agras T40 Spraying Drone Pending Request
    $stmt->execute([
        206, 17, 1, 'rent', 'Oct 22, 2026', 'Oct 24, 2026', 3000, 'pending'
    ]);

    // Aspee HTP Tractor Sprayer Pending Request
    $stmt->execute([
        207, 13, 1, 'rent', 'Oct 25, 2026', 'Oct 26, 2026', 320, 'pending'
    ]);

    // PRD Autodrill Borewell Rig Active Approved
    $stmt->execute([
        208, 20, 1, 'rent', 'Oct 28, 2026', 'Nov 02, 2026', 7500, 'approved'
    ]);

    // 2024 John Deere 8R 370 Completed Purchase
    $stmt->execute([
        209, 6, 1, 'buy', 'Sep 05, 2026', null, 384500, 'completed'
    ]);

    // Seed messages
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, text) VALUES (?, ?, ?)");
    // Ramesh to Senthil
    $stmt->execute([1, 2, "Is this tractor available tomorrow?"]);
    // Senthil to Ramesh
    $stmt->execute([2, 1, "Yes, it is available from 9 AM."]);

    // Seed notifications
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, text, time_text, unread) VALUES (?, ?, ?, ?)");
    $stmt->execute([1, "Your rental of John Deere 8R 410 is approved!", "10 mins ago", 1]);
    $stmt->execute([1, "Owner accepted your booking request for Claas Lexion 8900", "1 hr ago", 0]);
    $stmt->execute([2, "New pending request for Case IH Magnum 340 from Ramesh Kumar!", "2 hrs ago", 1]);

    }
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}
?>
