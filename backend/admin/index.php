<?php
// 管理后台首页
// 设置浏览器缓存控制
header('Cache-Control: max-age=86400, public');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

require_once __DIR__ . '/includes/config.php';

// 设置页面标题和活动页面
$pageTitle = '仪表盘';
$activePage = 'index';

// 统计数据
$db = getAdminDB();

// 获取用户数量
$userCount = 0;
if ($db !== null) {
    try {
        $userCount = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    } catch (Exception $e) {
        $userCount = 0;
    }
}

// 获取医生数量
$doctorCount = 0;
if ($db !== null) {
    try {
        $doctorCount = $db->query('SELECT COUNT(*) FROM doctors')->fetchColumn();
    } catch (Exception $e) {
        $doctorCount = 0;
    }
}

// 获取预约数量
$appointmentCount = 0;
if ($db !== null) {
    try {
        $appointmentCount = $db->query('SELECT COUNT(*) FROM appointments')->fetchColumn();
    } catch (Exception $e) {
        $appointmentCount = 0;
    }
}

// 获取最近预约数据
$recentAppointments = [];
if ($db !== null) {
    try {
        $stmt = $db->prepare('SELECT a.id, a.user_id, a.doctor_id, a.appointment_time, a.status, 
                               u.nickname as user_name, u.phone as user_phone, d.name as doctor_name 
                        FROM appointments a 
                        LEFT JOIN users u ON a.user_id = u.id 
                        LEFT JOIN doctors d ON a.doctor_id = d.id 
                        ORDER BY a.created_at DESC 
                        LIMIT 4');
        $stmt->execute();
        $recentAppointments = $stmt->fetchAll();
    } catch (Exception $e) {
        $recentAppointments = [];
    }
}

// 包含头部模板
require_once __DIR__ . '/includes/header.php';
?>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stats-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
    }
    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    .stats-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    .stats-title {
        font-size: 16px;
        font-weight: 500;
        color: #666;
        margin: 0;
    }
    .stats-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }
    .stats-value {
        font-size: 32px;
        font-weight: 600;
        color: #333;
        margin-bottom: 5px;
    }
    .stats-desc {
        font-size: 14px;
        color: #999;
    }
    .recent-appointments {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .section-title {
        font-size: 18px;
        font-weight: 600;
        margin: 0;
        color: #333;
    }
    .view-all {
        color: #667eea;
        text-decoration: none;
        font-size: 14px;
    }
    .view-all:hover {
        text-decoration: underline;
    }
    .appointments-table {
        width: 100%;
        border-collapse: collapse;
    }
    .appointments-table th,
    .appointments-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #e0e0e0;
    }
    .appointments-table th {
        font-weight: 600;
        color: #666;
        background-color: #f8f9fa;
    }
    .status-badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }
    .status-pending {
        background-color: #fff3cd;
        color: #856404;
    }
    .status-confirmed {
        background-color: #d1ecf1;
        color: #0c5460;
    }
    .status-completed {
        background-color: #d4edda;
        color: #155724;
    }
</style>

<!-- 统计卡片 -->
<div class="stats-grid">
    <div class="stats-card">
        <div class="stats-header">
            <h4 class="stats-title">用户总数</h4>
            <div class="stats-icon">
                <i class="fa fa-users"></i>
            </div>
        </div>
        <div class="stats-value"><?php echo $userCount; ?></div>
        <div class="stats-desc">系统注册用户数量</div>
    </div>
    <div class="stats-card">
        <div class="stats-header">
            <h4 class="stats-title">医生总数</h4>
            <div class="stats-icon">
                <i class="fa fa-user-md"></i>
            </div>
        </div>
        <div class="stats-value"><?php echo $doctorCount; ?></div>
        <div class="stats-desc">医院医生数量</div>
    </div>
    <div class="stats-card">
        <div class="stats-header">
            <h4 class="stats-title">预约总数</h4>
            <div class="stats-icon">
                <i class="fa fa-calendar"></i>
            </div>
        </div>
        <div class="stats-value"><?php echo $appointmentCount; ?></div>
        <div class="stats-desc">系统预约数量</div>
    </div>
</div>

<!-- 最近预约 -->
<div class="recent-appointments">
    <div class="section-header">
            <h5 class="section-title">最近预约</h5>
            <a href="pages/appointments.php" class="view-all">查看全部</a>
        </div>
    <table class="appointments-table">
        <thead>
            <tr>
                <th>预约ID</th>
                <th>患者姓名</th>
                <th>手机号</th>
                <th>预约医生</th>
                <th>预约时间</th>
                <th>状态</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($recentAppointments)): ?>
                <?php foreach ($recentAppointments as $appointment): ?>
                    <tr>
                        <td><?php echo $appointment['id']; ?></td>
                        <td><?php echo $appointment['user_name'] ?: '未知'; ?></td>
                        <td><?php echo $appointment['user_phone'] ?: '未知'; ?></td>
                        <td><?php echo $appointment['doctor_name'] ?: '未知'; ?></td>
                        <td>
                            <?php
                            // 提取时间部分并判断是上午还是下午
                            $appointmentTime = $appointment['appointment_time'];
                            $hour = date('H', strtotime($appointmentTime));
                            $timeSlot = $hour < 12 ? '上午' : '下午';
                            // 显示日期和时间段
                            echo date('Y-m-d', strtotime($appointmentTime)) . ' ' . $timeSlot;
                            ?>
                        </td>
                        <td>
                            <?php
                            // 根据状态显示不同的徽章
                            switch ($appointment['status']) {
                                case 'pending':
                                    echo '<span class="status-badge status-pending">待确认</span>';
                                    break;
                                case 'confirmed':
                                    echo '<span class="status-badge status-confirmed">已确认</span>';
                                    break;
                                case 'completed':
                                    echo '<span class="status-badge status-completed">已完成</span>';
                                    break;
                                default:
                                    echo '<span class="status-badge status-pending">待确认</span>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center">暂无预约数据</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// 包含底部模板
require_once __DIR__ . '/includes/footer.php';
?>