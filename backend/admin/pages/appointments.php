<?php
// 管理后台预约管理页面
// 设置浏览器缓存控制
header('Cache-Control: max-age=86400, public');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

require_once __DIR__ . '/../includes/config.php';

// 检查是否需要导出Excel
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    // 导出Excel功能
    function exportToExcel($data, $filename) {
        // 设置响应头
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        header('Cache-Control: max-age=0');
        
        // 打开输出缓冲区
        ob_start();
        
        // 创建HTML表格作为Excel内容
        echo '<table border="1">';
        
        // 输出表头
        echo '<tr>';
        echo '<th>预约ID</th>';
        echo '<th>订单编号</th>';
        echo '<th>用户姓名</th>';
        echo '<th>用户手机号</th>';
        echo '<th>IP地址</th>';
        echo '<th>IP地区</th>';
        echo '<th>患者姓名</th>';
        echo '<th>患者手机号</th>';
        echo '<th>性别</th>';
        echo '<th>年龄</th>';
        echo '<th>预约病种</th>';
        echo '<th>预约医生</th>';
        echo '<th>预约时间</th>';
        echo '<th>病情症状描述</th>';
        echo '<th>创建时间</th>';
        echo '<th>状态</th>';
        echo '</tr>';
        
        // 输出数据行
        foreach ($data as $row) {
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . $row['order_id'] . '</td>';
            echo '<td>' . ($row['user_name'] ?: '-') . '</td>';
            echo '<td>' . ($row['user_phone'] ?: '-') . '</td>';
            echo '<td>' . ($row['ip_address'] ?: '-') . '</td>';
            echo '<td>' . ($row['ip_location'] ?: '-') . '</td>';
            echo '<td>' . $row['patient_name'] . '</td>';
            echo '<td>' . $row['patient_phone'] . '</td>';
            echo '<td>' . $row['patient_gender'] . '</td>';
            echo '<td>' . $row['patient_age'] . '</td>';
            echo '<td>' . ($row['disease_name'] ?: '-') . '</td>';
            echo '<td>' . $row['doctor_name'] . '</td>';
            echo '<td>' . $row['appointment_time'] . '</td>';
            echo '<td>' . ($row['symptoms'] ?: '-') . '</td>';
            echo '<td>' . $row['created_at'] . '</td>';
            echo '<td>';
            switch ($row['status']) {
                case 'pending':
                    echo '待确认到诊';
                    break;
                case 'confirmed':
                    echo '已确认到诊';
                    break;
                case 'completed':
                    echo '已完成到诊';
                    break;
                case 'cancelled':
                    echo '已取消到诊';
                    break;
                default:
                    echo '未知';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        
        // 输出缓冲区内容
        ob_end_flush();
        exit;
    }
    
    // 获取所有预约数据
    $db = getAdminDB();
    $stmt = $db->query('SELECT a.id, a.user_name, a.user_phone, a.ip_address, a.ip_location, a.order_id, a.patient_name, a.patient_phone, a.patient_gender, a.patient_age, a.disease_name, a.symptoms, d.name as doctor_name, a.appointment_time, a.status, a.created_at FROM appointments a JOIN doctors d ON a.doctor_id = d.id ORDER BY a.created_at DESC');
    $appointments = $stmt->fetchAll();
    
    // 导出Excel
    exportToExcel($appointments, 'appointments_' . date('YmdHis'));
    exit;
}

// 页面标题
$pageTitle = '预约管理';
// 当前活动页面
$activePage = 'appointments';

$db = getAdminDB();

// 分页参数
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$pageSize = 8; // 每页显示8条记录
$offset = ($page - 1) * $pageSize;

// 构建搜索条件
$whereConditions = [];
$bindValues = [];

// 普通搜索
if (isset($_GET['search'])) {
    $searchTerm = $_GET['search'];
    $whereConditions[] = '(a.order_id LIKE ? OR a.user_phone LIKE ? OR a.patient_phone LIKE ? OR a.user_name LIKE ? OR a.patient_name LIKE ?)';
    $bindValues[] = '%' . $searchTerm . '%';
    $bindValues[] = '%' . $searchTerm . '%';
    $bindValues[] = '%' . $searchTerm . '%';
    $bindValues[] = '%' . $searchTerm . '%';
    $bindValues[] = '%' . $searchTerm . '%';
}

// 高级搜索
if (isset($_GET['order_id'])) {
    $whereConditions[] = 'a.order_id LIKE ?';
    $bindValues[] = '%' . $_GET['order_id'] . '%';
}

if (isset($_GET['phone'])) {
    $whereConditions[] = '(a.user_phone LIKE ? OR a.patient_phone LIKE ?)';
    $bindValues[] = '%' . $_GET['phone'] . '%';
    $bindValues[] = '%' . $_GET['phone'] . '%';
}

if (isset($_GET['name'])) {
    $whereConditions[] = '(a.user_name LIKE ? OR a.patient_name LIKE ?)';
    $bindValues[] = '%' . $_GET['name'] . '%';
    $bindValues[] = '%' . $_GET['name'] . '%';
}

if (isset($_GET['department'])) {
    $whereConditions[] = 'a.department LIKE ?';
    $bindValues[] = '%' . $_GET['department'] . '%';
}

if (isset($_GET['doctor'])) {
    $whereConditions[] = 'd.name LIKE ?';
    $bindValues[] = '%' . $_GET['doctor'] . '%';
}

if (isset($_GET['disease'])) {
    $whereConditions[] = 'a.disease_name LIKE ?';
    $bindValues[] = '%' . $_GET['disease'] . '%';
}

if (isset($_GET['status'])) {
    $whereConditions[] = 'a.status = ?';
    $bindValues[] = $_GET['status'];
}

if (isset($_GET['date'])) {
    $whereConditions[] = 'DATE(a.appointment_time) = ?';
    $bindValues[] = $_GET['date'];
}

// 构建WHERE子句
$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// 初始化变量
$totalCount = 0;
$totalPages = 0;
$appointments = [];

// 检查数据库连接
if ($db) {
    try {
        // 获取总记录数
        $countQuery = 'SELECT COUNT(*) FROM appointments a JOIN doctors d ON a.doctor_id = d.id ' . $whereClause;
        $countStmt = $db->prepare($countQuery);
        foreach ($bindValues as $index => $value) {
            $countStmt->bindValue($index + 1, $value);
        }
        $countStmt->execute();
        $totalCount = $countStmt->fetchColumn();
        $totalPages = ceil($totalCount / $pageSize);

        // 获取预约列表
        $selectQuery = 'SELECT a.id, a.user_name, a.user_phone, a.ip_address, a.ip_location, a.order_id, a.patient_name, a.patient_phone, a.patient_gender, a.patient_age, a.disease_name, a.symptoms, d.name as doctor_name, a.appointment_time, a.status, a.created_at FROM appointments a JOIN doctors d ON a.doctor_id = d.id ' . $whereClause . ' ORDER BY a.created_at DESC LIMIT ? OFFSET ?';
        $stmt = $db->prepare($selectQuery);

        // 绑定搜索参数
        foreach ($bindValues as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }

        // 绑定分页参数
        $stmt->bindValue(count($bindValues) + 1, $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(count($bindValues) + 2, $offset, PDO::PARAM_INT);

        $stmt->execute();
        $appointments = $stmt->fetchAll();
    } catch (PDOException $e) {
        // 记录错误但不中断执行
        error_log('数据库查询失败: ' . $e->getMessage());
    }
}

// 包含头部模板
require_once __DIR__ . '/../includes/header.php';

// 页面特定样式
?>

<style>
    .table-container {
        overflow-x: auto;
        width: 100%;
    }
    .appointments-table {
        min-width: 1200px;
    }
    .appointments-table th {
        white-space: nowrap;
        text-align: center;
        vertical-align: middle;
        padding: 8px 12px;
        font-size: 13px;
    }
    .appointments-table td {
        text-align: center;
        vertical-align: middle;
        padding: 8px 12px;
        font-size: 13px;
    }
    .appointments-table .user-info, 
    .appointments-table .patient-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
        align-items: center;
    }
    .appointments-table .user-info strong, 
    .appointments-table .patient-info strong {
        font-size: 13px;
    }
    .appointments-table .user-info span, 
    .appointments-table .patient-info span {
        font-size: 12px;
    }
    .appointments-table .action-buttons {
        display: flex;
        gap: 4px;
        justify-content: center;
    }
    .appointments-table .action-buttons .btn {
        padding: 4px 8px;
        font-size: 12px;
    }
    .appointments-table .time-slot {
        display: flex;
        flex-direction: column;
        gap: 2px;
        align-items: center;
    }
    .appointments-table .time-slot span {
        font-size: 12px;
    }
    @media (max-width: 768px) {
        .table-container {
            margin-bottom: 20px;
        }
        .appointments-table {
            min-width: 900px;
        }
        .appointments-table th,
        .appointments-table td {
            padding: 6px 8px;
            font-size: 12px;
        }
    }
</style>

                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center bg-white border-bottom">
                        <h5 class="card-title mb-0">预约列表</h5>
                        <div class="d-flex gap-2">
                            <button id="batchDeleteBtn" class="btn btn-danger btn-sm">
                                <i class="fa fa-trash me-1"></i> 批量删除
                            </button>
                            <a href="?action=export" class="btn btn-success btn-sm">
                                <i class="fa fa-download me-1"></i> 导出Excel
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <div class="d-flex gap-2 mb-3">
                                <input type="text" id="searchInput" class="form-control" placeholder="搜索订单号、手机号、姓名..." style="flex: 1;">
                                <button id="searchBtn" class="btn btn-primary btn-sm">
                                    <i class="fa fa-search me-1"></i> 搜索
                                </button>
                                <button id="advancedSearchBtn" class="btn btn-info btn-sm">
                                    <i class="fa fa-filter me-1"></i> 高级搜索
                                </button>
                            </div>
                        </div>
                        <div class="table-container">
                            <table class="table table-striped table-hover mb-0 appointments-table">
                                <thead class="bg-light">
                                    <tr>
                                        <th><input type="checkbox" id="selectAll"></th>
                                        <th>预约ID</th>
                                        <th>订单编号</th>
                                        <th>用户信息</th>
                                        <th>IP地址</th>
                                        <th>IP地区</th>
                                        <th>患者信息</th>
                                        <th>性别</th>
                                        <th>年龄</th>
                                        <th>预约病种</th>
                                        <th>预约医生</th>
                                        <th>预约时间</th>
                                        <th>病情症状</th>
                                        <th>创建时间</th>
                                        <th>状态</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): ?>
                                    <tr>
                                        <td><input type="checkbox" class="appointment-checkbox" value="<?php echo $appointment['id']; ?>"></td>
                                        <td><?php echo $appointment['id']; ?></td>
                                        <td><?php echo $appointment['order_id']; ?></td>
                                        <td>
                                            <div class="user-info">
                                                <strong><?php echo $appointment['user_name'] ?: '未设置'; ?></strong>
                                                <span><?php echo $appointment['user_phone'] ?: '未设置'; ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo $appointment['ip_address'] ?: '未设置'; ?></td>
                                        <td><?php echo $appointment['ip_location'] ?: '未设置'; ?></td>
                                        <td>
                                            <div class="patient-info">
                                                <strong><?php echo $appointment['patient_name']; ?></strong>
                                                <span><?php echo $appointment['patient_phone']; ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo $appointment['patient_gender']; ?></td>
                                        <td><?php echo $appointment['patient_age']; ?></td>
                                        <td><?php echo $appointment['disease_name'] ?: '未设置'; ?></td>
                                        <td><?php echo $appointment['doctor_name']; ?></td>
                                        <td>
                                            <div class="time-slot">
                                                <?php 
                                                    $appointmentTime = $appointment['appointment_time'];
                                                    $hour = date('H', strtotime($appointmentTime));
                                                    if ($hour >= 6 && $hour < 12) {
                                                        echo '<span class="text-success">上午</span>';
                                                    } else if ($hour >= 12 && $hour < 18) {
                                                        echo '<span class="text-primary">下午</span>';
                                                    } else {
                                                        echo '<span class="text-info">晚上</span>';
                                                    }
                                                ?>
                                                <span><?php echo date('Y-m-d H:i', strtotime($appointment['appointment_time'])); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo $appointment['symptoms'] ?: '未填写'; ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($appointment['created_at'])); ?></td>
                                        <td>
                                            <?php if ($appointment['status'] == 'pending'): ?>
                                                <span class="badge bg-warning">待确认</span>
                                            <?php elseif ($appointment['status'] == 'confirmed'): ?>
                                                <span class="badge bg-success">已确认</span>
                                            <?php elseif ($appointment['status'] == 'completed'): ?>
                                                <span class="badge bg-primary">已完成</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">已取消</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-primary btn-sm btn-edit-appointment"><i class="fa fa-edit"></i></button>
                                                <button class="btn btn-success btn-sm"><i class="fa fa-check"></i></button>
                                                <button class="btn btn-danger btn-sm"><i class="fa fa-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- 分页导航 -->
                        <div class="card-footer bg-white border-top">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted">
                                    共 <?php echo $totalCount; ?> 条数据
                                </div>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination mb-0">
                                        <li class="page-item <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                        <?php 
                                            $startPage = max(1, $page - 2);
                                            $endPage = min($totalPages, $startPage + 4);
                                            
                                            if ($startPage > 1) {
                                                echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                                                if ($startPage > 2) {
                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                }
                                            }
                                            
                                            for ($i = $startPage; $i <= $endPage; $i++): 
                                        ?>
                                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                        <?php endfor; ?>
                                        <?php 
                                            if ($endPage < $totalPages) {
                                                if ($endPage < $totalPages - 1) {
                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                }
                                                echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '">' . $totalPages . '</a></li>';
                                            }
                                        ?>
                                        <li class="page-item <?php echo $page == $totalPages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                                <div class="text-muted">
                                    当前第 <?php echo $page; ?>/<?php echo $totalPages; ?> 页
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

    <!-- 编辑预约模态框 -->
    <div class="modal fade" id="editAppointmentModal" tabindex="-1" aria-labelledby="editAppointmentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAppointmentModalLabel">编辑预约</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editAppointmentForm">
                        <input type="hidden" id="appointmentId" name="id">
                        <div class="mb-3">
                            <label for="editPatientName" class="form-label">患者姓名</label>
                            <input type="text" class="form-control" id="editPatientName" name="patient_name">
                        </div>
                        <div class="mb-3">
                            <label for="editPatientPhone" class="form-label">患者手机号</label>
                            <input type="text" class="form-control" id="editPatientPhone" name="patient_phone">
                        </div>
                        <div class="mb-3">
                            <label for="editDoctorId" class="form-label">预约医生</label>
                            <select class="form-select" id="editDoctorId" name="doctor_id">
                                <?php
                                // 获取医生列表
                                $doctorsStmt = $db->query('SELECT id, name FROM doctors');
                                $doctors = $doctorsStmt->fetchAll();
                                foreach ($doctors as $doc) {
                                    echo "<option value='{$doc['id']}'>{$doc['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editAppointmentTime" class="form-label">预约时间</label>
                            <input type="datetime-local" class="form-control" id="editAppointmentTime" name="appointment_time">
                        </div>
                        <div class="mb-3">
                            <label for="editStatus" class="form-label">状态</label>
                            <select class="form-select" id="editStatus" name="status">
                                <option value="pending">待确认到诊</option>
                                <option value="confirmed">已确认到诊</option>
                                <option value="completed">已完成到诊</option>
                                <option value="cancelled">已取消到诊</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" id="saveAppointmentBtn">保存</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 高级搜索模态框 -->
    <div class="modal fade" id="advancedSearchModal" tabindex="-1" aria-labelledby="advancedSearchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="advancedSearchModalLabel">高级搜索</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="advancedSearchForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="searchOrderId" class="form-label">订单编号</label>
                                <input type="text" class="form-control" id="searchOrderId" name="order_id" placeholder="输入订单编号">
                            </div>
                            <div class="col-md-6">
                                <label for="searchPhone" class="form-label">手机号</label>
                                <input type="text" class="form-control" id="searchPhone" name="phone" placeholder="输入用户或患者手机号">
                            </div>
                            <div class="col-md-6">
                                <label for="searchName" class="form-label">姓名</label>
                                <input type="text" class="form-control" id="searchName" name="name" placeholder="输入用户或患者姓名">
                            </div>
                            <div class="col-md-6">
                                <label for="searchDepartment" class="form-label">科室</label>
                                <select class="form-select" id="searchDepartment" name="department">
                                    <option value="">选择科室</option>
                                    <?php
                                    // 获取科室列表
                                    if ($db) {
                                        try {
                                            $departmentsStmt = $db->query('SELECT id, name FROM departments');
                                            $departments = $departmentsStmt->fetchAll();
                                            foreach ($departments as $dept) {
                                                echo "<option value='{$dept['name']}'>{$dept['name']}</option>";
                                            }
                                        } catch (PDOException $e) {
                                            // 记录错误但不中断执行
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="searchDoctor" class="form-label">预约医生</label>
                                <select class="form-select" id="searchDoctor" name="doctor">
                                    <option value="">选择医生</option>
                                    <?php
                                    // 获取医生列表
                                    if ($db) {
                                        try {
                                            $doctorsStmt = $db->query('SELECT id, name FROM doctors');
                                            $doctors = $doctorsStmt->fetchAll();
                                            foreach ($doctors as $doc) {
                                                echo "<option value='{$doc['name']}'>{$doc['name']}</option>";
                                            }
                                        } catch (PDOException $e) {
                                            // 记录错误但不中断执行
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="searchDisease" class="form-label">病种</label>
                                <input type="text" class="form-control" id="searchDisease" name="disease" placeholder="输入病种名称">
                            </div>
                            <div class="col-md-6">
                                <label for="searchStatus" class="form-label">预约状态</label>
                                <select class="form-select" id="searchStatus" name="status">
                                    <option value="">选择状态</option>
                                    <option value="pending">待确认到诊</option>
                                    <option value="confirmed">已确认到诊</option>
                                    <option value="completed">已完成到诊</option>
                                    <option value="cancelled">已取消到诊</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="searchDate" class="form-label">预约日期</label>
                                <input type="date" class="form-control" id="searchDate" name="date">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" id="doAdvancedSearch">搜索</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 等待DOM加载完成
        document.addEventListener('DOMContentLoaded', function() {
            // 初始化编辑预约模态框
            const editAppointmentModal = new bootstrap.Modal(document.getElementById('editAppointmentModal'));
            
            // 初始化高级搜索模态框
            const advancedSearchModal = new bootstrap.Modal(document.getElementById('advancedSearchModal'));
            
            // 打开高级搜索模态框
            const advancedSearchBtn = document.getElementById('advancedSearchBtn');
            if (advancedSearchBtn) {
                advancedSearchBtn.addEventListener('click', function() {
                    advancedSearchModal.show();
                });
            }
            
            // 普通搜索
            const searchBtn = document.getElementById('searchBtn');
            if (searchBtn) {
                searchBtn.addEventListener('click', function() {
                    const searchInput = document.getElementById('searchInput');
                    if (searchInput) {
                        const searchTerm = searchInput.value.trim();
                        if (searchTerm) {
                            window.location.href = '?search=' + encodeURIComponent(searchTerm);
                        }
                    }
                });
            }
            
            // 回车键触发搜索
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        const searchBtn = document.getElementById('searchBtn');
                        if (searchBtn) {
                            searchBtn.click();
                        }
                    }
                });
            }
            
            // 高级搜索
            const doAdvancedSearch = document.getElementById('doAdvancedSearch');
            if (doAdvancedSearch) {
                doAdvancedSearch.addEventListener('click', function() {
                    const form = document.getElementById('advancedSearchForm');
                    if (form) {
                        const formData = new FormData(form);
                        const params = new URLSearchParams();
                        
                        formData.forEach((value, key) => {
                            if (value) {
                                params.append(key, value);
                            }
                        });
                        
                        const queryString = params.toString();
                        window.location.href = '?' + (queryString ? queryString : '');
                    }
                });
            }
            
            // 编辑按钮点击事件
            document.querySelectorAll('.btn-edit-appointment').forEach(btn => {
                btn.addEventListener('click', function() {
                    const appointmentId = this.closest('tr').querySelector('td:nth-child(2)').textContent;
                    
                    // 发送请求获取预约详情
                    fetch('../includes/appointment_manage.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'action=get&id=' + appointmentId
                    })
                    .then(response => {
                        console.log('Response status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Response data:', data);
                        if (data.code === 200) {
                            const appointment = data.data;
                            console.log('Appointment data:', appointment);
                            
                            // 填充表单数据
                            document.getElementById('appointmentId').value = appointment.id;
                            document.getElementById('editPatientName').value = appointment.patient_name || '';
                            document.getElementById('editPatientPhone').value = appointment.patient_phone || '';
                            document.getElementById('editDoctorId').value = appointment.doctor_id;
                            document.getElementById('editAppointmentTime').value = appointment.appointment_time.replace(' ', 'T');
                            document.getElementById('editStatus').value = appointment.status;
                            
                            // 显示模态框
                            editAppointmentModal.show();
                        } else {
                            alert('获取预约信息失败: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('网络错误，请重试: ' + error.message);
                    });
                });
            });
            
            // 确认按钮点击事件
            document.querySelectorAll('.btn-success').forEach(btn => {
                btn.addEventListener('click', function() {
                    const appointmentId = this.closest('tr').querySelector('td:nth-child(2)').textContent;
                    if (confirm('确定要确认这个预约吗？')) {
                        // 发送AJAX请求确认预约
                        fetch('../includes/appointment_manage.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'action=confirm&id=' + appointmentId
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.code === 200) {
                                alert('预约 ID: ' + appointmentId + ' 已确认');
                                // 刷新页面
                                window.location.reload();
                            } else {
                                alert('确认失败: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('网络错误，请重试: ' + error.message);
                        });
                    }
                });
            });
            
            // 删除按钮点击事件
            document.querySelectorAll('.btn-danger').forEach(btn => {
                btn.addEventListener('click', function() {
                    const appointmentId = this.closest('tr').querySelector('td:nth-child(2)').textContent;
                    if (confirm('确定要删除这个预约吗？')) {
                        // 发送AJAX请求删除预约
                        fetch('../includes/appointment_manage.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'action=delete&id=' + appointmentId
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.code === 200) {
                                alert('预约 ID: ' + appointmentId + ' 已删除');
                                // 刷新页面
                                window.location.reload();
                            } else {
                                alert('删除失败: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('网络错误，请重试: ' + error.message);
                        });
                    }
                });
            });
            
            // 保存预约按钮点击事件
            const saveAppointmentBtn = document.getElementById('saveAppointmentBtn');
            if (saveAppointmentBtn) {
                saveAppointmentBtn.addEventListener('click', function() {
                    const form = document.getElementById('editAppointmentForm');
                    if (form) {
                        const formData = new FormData(form);
                        
                        // 转换为普通对象
                        const appointmentData = {
                            action: 'update'
                        };
                        formData.forEach((value, key) => {
                            appointmentData[key] = value;
                        });
                        
                        // 发送请求更新预约
                        fetch('../includes/appointment_manage.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: new URLSearchParams(appointmentData).toString()
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.code === 200) {
                                // 关闭模态框
                                editAppointmentModal.hide();
                                // 刷新页面
                                window.location.reload();
                            } else {
                                alert('更新失败: ' + data.message);
                            }
                        })
                        .catch(error => {
                            alert('网络错误，请重试');
                        });
                    }
                });
            }
            
            // 全选/取消全选
            const selectAll = document.getElementById('selectAll');
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('.appointment-checkbox');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
            }
            
            // 批量删除按钮点击事件
            const batchDeleteBtn = document.getElementById('batchDeleteBtn');
            if (batchDeleteBtn) {
                batchDeleteBtn.addEventListener('click', function() {
                    const checkboxes = document.querySelectorAll('.appointment-checkbox:checked');
                    if (checkboxes.length === 0) {
                        alert('请选择要删除的预约');
                        return;
                    }
                    
                    if (confirm('确定要删除选中的' + checkboxes.length + '个预约吗？')) {
                        const ids = Array.from(checkboxes).map(checkbox => checkbox.value).join(',');
                        
                        // 发送AJAX请求批量删除预约
                        fetch('../includes/appointment_manage.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'action=batchDelete&ids=' + ids
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.code === 200) {
                                alert('批量删除成功');
                                // 刷新页面
                                window.location.reload();
                            } else {
                                alert('批量删除失败: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('网络错误，请重试: ' + error.message);
                        });
                    }
                });
            }
        });
    </script>

<?php
// 包含底部模板
require_once __DIR__ . '/../includes/footer.php';
?>