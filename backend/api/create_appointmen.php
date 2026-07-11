<?php
// 创建预约的API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // 允许跨域访问
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/JWT.php';

// 发送邮件函数（使用SMTP with SSL）
function sendAppointmentEmail($appointmentData, $doctorData, $userData) {
    // 管理员邮箱数组，支持多个邮箱
    $adminEmails = ['14821043@qq.com', '44402589@qq.com'];
    
    // 获取网站名称
    $siteName = '医院挂号'; // 默认值
    try {
        // 连接数据库
        $db = new PDO('mysql:host=localhost;port=3306;dbname=guahao;charset=utf8mb4', 'guahao', 'guahao');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 查询网站名称
        $stmt = $db->prepare('SELECT value FROM settings WHERE key_name = ?');
        $stmt->execute(['site_name']);
        $result = $stmt->fetch();
        if ($result) {
            $siteName = $result['value'];
        }
    } catch (Exception $e) {
        // 数据库连接失败，使用默认值
        file_put_contents(__DIR__ . '/error_log.txt', date('Y-m-d H:i:s') . ' - 数据库连接失败: ' . $e->getMessage() . "\n", FILE_APPEND);
    }
    
    // 邮件主题
    $subject = $siteName . '-新预约挂号通知 - ' . $appointmentData['order_id'];
    
    // 解析预约时间，提取年月日和上午/下午时间段
    $appointmentTime = $appointmentData['appointment_time'];
    $appointmentDate = date('Y-m-d', strtotime($appointmentTime));
    $hour = date('H', strtotime($appointmentTime));
    $timeSlot = $hour < 12 ? '上午' : '下午';
    $formattedAppointmentTime = $appointmentDate . ' ' . $timeSlot;
    
    // 构建邮件内容
    $message = "<html><body>";
    $message .= "<h2>{$siteName}-新预约挂号通知</h2>";
    $message .= "<p>您好，有新的预约挂号信息，请及时处理。</p>";
    $message .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
    $message .= "<tr><th>项目</th><th>信息</th></tr>";
    $message .= "<tr><td>订单编号</td><td>{$appointmentData['order_id']}</td></tr>";
    $message .= "<tr><td>用户信息</td><td>{$userData['nickname']} ({$userData['phone']})</td></tr>";
    $message .= "<tr><td>患者姓名</td><td>{$appointmentData['patient_name']}</td></tr>";
    $message .= "<tr><td>患者手机号</td><td>{$appointmentData['patient_phone']}</td></tr>";
    $message .= "<tr><td>患者性别</td><td>{$appointmentData['patient_gender']}</td></tr>";
    $message .= "<tr><td>患者年龄</td><td>{$appointmentData['patient_age']}</td></tr>";
    $message .= "<tr><td>预约病种</td><td>{$appointmentData['disease_name']}</td></tr>";
    $message .= "<tr><td>预约医生</td><td>{$doctorData['name']}</td></tr>";
    $message .= "<tr><td>预约时间</td><td>{$formattedAppointmentTime}</td></tr>";
    $message .= "<tr><td>病情症状描述</td><td>{$appointmentData['symptoms']}</td></tr>";
    $message .= "<tr><td>预约状态</td><td>待确认到诊</td></tr>";
    $message .= "<tr><td>创建时间</td><td>" . date('Y-m-d H:i:s') . "</td></tr>";
    $message .= "</table>";
    $message .= "<p>请登录后台管理系统查看详细信息。</p>";
    $message .= "</body></html>";
    
    // SMTP配置（使用用户提供的参数）
    $smtpServer = 'smtp.163.com';
    $smtpPort = 465; // SSL端口
    $smtpUsername = '14821043@163.com';
    $smtpPassword = 'LNEUCCGJTADFSUXM'; // 用户提供的授权码
    $fromEmail = '14821043@163.com';
    $fromName = $siteName;
    
    // 尝试使用SMTP发送邮件
    try {
        // 建立SSL连接
        $socket = fsockopen('ssl://' . $smtpServer, $smtpPort, $errno, $errstr, 30);
        if (!$socket) {
            throw new Exception("无法连接到SMTP服务器: $errstr ($errno)");
        }
        
        // 设置超时
        stream_set_timeout($socket, 30);
        
        // 读取服务器响应
        function getResponse($socket) {
            $response = '';
            $startTime = time();
            while (time() - $startTime < 30) {
                $line = fgets($socket, 512);
                if ($line === false) {
                    break;
                }
                $response .= $line;
                if (substr($line, 3, 1) == ' ') {
                    break;
                }
            }
            return $response;
        }
        
        // 读取初始响应
        $initialResponse = getResponse($socket);
        if (empty($initialResponse) || substr($initialResponse, 0, 3) != '220') {
            throw new Exception("SMTP服务器初始响应失败: $initialResponse");
        }
        
        // 发送EHLO命令
        fwrite($socket, "EHLO localhost\r\n");
        $ehloResponse = getResponse($socket);
        if (empty($ehloResponse) || substr($ehloResponse, 0, 3) != '250') {
            throw new Exception("EHLO命令失败: $ehloResponse");
        }
        
        // 发送AUTH LOGIN命令
        fwrite($socket, "AUTH LOGIN\r\n");
        $authResponse = getResponse($socket);
        if (empty($authResponse) || substr($authResponse, 0, 3) != '334') {
            throw new Exception("AUTH LOGIN命令失败: $authResponse");
        }
        
        // 发送用户名
        fwrite($socket, base64_encode($smtpUsername) . "\r\n");
        $userResponse = getResponse($socket);
        if (empty($userResponse) || substr($userResponse, 0, 3) != '334') {
            throw new Exception("用户名发送失败: $userResponse");
        }
        
        // 发送密码
        fwrite($socket, base64_encode($smtpPassword) . "\r\n");
        $passResponse = getResponse($socket);
        if (empty($passResponse) || substr($passResponse, 0, 3) != '235') {
            throw new Exception("SMTP认证失败: $passResponse");
        }
        
        // 发送MAIL FROM命令
        fwrite($socket, "MAIL FROM: <$fromEmail>\r\n");
        $mailFromResponse = getResponse($socket);
        if (empty($mailFromResponse) || substr($mailFromResponse, 0, 3) != '250') {
            throw new Exception("MAIL FROM命令失败: $mailFromResponse");
        }
        
        // 为每个管理员邮箱发送RCPT TO命令
        foreach ($adminEmails as $adminEmail) {
            fwrite($socket, "RCPT TO: <$adminEmail>\r\n");
            $rcptToResponse = getResponse($socket);
            if (empty($rcptToResponse) || substr($rcptToResponse, 0, 3) != '250') {
                throw new Exception("RCPT TO命令失败: $rcptToResponse");
            }
        }
        
        // 发送DATA命令
        fwrite($socket, "DATA\r\n");
        $dataResponse = getResponse($socket);
        if (empty($dataResponse) || substr($dataResponse, 0, 3) != '354') {
            throw new Exception("DATA命令失败: $dataResponse");
        }
        
        // 构建To头
        $toHeader = implode(', ', array_map(function($email) { return "<$email>"; }, $adminEmails));
        
        // 发送邮件内容
        $emailContent = "From: $fromName <$fromEmail>\r\n";
        $emailContent .= "To: $toHeader\r\n";
        $emailContent .= "Subject: $subject\r\n";
        $emailContent .= "MIME-Version: 1.0\r\n";
        $emailContent .= "Content-type: text/html; charset=utf-8\r\n";
        $emailContent .= "Reply-To: $fromEmail\r\n\r\n";
        $emailContent .= $message . "\r\n.\r\n";
        
        // 发送邮件内容
        fwrite($socket, $emailContent);
        // 确保内容完全发送
        fflush($socket);
        
        // 读取响应
        $contentResponse = getResponse($socket);
        if (empty($contentResponse) || substr($contentResponse, 0, 3) != '250') {
            throw new Exception("邮件内容发送失败: $contentResponse");
        }
        
        // 发送QUIT命令
        fwrite($socket, "QUIT\r\n");
        $quitResponse = getResponse($socket);
        if (empty($quitResponse) || substr($quitResponse, 0, 3) != '221') {
            throw new Exception("QUIT命令失败: $quitResponse");
        }
        
        // 关闭连接
        fclose($socket);
        
        // 邮件发送成功
        $emailsStr = implode(', ', $adminEmails);
        file_put_contents(__DIR__ . '/error_log.txt', date('Y-m-d H:i:s') . ' - 邮件发送成功: ' . $emailsStr . ' - 订单编号: ' . $appointmentData['order_id'] . "\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/error_log.txt', date('Y-m-d H:i:s') . ' - 邮件发送异常: ' . $e->getMessage() . "\n", FILE_APPEND);
    }
    
    // 无论邮件发送是否成功，都返回true，确保预约流程正常完成
    return true;
}

try {
    // 获取数据库连接
    $db = Database::getInstance()->getConn();
    
    // 获取请求参数
    $data = json_decode(file_get_contents('php://input'), true);
    
    // 获取token
    $token = '';
    // 尝试从请求头中获取token
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = $_SERVER['HTTP_AUTHORIZATION'];
        // 移除Bearer前缀
        $token = str_replace('Bearer ', '', $token);
    }
    // 尝试从请求参数中获取token
    if (empty($token)) {
        $token = $_GET['token'] ?? $data['token'] ?? '';
    }
    
    if (empty($token)) {
        throw new Exception('token不能为空');
    }
    
    // 验证token
    $payload = JWT::decode($token, 'your_secret_key', ['HS256']);
    $user_id = $payload->user_id;
    
    // 验证参数
    if (!isset($data['doctor_id']) || !isset($data['appointment_time']) || !isset($data['patient_name']) || !isset($data['patient_phone'])) {
        throw new Exception('缺少必要参数');
    }
    
    // 设置默认值
    $schedule_id = $data['schedule_id'] ?? 1;
    $hospital_id = $data['hospital_id'] ?? 1;
    $department_id = $data['department_id'] ?? 1;
    
    // 开始事务
        $db->beginTransaction();
        
        try {
            // 检查用户是否存在
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('用户不存在');
            }
            
            // 检查是否已经有相同的预约（防止重复提交）
            $stmt = $db->prepare("SELECT id FROM appointments WHERE user_id = ? AND doctor_id = ? AND appointment_time = ? AND status != 'cancelled' LIMIT 1");
            $stmt->execute([$user_id, $data['doctor_id'], $data['appointment_time']]);
            $existingAppointment = $stmt->fetch();
            
            if ($existingAppointment) {
                throw new Exception('您已经预约过这个时间了，请查看我的预约');
            }
        
        // 获取用户IP地址
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // 获取IP所在地区（模拟数据，实际项目中可以使用IP地址查询服务）
        $ipLocation = '未知地区';
        // 这里可以添加IP地址查询逻辑

        
        // 获取医生信息以获取hospital_id和department_id
        $stmt = $db->prepare("SELECT * FROM doctors WHERE id = ?");
        $stmt->execute([$data['doctor_id']]);
        $doctor = $stmt->fetch();
        
        if (!$doctor) {
            throw new Exception('医生不存在');
        }
        
        // 生成唯一的订单编码：年月日 + 6位随机数
        $orderId = date('Ymd') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        
        // 确保订单编码唯一
        $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $count = $stmt->fetchColumn();
        
        while ($count > 0) {
            $orderId = date('Ymd') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
            $stmt->execute([$orderId]);
            $count = $stmt->fetchColumn();
        }
        
        // 解析预约时间和时间段
        $appointmentTime = $data['appointment_time'];
        $appointmentDate = date('Y-m-d', strtotime($appointmentTime));
        $timeSlot = $data['time_slot'] ?? (date('H', strtotime($appointmentTime)) < 12 ? '上午' : '下午');
        
        // 查找对应的排班记录
        $stmt = $db->prepare("SELECT * FROM schedules WHERE doctor_id = ? AND date = ? AND time_slot = ? LIMIT 1");
        $stmt->execute([$data['doctor_id'], $appointmentDate, $timeSlot]);
        $schedule = $stmt->fetch();
        
        if ($schedule) {
            // 检查剩余号源
            if ($schedule['remaining_quantity'] <= 0) {
                throw new Exception('该时段号源已用尽');
            }
            
            // 使用找到的排班ID
            $schedule_id = $schedule['id'];
        }
        
        // 插入预约记录 - 使用正确的字段名
        $sql = "INSERT INTO appointments (user_id, user_name, user_phone, ip_address, ip_location, doctor_id, schedule_id, hospital_id, department_id, disease_id, disease_name, appointment_time, status, order_id, patient_name, patient_phone, patient_gender, patient_age, symptoms) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            $user_id,
            $user['nickname'] ?? '',
            $user['phone'] ?? '',
            $ipAddress,
            $ipLocation,
            $data['doctor_id'],
            $schedule_id,
            $hospital_id,
            $department_id,
            isset($data['disease_id']) ? $data['disease_id'] : 0,
            isset($data['disease_name']) ? $data['disease_name'] : '',
            $data['appointment_time'],
            'pending', // 默认状态
            $orderId,
            $data['patient_name'],
            $data['patient_phone'],
            isset($data['patient_gender']) ? $data['patient_gender'] : '未知',
            isset($data['patient_age']) ? $data['patient_age'] : 0,
            isset($data['symptoms']) ? $data['symptoms'] : ''
        ]);
        
        if (!$result) {
            throw new Exception('预约记录插入失败');
        }
        
        // 获取插入的ID
        $appointment_id = $db->lastInsertId();
        
        // 如果没有获取到ID，查询刚插入的记录
        if (!$appointment_id) {
            $stmt = $db->prepare("SELECT id FROM appointments WHERE order_id = ? LIMIT 1");
            $stmt->execute([$orderId]);
            $appointment = $stmt->fetch();
            if ($appointment) {
                $appointment_id = $appointment['id'];
            }
        }
        
        // 更新排班的剩余号源
        if ($schedule) {
            $stmt = $db->prepare("UPDATE schedules SET remaining_quantity = remaining_quantity - 1 WHERE id = ? AND remaining_quantity > 0");
            $stmt->execute([$schedule['id']]);
        }
        
        // 提交事务
        $db->commit();
        
        // 如果仍然没有ID，使用0作为替代
        if (!$appointment_id) {
            $appointment_id = 0;
        }
        
        // 发送通知给用户
        $title = '预约成功';
        // 解析预约时间，提取时间段和日期
        $appointmentTime = $data['appointment_time'];
        $hour = date('H', strtotime($appointmentTime));
        $timeSlot = $hour < 12 ? '上午' : '下午';
        $appointmentDate = date('Y-m-d', strtotime($appointmentTime));
        $content = '您的预约已成功提交，时间段：' . $timeSlot . '，预约时间：' . $appointmentDate . '，医生会尽快联系您。';
        
        $sql = "INSERT INTO notifications (user_id, title, content, type) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$user_id, $title, $content, 'appointment']);
        
        // 发送邮件给管理员
        $appointmentData = [
            'order_id' => $orderId,
            'patient_name' => $data['patient_name'],
            'patient_phone' => $data['patient_phone'],
            'patient_gender' => isset($data['patient_gender']) ? $data['patient_gender'] : '未知',
            'patient_age' => isset($data['patient_age']) ? $data['patient_age'] : 0,
            'disease_name' => isset($data['disease_name']) ? $data['disease_name'] : '',
            'appointment_time' => $data['appointment_time'],
            'symptoms' => isset($data['symptoms']) ? $data['symptoms'] : ''
        ];
        $userData = [
            'nickname' => $user['nickname'] ?? '',
            'phone' => $user['phone'] ?? ''
        ];
        sendAppointmentEmail($appointmentData, $doctor, $userData);
        
        echo json_encode([
            'code' => 200,
            'message' => '预约成功',
            'data' => ['appointment_id' => $appointment_id]
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        // 回滚事务
        $db->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    // 记录错误信息到日志文件
    file_put_contents('error_log.txt', date('Y-m-d H:i:s') . ' - 预约失败: ' . $e->getMessage() . "\n", FILE_APPEND);
    
    echo json_encode([
        'code' => 500,
        'message' => '预约失败: ' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>