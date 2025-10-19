    <?php
    session_start();
    require_once '../config.php';
    require_once ROOT_PATH . '/_partials/Header.php';
    if (isset($_GET['code'])) {
        $token = $gClient->fetchAccessTokenWithAuthCode($_GET['code']);
        if (!isset($token['error'])) {
            $gClient->setAccessToken($token['access_token']);
            $_SESSION['access_token'] = $token['access_token'];

            $google_oauth = new \Google\Service\Oauth2($gClient);
            $google_account_info = $google_oauth->userinfo->get();

            $email = $google_account_info->email;
            $name = $google_account_info->name;
            $google_id = $google_account_info->id;
            $avatar = $google_account_info->picture;

            // Kiểm tra xem người dùng đã tồn tại trong DB chưa
            $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
            $stmt->execute([$google_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Người dùng đã tồn tại, cập nhật thông tin và đăng nhập
                $updateStmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, avatar = ? WHERE google_id = ?");
                $updateStmt->execute([$name, $email, $avatar, $google_id]);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
            } else {
                // Người dùng mới, thêm vào DB với vai trò mặc định là 'user'
                // Trong thực tế, tài khoản admin và teacher nên được tạo thủ công
                $insertStmt = $pdo->prepare("INSERT INTO users (google_id, email, name, avatar, role) VALUES (?, ?, ?, ?, 'user')");
                $insertStmt->execute([$google_id, $email, $name, $avatar]);
                $newUserId = $pdo->lastInsertId();
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['user_role'] = 'user';
            }

            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_avatar'] = $avatar;

            // Điều hướng về trang chủ
            header('Location: /index.php');
            exit();

        } else {
            // Lỗi xác thực
            header('Location: /index.php?error=1');
            exit();
        }
    } else {
        header('Location: /index.php');
        exit();
    }
    ?>
    
