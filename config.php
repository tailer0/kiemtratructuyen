    <?php
    // Bắt đầu session
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Cấu hình cơ sở dữ liệu
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', ''); // Mật khẩu root của Laragon thường là rỗng
    define('DB_NAME', 'kiemtratructuyen');

    // Tạo kết nối PDO
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("set names utf8");
    } catch (PDOException $e) {
        die("ERROR: Could not connect. " . $e->getMessage());
    }
    error_reporting(E_ALL & ~E_DEPRECATED);
    // Cấu hình Google API
    require_once __DIR__ . '/vendor/autoload.php';
    define('ROOT_PATH', __DIR__);
    define('GOOGLE_CLIENT_ID', '460765964721-ft1br492bmitjnjusv9iiu1trdvrbsg2.apps.googleusercontent.com'); // Thay bằng Client ID của bạn
    define('GOOGLE_CLIENT_SECRET', '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'); // Thay bằng Client Secret
    define('GOOGLE_REDIRECT_URL', 'http://localhost:81/auth/google-callback.php');

    // Tạo Google Client
    $gClient = new Google_Client();
    $gClient->setClientId(GOOGLE_CLIENT_ID);
    $gClient->setClientSecret(GOOGLE_CLIENT_SECRET);
    $gClient->setRedirectUri(GOOGLE_REDIRECT_URL);
    $gClient->addScope('email');
    $gClient->addScope('profile');
    ?>
    
