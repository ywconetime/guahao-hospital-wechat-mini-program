<?php
// 管理后台医生管理页面
// 设置浏览器缓存控制
header('Cache-Control: max-age=86400, public');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

require_once __DIR__ . '/../includes/config.php';

// 页面标题
$pageTitle = '医生管理';
// 当前活动页面
$activePage = 'doctors';

$db = getAdminDB();

// 分页配置
$pageSize = 20; // 每页20条数据
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage); // 确保页码至少为1

// 获取总数据量
$totalStmt = $db->query('SELECT COUNT(*) FROM doctors');
$totalCount = (int)$totalStmt->fetchColumn();

// 计算总页数
$totalPages = ceil($totalCount / $pageSize);
$totalPages = max(1, $totalPages); // 确保至少有1页

// 计算偏移量
$offset = ($currentPage - 1) * $pageSize;

// 获取医生列表，一次性获取所有医生的绑定科室和病种（带分页）
$stmt = $db->prepare('SELECT d.*, 
                           (SELECT GROUP_CONCAT(DISTINCT de.name) 
                            FROM doctor_departments dd 
                            LEFT JOIN departments de ON dd.department_id = de.id 
                            WHERE dd.doctor_id = d.id) as bound_departments, 
                           (SELECT GROUP_CONCAT(DISTINCT di.name) 
                            FROM doctor_diseases ddi 
                            LEFT JOIN diseases di ON ddi.disease_id = di.id 
                            WHERE ddi.doctor_id = d.id) as bound_diseases 
                    FROM doctors d 
                    ORDER BY d.id DESC 
                    LIMIT :limit OFFSET :offset');
$stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$doctors = $stmt->fetchAll();

// 处理空值
foreach ($doctors as &$doctor) {
    $doctor['bound_departments'] = $doctor['bound_departments'] ?: '';
    $doctor['bound_diseases'] = $doctor['bound_diseases'] ?: '';
}

// 解除引用，避免后续代码中的意外行为
unset($doctor);

// 调试信息
// echo '<pre>';
// print_r($doctors);
// echo '</pre>';
// exit;

// 包含头部模板
require_once __DIR__ . '/../includes/header.php';

// 页面特定样式
?>
<style>
    /* 医生管理页面样式 */
    .card {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
        border-radius: 0.5rem;
        border: none;
    }
    
    .card-header {
        border-bottom: 1px solid #e9ecef;
        background-color: #ffffff;
        border-radius: 0.5rem 0.5rem 0 0;
    }
    
    .card-title {
        font-weight: 600;
        color: #343a40;
    }
    
    .btn-add {
        border-radius: 2rem;
        font-weight: 500;
    }
    
    /* 表格样式 */
    .table {
        font-size: 0.875rem;
    }
    
    .table th {
        font-weight: 600;
        color: #495057;
        border-bottom: 2px solid #e9ecef;
        padding: 1rem;
    }
    
    .table td {
        padding: 1rem;
        vertical-align: middle;
    }
    
    .table-striped > tbody > tr:nth-of-type(odd) {
        background-color: rgba(0, 0, 0, 0.02);
    }
    
    .table-hover > tbody > tr:hover {
        background-color: rgba(0, 123, 255, 0.05);
    }
    
    /* 医生信息样式 */
    .doctor-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #f8f9fa;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .doctor-name {
        font-size: 1.1rem;
        font-weight: 600;
        color: #343a40;
    }
    
    .doctor-title {
        font-size: 0.875rem;
        color: #6c757d;
    }
    
    .doctor-dept {
        font-size: 0.875rem;
        color: #6c757d;
    }
    
    /* 内容显示样式 */
    .specialty-text,
    .department-text,
    .disease-text {
        line-height: 1.4;
        word-break: break-word;
    }
    
    .specialty-content,
    .department-content,
    .disease-content {
        max-height: 120px;
        overflow-y: auto;
    }
    
    /* 操作按钮样式 */
    .btn-sm {
        font-size: 0.75rem;
        padding: 0.375rem 0.75rem;
    }
    
    .btn-rounded {
        border-radius: 2rem;
    }
    
    /* 分页样式 */
    .pagination {
        margin-top: 0.5rem;
    }
    
    .page-item.active .page-link {
        background-color: #007bff;
        border-color: #007bff;
    }
    
    .page-link {
        color: #007bff;
    }
    
    .page-link:hover {
        color: #0056b3;
    }
    
    /* 响应式设计 */
    @media (max-width: 768px) {
        .table-responsive {
            border: 1px solid #dee2e6;
        }
        
        .doctor-avatar {
            width: 60px;
            height: 60px;
        }
        
        .doctor-name {
            font-size: 1rem;
        }
    }
</style>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title">医生列表</h5>
        <button class="btn btn-primary btn-sm btn-add">
            <i class="fa fa-plus"></i> 添加医生
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-borderless">
                <thead class="bg-light">
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th style="width: 300px;">医生信息</th>
                        <th style="width: 300px;">专长</th>
                        <th style="width: 150px;">科室</th>
                        <th style="width: 200px;">病种</th>
                        <th style="width: 180px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doctors as $doctor): ?>
                    <tr class="align-middle">
                        <td class="text-center font-weight-medium"><?php echo $doctor['id']; ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar-container">
                                    <img src="<?php echo $doctor['avatar'] ? '../uploads/' . basename($doctor['avatar']) : 'https://via.placeholder.com/80'; ?>" class="doctor-avatar" alt="医生头像">
                                </div>
                                <div class="doctor-info">
                                    <h6 class="doctor-name mb-1"><?php echo $doctor['name']; ?></h6>
                                    <p class="doctor-title mb-1 text-muted"><?php echo $doctor['title']; ?></p>
                                    <p class="doctor-dept text-muted"><?php echo $doctor['department']; ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="specialty-content">
                            <div class="specialty-text"><?php echo $doctor['specialty']; ?></div>
                        </td>
                        <td class="department-content">
                            <div class="department-text"><?php echo $doctor['bound_departments'] ?: '-'; ?></div>
                        </td>
                        <td class="disease-content">
                            <div class="disease-text"><?php echo $doctor['bound_diseases'] ?: '-'; ?></div>
                        </td>
                        <td>
                            <div class="d-flex gap-2 justify-content-center">
                                <button class="btn btn-sm btn-primary rounded-pill"><i class="fa fa-edit me-1"></i> 编辑</button>
                                <button class="btn btn-sm btn-success rounded-pill" onclick="openBindModal(<?php echo $doctor['id']; ?>)"><i class="fa fa-link me-1"></i> 绑定</button>
                                <button class="btn btn-sm btn-info rounded-pill" onclick="openScheduleModal(<?php echo $doctor['id']; ?>, '<?php echo addslashes($doctor['name']); ?>')"><i class="fa fa-calendar me-1"></i> 排班</button>
                                <button class="btn btn-sm btn-danger rounded-pill"><i class="fa fa-trash me-1"></i> 删除</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- 分页控件 -->
    <div class="card-body border-top">
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <!-- 上一页 -->
                <li class="page-item <?php echo $currentPage == 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $currentPage - 1; ?>">上一页</a>
                </li>
                
                <!-- 页码 -->
                <?php
                // 显示页码范围
                $startPage = max(1, $currentPage - 2);
                $endPage = min($totalPages, $startPage + 4);
                
                // 显示第一页
                if ($startPage > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                    if ($startPage > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }
                
                // 显示中间页码
                for ($i = $startPage; $i <= $endPage; $i++) {
                    echo '<li class="page-item ' . ($i == $currentPage ? 'active' : '') . '"><a class="page-link" href="?page=' . $i . '">' . $i . '</a></li>';
                }
                
                // 显示最后一页
                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '">' . $totalPages . '</a></li>';
                }
                ?>
                
                <!-- 下一页 -->
                <li class="page-item <?php echo $currentPage == $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $currentPage + 1; ?>">下一页</a>
                </li>
            </ul>
        </nav>
        <div class="text-center mt-2">
            <small class="text-muted">共 <?php echo $totalCount; ?> 条数据，当前第 <?php echo $currentPage; ?>/<?php echo $totalPages; ?> 页</small>
        </div>
    </div>
</div>

<!-- 添加医生模态框 -->
    <div class="modal fade" id="addDoctorModal" tabindex="-1" aria-labelledby="addDoctorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDoctorModalLabel">添加医生</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addDoctorForm">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="name" class="form-label">姓名</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="title" class="form-label">职称</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="department" class="form-label">科室</label>
                            <input type="text" class="form-control" id="department" name="department" required>
                        </div>
                        <div class="mb-3">
                            <label for="specialty" class="form-label">专长</label>
                            <input type="text" class="form-control" id="specialty" name="specialty">
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">描述</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="avatar" class="form-label">头像</label>
                            <div class="mb-2">
                                <div id="avatarPreview" style="width: 120px; height: 120px; border: 2px dashed #ccc; border-radius: 50%; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 10px;">
                                    <span id="avatarPlaceholder" style="color: #999;">点击上传头像</span>
                                    <img id="avatarImage" src="" alt="头像预览" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                                </div>
                                <input type="file" class="form-control" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif" style="display: none;">
                                <p class="text-muted text-sm">支持JPG、PNG、GIF格式，尺寸1:1，大小不超过1M</p>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" form="addDoctorForm" class="btn btn-primary">保存</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 编辑医生模态框 -->
    <div class="modal fade" id="editDoctorModal" tabindex="-1" aria-labelledby="editDoctorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDoctorModalLabel">编辑医生</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editDoctorForm">
                        <input type="hidden" name="action" value="edit">
                        <div class="mb-3">
                            <label for="editName" class="form-label">姓名</label>
                            <input type="text" class="form-control" id="editName" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="editTitle" class="form-label">职称</label>
                            <input type="text" class="form-control" id="editTitle" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="editDepartment" class="form-label">科室</label>
                            <input type="text" class="form-control" id="editDepartment" name="department" required>
                        </div>
                        <div class="mb-3">
                            <label for="editSpecialty" class="form-label">专长</label>
                            <input type="text" class="form-control" id="editSpecialty" name="specialty">
                        </div>
                        <div class="mb-3">
                            <label for="editDescription" class="form-label">描述</label>
                            <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="editAvatar" class="form-label">头像</label>
                            <div class="mb-2">
                                <div id="editAvatarPreview" style="width: 120px; height: 120px; border: 2px dashed #ccc; border-radius: 50%; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 10px;">
                                    <span id="editAvatarPlaceholder" style="color: #999;">点击上传头像</span>
                                    <img id="editAvatarImage" src="" alt="头像预览" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                                </div>
                                <input type="file" class="form-control" id="editAvatar" name="avatar" accept="image/jpeg,image/png,image/gif" style="display: none;">
                                <p class="text-muted text-sm">支持JPG、PNG、GIF格式，尺寸1:1，大小不超过1M</p>
                            </div>
                        </div>
                        
                        <!-- 绑定科室和病种 -->
                        <div class="mb-3">
                            <label class="form-label">绑定科室</label>
                            <div id="editDepartmentList" class="row"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">绑定病种</label>
                            <div id="editDiseaseList" class="row"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" form="editDoctorForm" class="btn btn-primary">保存</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 删除医生确认模态框 -->
    <div class="modal fade" id="deleteDoctorModal" tabindex="-1" aria-labelledby="deleteDoctorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteDoctorModalLabel">确认删除</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>确定要删除医生 <span id="deleteDoctorName" class="font-weight-bold"></span> 吗？</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" id="confirmDelete" class="btn btn-danger">删除</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 绑定科室和病种模态框 -->
    <div class="modal fade" id="bindDoctorModal" tabindex="-1" aria-labelledby="bindDoctorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bindDoctorModalLabel">绑定科室和病种</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="bindDoctorForm">
                        <input type="hidden" name="action" value="bind">
                        <input type="hidden" id="bindDoctorId" name="doctor_id">
                        
                        <div class="mb-3">
                            <label class="form-label">绑定科室</label>
                            <div id="departmentList" class="row"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">绑定病种</label>
                            <div id="diseaseList" class="row"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" form="bindDoctorForm" class="btn btn-primary">保存</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 排班管理模态框 -->
    <div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="scheduleModalLabel">排班管理 - <span id="scheduleDoctorName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="scheduleDoctorId" value="">
                    
                    <!-- 排班模式切换 -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h6 class="mb-2">排班模式</h6>
                                    <div class="btn-group" role="group">
                                        <input type="radio" class="btn-check" name="scheduleMode" id="modeAuto" value="auto" checked>
                                        <label class="btn btn-outline-primary" for="modeAuto">自动模式</label>
                                        <input type="radio" class="btn-check" name="scheduleMode" id="modeManual" value="manual">
                                        <label class="btn btn-outline-secondary" for="modeManual">手动模式</label>
                                    </div>
                                </div>
                                <div class="col-md-6 text-end">
                                    <button class="btn btn-sm btn-outline-info" id="btnShowSettings">
                                        <i class="fa fa-cog"></i> 排班设置
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 排班设置 (默认隐藏) -->
                    <div class="card mb-3" id="scheduleSettingsCard" style="display: none;">
                        <div class="card-body">
                            <h6 class="mb-3">自动排班设置</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">工作日</label>
                                    <div class="d-flex gap-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="day1" value="1" checked>
                                            <label class="form-check-label" for="day1">周一</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="day2" value="2" checked>
                                            <label class="form-check-label" for="day2">周二</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="day3" value="3" checked>
                                            <label class="form-check-label" for="day3">周三</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="day4" value="4" checked>
                                            <label class="form-check-label" for="day4">周四</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="day5" value="5" checked>
                                            <label class="form-check-label" for="day5">周五</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="day6" value="6">
                                            <label class="form-check-label" for="day6">周六</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="day7" value="7">
                                            <label class="form-check-label" for="day7">周日</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">上午默认号源</label>
                                    <input type="number" class="form-control" id="defaultMorningSlots" value="20" min="1">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">下午默认号源</label>
                                    <input type="number" class="form-control" id="defaultAfternoonSlots" value="15" min="1">
                                </div>
                            </div>
                            <div class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" id="btnCancelSettings">取消</button>
                                <button class="btn btn-sm btn-primary" id="btnSaveSettings">
                                    <i class="fa fa-save"></i> 保存设置
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 日期选择和操作 -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <input type="date" class="form-control" id="startDate" value="">
                                        <span class="input-group-text">至</span>
                                        <input type="date" class="form-control" id="endDate" value="">
                                        <button class="btn btn-outline-primary" id="btnLoadSchedules">
                                            <i class="fa fa-refresh"></i> 加载
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6 text-end">
                                    <button class="btn btn-danger" id="btnBatchDeleteSchedules" style="display: none;">
                                        <i class="fa fa-trash"></i> 批量删除
                                    </button>
                                    <button class="btn btn-success" id="btnAddSchedule">
                                        <i class="fa fa-plus"></i> 添加排班
                                    </button>
                                    <button class="btn btn-warning" id="btnGenerateSchedules">
                                        <i class="fa fa-magic"></i> 自动生成
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 排班列表 -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="scheduleTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">
                                        <input type="checkbox" id="selectAllSchedules">
                                    </th>
                                    <th style="width: 120px;">日期</th>
                                    <th style="width: 100px;">星期</th>
                                    <th style="width: 100px;">时段</th>
                                    <th style="width: 120px;">开始时间</th>
                                    <th style="width: 120px;">结束时间</th>
                                    <th style="width: 100px;">总号源</th>
                                    <th style="width: 100px;">剩余号源</th>
                                    <th style="width: 150px;">操作</th>
                                </tr>
                            </thead>
                            <tbody id="scheduleTableBody">
                                <!-- 动态填充 -->
                            </tbody>
                        </table>
                    </div>
                    <div id="scheduleEmpty" class="text-center text-muted py-4" style="display: none;">
                        <i class="fa fa-calendar-o fa-3x mb-3"></i>
                        <p>暂无排班数据</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 编辑/添加排班模态框 -->
    <div class="modal fade" id="editScheduleModal" tabindex="-1" aria-labelledby="editScheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editScheduleModalLabel">编辑排班</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editScheduleForm">
                        <input type="hidden" id="editScheduleId" name="id" value="">
                        
                        <div class="mb-3">
                            <label class="form-label">日期</label>
                            <input type="date" class="form-control" id="editScheduleDate" name="date" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">时段</label>
                            <select class="form-select" id="editScheduleTimeSlot" name="time_slot" required>
                                <option value="上午">上午</option>
                                <option value="下午">下午</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">开始时间</label>
                                <input type="time" class="form-control" id="editScheduleStartTime" name="start_time">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">结束时间</label>
                                <input type="time" class="form-control" id="editScheduleEndTime" name="end_time">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">总号源</label>
                                <input type="number" class="form-control" id="editScheduleTotalQuantity" name="total_quantity" value="20" min="1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">剩余号源</label>
                                <input type="number" class="form-control" id="editScheduleRemainingQuantity" name="remaining_quantity" value="20" min="0" required>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" form="editScheduleForm" class="btn btn-primary">保存</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // 全局变量
        let scheduleModal;
        let editScheduleModal;
        let currentScheduleDoctorId = null;
        
        // 等待DOM加载完成
        document.addEventListener('DOMContentLoaded', function() {
            // 初始化模态框
            scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
            editScheduleModal = new bootstrap.Modal(document.getElementById('editScheduleModal'));
            // 添加医生模态框
            const addDoctorModal = new bootstrap.Modal(document.getElementById('addDoctorModal'));
            
            // 编辑医生模态框
            const editDoctorModal = new bootstrap.Modal(document.getElementById('editDoctorModal'));
            
            // 删除确认模态框
            const deleteDoctorModal = new bootstrap.Modal(document.getElementById('deleteDoctorModal'));
            
            // 当前编辑的医生ID
            let currentDoctorId = null;
            
            // 绑定科室和病种模态框
            const bindDoctorModal = new bootstrap.Modal(document.getElementById('bindDoctorModal'));
            
            // 头像上传处理
            const avatarPreview = document.getElementById('avatarPreview');
            const avatarInput = document.getElementById('avatar');
            const avatarImage = document.getElementById('avatarImage');
            const avatarPlaceholder = document.getElementById('avatarPlaceholder');
            
            if (avatarPreview && avatarInput) {
                // 点击预览区域打开文件选择
                avatarPreview.addEventListener('click', function() {
                    avatarInput.click();
                });
                
                // 选择文件后预览
                avatarInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        // 验证文件大小
                        if (file.size > 1 * 1024 * 1024) {
                            alert('图片大小不能超过1M');
                            return;
                        }
                        
                        // 验证文件类型
                        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                        if (!allowedTypes.includes(file.type)) {
                            alert('只支持JPG、PNG、GIF格式');
                            return;
                        }
                        
                        // 预览图片
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            avatarImage.src = e.target.result;
                            avatarImage.style.display = 'block';
                            avatarPlaceholder.style.display = 'none';
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            // 编辑医生模态框的头像上传处理
            const editAvatarPreview = document.getElementById('editAvatarPreview');
            const editAvatarInput = document.getElementById('editAvatar');
            const editAvatarImage = document.getElementById('editAvatarImage');
            const editAvatarPlaceholder = document.getElementById('editAvatarPlaceholder');
            
            if (editAvatarPreview && editAvatarInput) {
                // 点击预览区域打开文件选择
                editAvatarPreview.addEventListener('click', function() {
                    editAvatarInput.click();
                });
                
                // 选择文件后预览
                editAvatarInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        // 验证文件大小
                        if (file.size > 1 * 1024 * 1024) {
                            alert('图片大小不能超过1M');
                            return;
                        }
                        
                        // 验证文件类型
                        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                        if (!allowedTypes.includes(file.type)) {
                            alert('只支持JPG、PNG、GIF格式');
                            return;
                        }
                        
                        // 预览图片
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            editAvatarImage.src = e.target.result;
                            editAvatarImage.style.display = 'block';
                            editAvatarPlaceholder.style.display = 'none';
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            // 点击添加医生按钮
            const addButton = document.querySelector('.btn-add');
            if (addButton) {
                addButton.addEventListener('click', function() {
                    // 重置表单
                    document.getElementById('addDoctorForm').reset();
                    // 显示模态框
                    addDoctorModal.show();
                });
            }
            
            // 打开绑定科室和病种模态框
            window.openBindModal = function(doctorId) {
                // 设置医生ID
                document.getElementById('bindDoctorId').value = doctorId;
                
                // 加载科室列表
                loadDepartments(doctorId);
                
                // 加载病种列表
                loadDiseases(doctorId);
                
                // 显示模态框
                bindDoctorModal.show();
            };
            
            // 加载科室列表
            function loadDepartments(doctorId) {
                const departmentList = document.getElementById('departmentList');
                departmentList.innerHTML = '<div class="col-12"><p>加载中...</p></div>';
                
                // 发送请求获取科室列表
                fetch('../includes/doctor_manage.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=getDepartments&doctor_id=' + doctorId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const departments = data.data;
                        let html = '';
                        departments.forEach(dept => {
                            html += `
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="departments[]" value="${dept.id}" ${dept.checked ? 'checked' : ''}>
                                        <label class="form-check-label">${dept.name}</label>
                                    </div>
                                </div>
                            `;
                        });
                        departmentList.innerHTML = html;
                    } else {
                        departmentList.innerHTML = '<div class="col-12"><p>加载科室失败</p></div>';
                    }
                })
                .catch(error => {
                    departmentList.innerHTML = '<div class="col-12"><p>加载科室失败</p></div>';
                });
            }
            
            // 加载病种列表
            function loadDiseases(doctorId) {
                const diseaseList = document.getElementById('diseaseList');
                diseaseList.innerHTML = '<div class="col-12"><p>加载中...</p></div>';
                
                // 发送请求获取病种列表
                fetch('../includes/doctor_manage.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=getDiseases&doctor_id=' + doctorId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const diseases = data.data;
                        let html = '';
                        diseases.forEach(disease => {
                            html += `
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="diseases[]" value="${disease.id}" ${disease.checked ? 'checked' : ''}>
                                        <label class="form-check-label">${disease.name}</label>
                                    </div>
                                </div>
                            `;
                        });
                        diseaseList.innerHTML = html;
                    } else {
                        diseaseList.innerHTML = '<div class="col-12"><p>加载病种失败</p></div>';
                    }
                })
                .catch(error => {
                    diseaseList.innerHTML = '<div class="col-12"><p>加载病种失败</p></div>';
                });
            }
            
            // 点击编辑按钮
        const editButtons = document.querySelectorAll('table.table tbody tr .d-flex .btn-primary');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                // 获取医生行
                const row = this.closest('tr');
                // 获取医生ID
                currentDoctorId = row.querySelector('td:first-child').textContent;
                
                // 发送请求获取完整的医生信息
                fetch('../includes/doctor_manage.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=get&id=' + currentDoctorId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const doctor = data.data;
                        
                        // 填充表单
                        document.getElementById('editName').value = doctor.name;
                        document.getElementById('editTitle').value = doctor.title;
                        document.getElementById('editDepartment').value = doctor.department;
                        document.getElementById('editSpecialty').value = doctor.specialty;
                        document.getElementById('editDescription').value = doctor.description || '';
                        
                        // 显示当前头像
                        const editAvatarImage = document.getElementById('editAvatarImage');
                        const editAvatarPlaceholder = document.getElementById('editAvatarPlaceholder');
                        if (doctor.avatar) {
                            // 提取文件名，使用正确的路径
                            const avatarFilename = doctor.avatar.split('/').pop();
                            editAvatarImage.src = '../uploads/' + avatarFilename;
                            editAvatarImage.style.display = 'block';
                            editAvatarPlaceholder.style.display = 'none';
                        } else {
                            editAvatarImage.src = '';
                            editAvatarImage.style.display = 'none';
                            editAvatarPlaceholder.style.display = 'block';
                        }
                        document.getElementById('editAvatar').value = '';
                        
                        // 加载科室列表
                        loadEditDepartments(currentDoctorId);
                        
                        // 加载病种列表
                        loadEditDiseases(currentDoctorId);
                        
                        // 显示模态框
                        editDoctorModal.show();
                    } else {
                        alert('获取医生信息失败: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('获取医生信息失败: 网络错误');
                });
            });
        });
        
        // 加载编辑页面的科室列表
        function loadEditDepartments(doctorId) {
            const departmentList = document.getElementById('editDepartmentList');
            departmentList.innerHTML = '<div class="col-12"><p>加载中...</p></div>';
            
            // 发送请求获取科室列表
            fetch('../includes/doctor_manage.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=getDepartments&doctor_id=' + doctorId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const departments = data.data;
                    let html = '';
                    let boundDepartmentName = '';
                    
                    departments.forEach(dept => {
                        html += `
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="departments[]" value="${dept.id}" ${dept.checked ? 'checked' : ''}>
                                    <label class="form-check-label">${dept.name}</label>
                                </div>
                            </div>
                        `;
                        
                        // 记录第一个绑定的科室名称
                        if (dept.checked && !boundDepartmentName) {
                            boundDepartmentName = dept.name;
                        }
                    });
                    
                    departmentList.innerHTML = html;
                    
                    // 如果有绑定的科室，更新科室输入框
                    if (boundDepartmentName) {
                        document.getElementById('editDepartment').value = boundDepartmentName;
                    }
                    
                    // 为科室复选框添加点击事件
                    document.querySelectorAll('input[name="departments[]"]').forEach(checkbox => {
                        checkbox.addEventListener('change', function() {
                            // 获取所有选中的科室
                            const checkedDepartments = document.querySelectorAll('input[name="departments[]"]:checked');
                            
                            // 如果有选中的科室，更新输入框为第一个选中的科室名称
                            if (checkedDepartments.length > 0) {
                                const firstCheckedDepartment = checkedDepartments[0].nextElementSibling.textContent;
                                document.getElementById('editDepartment').value = firstCheckedDepartment;
                            }
                        });
                    });
                } else {
                    departmentList.innerHTML = '<div class="col-12"><p>加载科室失败</p></div>';
                }
            })
            .catch(error => {
                departmentList.innerHTML = '<div class="col-12"><p>加载科室失败</p></div>';
            });
        }
        
        // 加载编辑页面的病种列表
        function loadEditDiseases(doctorId) {
            const diseaseList = document.getElementById('editDiseaseList');
            diseaseList.innerHTML = '<div class="col-12"><p>加载中...</p></div>';
            
            // 发送请求获取病种列表
            fetch('../includes/doctor_manage.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=getDiseases&doctor_id=' + doctorId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const diseases = data.data;
                    let html = '';
                    diseases.forEach(disease => {
                        html += `
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="diseases[]" value="${disease.id}" ${disease.checked ? 'checked' : ''}>
                                    <label class="form-check-label">${disease.name}</label>
                                </div>
                            </div>
                        `;
                    });
                    diseaseList.innerHTML = html;
                } else {
                    diseaseList.innerHTML = '<div class="col-12"><p>加载病种失败</p></div>';
                }
            })
            .catch(error => {
                diseaseList.innerHTML = '<div class="col-12"><p>加载病种失败</p></div>';
            });
        }
            
            // 点击删除按钮
            const deleteButtons = document.querySelectorAll('table.table tbody tr .d-flex .btn-danger');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // 获取医生行
                    const row = this.closest('tr');
                    // 获取医生ID
                    currentDoctorId = row.querySelector('td:first-child').textContent;
                    // 获取医生名称
                    const doctorName = row.querySelector('td:nth-child(2) h6').textContent;
                    // 更新确认消息
                    document.getElementById('deleteDoctorName').textContent = doctorName;
                    
                    // 显示模态框
                    deleteDoctorModal.show();
                });
            });
            
            // 提交添加医生表单
            const addForm = document.getElementById('addDoctorForm');
            if (addForm) {
                addForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    // 获取表单数据
                    const formData = new FormData(this);
                    
                    // 发送AJAX请求
                    fetch('../includes/doctor_manage.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // 关闭模态框
                            addDoctorModal.hide();
                            // 刷新页面
                            location.reload();
                        } else {
                            alert('添加失败: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('添加失败: 网络错误');
                    });
                });
            }
            
            // 提交编辑医生表单
            const editForm = document.getElementById('editDoctorForm');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    // 获取表单数据
                    const formData = new FormData(this);
                    formData.append('id', currentDoctorId);
                    formData.append('action', 'edit');
                    
                    // 发送AJAX请求
                    fetch('../includes/doctor_manage.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // 保存绑定信息
                            const bindFormData = new FormData();
                            bindFormData.append('action', 'bind');
                            bindFormData.append('doctor_id', currentDoctorId);
                            
                            // 添加选中的科室
                            const departments = document.querySelectorAll('input[name="departments[]"]:checked');
                            departments.forEach(dept => {
                                bindFormData.append('departments[]', dept.value);
                            });
                            
                            // 添加选中的病种
                            const diseases = document.querySelectorAll('input[name="diseases[]"]:checked');
                            diseases.forEach(disease => {
                                bindFormData.append('diseases[]', disease.value);
                            });
                            
                            // 发送绑定请求
                            return fetch('../includes/doctor_manage.php', {
                                method: 'POST',
                                body: bindFormData
                            });
                        } else {
                            throw new Error(data.message);
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        // 关闭模态框
                        editDoctorModal.hide();
                        // 刷新页面
                        location.reload();
                    })
                    .catch(error => {
                        alert('编辑失败: ' + error.message);
                    });
                });
            }
            
            // 确认删除
            const confirmDeleteButton = document.getElementById('confirmDelete');
            if (confirmDeleteButton) {
                confirmDeleteButton.addEventListener('click', function() {
                    // 发送AJAX请求
                    fetch('../includes/doctor_manage.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'action=delete&id=' + currentDoctorId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // 关闭模态框
                            deleteDoctorModal.hide();
                            // 刷新页面
                            location.reload();
                        } else {
                            alert('删除失败: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('删除失败: 网络错误');
                    });
                });
            }
            
            // 提交绑定科室和病种表单
            const bindForm = document.getElementById('bindDoctorForm');
            if (bindForm) {
                bindForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    // 获取表单数据
                    const formData = new FormData(this);
                    
                    // 发送AJAX请求
                    fetch('../includes/doctor_manage.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // 关闭模态框
                            bindDoctorModal.hide();
                            // 显示成功消息
                            alert('绑定成功');
                        } else {
                            alert('绑定失败: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('绑定失败: 网络错误');
                    });
                });
            }
            
            // 排班管理相关代码
            
            // 打开排班管理模态框
            window.openScheduleModal = function(doctorId, doctorName) {
                currentScheduleDoctorId = doctorId;
                document.getElementById('scheduleDoctorId').value = doctorId;
                document.getElementById('scheduleDoctorName').textContent = doctorName;
                
                // 设置默认日期范围（未来30天）
                const today = new Date();
                const endDate = new Date();
                endDate.setDate(today.getDate() + 30);
                
                document.getElementById('startDate').value = formatDate(today);
                document.getElementById('endDate').value = formatDate(endDate);
                
                // 加载医生排班设置
                loadScheduleSettings(doctorId);
                
                // 加载排班数据
                loadSchedules(doctorId);
                
                // 显示模态框
                scheduleModal.show();
            };
            
            // 格式化日期
            function formatDate(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }
            
            // 获取星期几的中文表示
            function getWeekDay(dateString) {
                const date = new Date(dateString);
                const weekDays = ['周日', '周一', '周二', '周三', '周四', '周五', '周六'];
                return weekDays[date.getDay()];
            }
            
            // 加载排班设置
            function loadScheduleSettings(doctorId) {
                fetch('../includes/doctor_manage.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=getScheduleSettings&doctor_id=' + doctorId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const settings = data.data;
                        
                        // 设置排班模式
                        if (settings.schedule_mode === 'manual') {
                            document.getElementById('modeManual').checked = true;
                        } else {
                            document.getElementById('modeAuto').checked = true;
                        }
                        
                        // 设置工作日
                        for (let i = 1; i <= 7; i++) {
                            const checkbox = document.getElementById('day' + i);
                            if (checkbox) {
                                checkbox.checked = settings.work_days && settings.work_days.includes(String(i));
                            }
                        }
                        
                        // 设置默认号源
                        document.getElementById('defaultMorningSlots').value = settings.default_morning_slots || 20;
                        document.getElementById('defaultAfternoonSlots').value = settings.default_afternoon_slots || 15;
                    }
                })
                .catch(error => {
                    console.error('加载排班设置失败:', error);
                });
            }
            
            // 加载排班数据
            function loadSchedules(doctorId) {
                const startDate = document.getElementById('startDate').value;
                const endDate = document.getElementById('endDate').value;
                
                const tableBody = document.getElementById('scheduleTableBody');
                const emptyDiv = document.getElementById('scheduleEmpty');
                
                tableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4">加载中...</td></tr>';
                
                fetch('../includes/doctor_manage.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=getSchedules&doctor_id=${doctorId}&start_date=${startDate}&end_date=${endDate}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const schedules = data.data;
                        
                        if (schedules.length === 0) {
                            tableBody.innerHTML = '';
                            emptyDiv.style.display = 'block';
                        } else {
                            emptyDiv.style.display = 'none';
                            let html = '';
                            schedules.forEach(schedule => {
                                const isSuspended = schedule.is_suspended == 1;
                                const suspendBtnClass = isSuspended ? 'btn-warning' : 'btn-outline-warning';
                                const suspendBtnText = isSuspended ? '开诊' : '停诊';
                                const rowClass = isSuspended ? 'table-warning' : '';
                                
                                html += `
                                    <tr class="${rowClass}">
                                        <td><input type="checkbox" class="schedule-checkbox" value="${schedule.id}"></td>
                                        <td>${schedule.date}</td>
                                        <td>${getWeekDay(schedule.date)}</td>
                                        <td>
                                            ${isSuspended ? '<span class="badge bg-warning text-dark">停诊</span>' : ''}
                                            ${schedule.time_slot}
                                        </td>
                                        <td>${schedule.start_time || '-'}</td>
                                        <td>${schedule.end_time || '-'}</td>
                                        <td>${schedule.total_quantity}</td>
                                        <td>${schedule.remaining_quantity}</td>
                                        <td>
                                            <button class="btn btn-sm btn-primary me-1" onclick="editSchedule(${schedule.id})">
                                                <i class="fa fa-edit"></i> 编辑
                                            </button>
                                            <button class="btn btn-sm ${suspendBtnClass} me-1" onclick="toggleScheduleSuspend(${schedule.id})">
                                                <i class="fa ${isSuspended ? 'fa-check-circle' : 'fa-pause-circle'}"></i> ${suspendBtnText}
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteSchedule(${schedule.id})">
                                                <i class="fa fa-trash"></i> 删除
                                            </button>
                                        </td>
                                    </tr>
                                `;
                            });
                            tableBody.innerHTML = html;
                        }
                    } else {
                        tableBody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger">加载失败: ' + data.message + '</td></tr>';
                    }
                })
                .catch(error => {
                    tableBody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger">加载失败: 网络错误</td></tr>';
                });
            }
            
            // 显示/隐藏排班设置
            document.getElementById('btnShowSettings').addEventListener('click', function() {
                const card = document.getElementById('scheduleSettingsCard');
                card.style.display = card.style.display === 'none' ? 'block' : 'none';
            });
            
            // 取消排班设置
            document.getElementById('btnCancelSettings').addEventListener('click', function() {
                document.getElementById('scheduleSettingsCard').style.display = 'none';
                loadScheduleSettings(currentScheduleDoctorId);
            });
            
            // 保存排班设置
            document.getElementById('btnSaveSettings').addEventListener('click', function() {
                const workDays = [];
                for (let i = 1; i <= 7; i++) {
                    const checkbox = document.getElementById('day' + i);
                    if (checkbox && checkbox.checked) {
                        workDays.push(i);
                    }
                }
                
                const scheduleMode = document.querySelector('input[name="scheduleMode"]:checked').value;
                const defaultMorningSlots = document.getElementById('defaultMorningSlots').value;
                const defaultAfternoonSlots = document.getElementById('defaultAfternoonSlots').value;
                
                const formData = new FormData();
                formData.append('action', 'saveScheduleSettings');
                formData.append('doctor_id', currentScheduleDoctorId);
                formData.append('schedule_mode', scheduleMode);
                formData.append('work_days', workDays.join(','));
                formData.append('default_morning_slots', defaultMorningSlots);
                formData.append('default_afternoon_slots', defaultAfternoonSlots);
                
                fetch('../includes/doctor_manage.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('保存成功');
                        document.getElementById('scheduleSettingsCard').style.display = 'none';
                    } else {
                        alert('保存失败: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('保存失败: 网络错误');
                });
            });
            
            // 切换排班模式
            document.querySelectorAll('input[name="scheduleMode"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const formData = new FormData();
                    formData.append('action', 'updateScheduleMode');
                    formData.append('doctor_id', currentScheduleDoctorId);
                    formData.append('schedule_mode', this.value);
                    
                    fetch('../includes/doctor_manage.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            alert('切换模式失败: ' + data.message);
                            // 恢复原来的选择
                            loadScheduleSettings(currentScheduleDoctorId);
                        }
                    })
                    .catch(error => {
                        alert('切换模式失败: 网络错误');
                        loadScheduleSettings(currentScheduleDoctorId);
                    });
                });
            });
            
            // 全选复选框
            document.getElementById('selectAllSchedules').addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.schedule-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateBatchDeleteButton();
            });
            
            // 排班复选框点击事件
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('schedule-checkbox')) {
                    updateBatchDeleteButton();
                }
            });
            
            // 更新批量删除按钮显示
            function updateBatchDeleteButton() {
                const checkedBoxes = document.querySelectorAll('.schedule-checkbox:checked');
                const btnBatchDelete = document.getElementById('btnBatchDeleteSchedules');
                btnBatchDelete.style.display = checkedBoxes.length > 0 ? 'inline-block' : 'none';
                
                // 更新全选复选框状态
                const allBoxes = document.querySelectorAll('.schedule-checkbox');
                const selectAll = document.getElementById('selectAllSchedules');
                selectAll.checked = allBoxes.length > 0 && checkedBoxes.length === allBoxes.length;
            }
            
            // 批量删除按钮
            document.getElementById('btnBatchDeleteSchedules').addEventListener('click', function() {
                const checkedBoxes = document.querySelectorAll('.schedule-checkbox:checked');
                const ids = Array.from(checkedBoxes).map(cb => cb.value).join(',');
                
                if (!confirm(`确定要删除选中的 ${checkedBoxes.length} 条排班记录吗？`)) {
                    return;
                }
                
                fetch('../includes/doctor_manage.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=batchDeleteSchedules&ids=${ids}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('批量删除成功');
                        loadSchedules(currentScheduleDoctorId);
                    } else {
                        alert('批量删除失败: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('批量删除失败: 网络错误');
                });
            });
            
            // 加载排班按钮
            document.getElementById('btnLoadSchedules').addEventListener('click', function() {
                loadSchedules(currentScheduleDoctorId);
            });
            
            // 添加排班按钮
            document.getElementById('btnAddSchedule').addEventListener('click', function() {
                document.getElementById('editScheduleModalLabel').textContent = '添加排班';
                document.getElementById('editScheduleId').value = '';
                document.getElementById('editScheduleForm').reset();
                
                const today = new Date();
                document.getElementById('editScheduleDate').value = formatDate(today);
                document.getElementById('editScheduleStartTime').value = '08:00';
                document.getElementById('editScheduleEndTime').value = '12:00';
                document.getElementById('editScheduleTotalQuantity').value = 20;
                document.getElementById('editScheduleRemainingQuantity').value = 20;
                
                editScheduleModal.show();
            });
            
            // 自动生成排班
            document.getElementById('btnGenerateSchedules').addEventListener('click', function() {
                if (!confirm('确定要自动生成排班吗？这将在指定日期范围内生成排班。')) {
                    return;
                }
                
                const startDate = document.getElementById('startDate').value;
                const endDate = document.getElementById('endDate').value;
                
                const formData = new FormData();
                formData.append('action', 'generateAutoSchedules');
                formData.append('doctor_id', currentScheduleDoctorId);
                formData.append('start_date', startDate);
                formData.append('end_date', endDate);
                
                fetch('../includes/doctor_manage.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('生成成功，共生成 ' + data.data.count + ' 条排班');
                        loadSchedules(currentScheduleDoctorId);
                    } else {
                        alert('生成失败: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('生成失败: 网络错误');
                });
            });
            
            // 编辑排班
            window.editSchedule = function(scheduleId) {
                document.getElementById('editScheduleModalLabel').textContent = '编辑排班';
                document.getElementById('editScheduleId').value = scheduleId;
                
                fetch('../includes/doctor_manage.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=getSchedule&id=' + scheduleId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const schedule = data.data;
                        document.getElementById('editScheduleDate').value = schedule.date;
                        document.getElementById('editScheduleTimeSlot').value = schedule.time_slot;
                        document.getElementById('editScheduleStartTime').value = schedule.start_time || '';
                        document.getElementById('editScheduleEndTime').value = schedule.end_time || '';
                        document.getElementById('editScheduleTotalQuantity').value = schedule.total_quantity;
                        document.getElementById('editScheduleRemainingQuantity').value = schedule.remaining_quantity;
                        
                        editScheduleModal.show();
                    } else {
                        alert('获取排班信息失败: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('获取排班信息失败: 网络错误');
                });
            };
            
            // 删除排班
            window.deleteSchedule = function(scheduleId) {
                if (!confirm('确定要删除这条排班吗？')) {
                    return;
                }
                
                fetch('../includes/doctor_manage.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=deleteSchedule&id=' + scheduleId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('删除成功');
                        loadSchedules(currentScheduleDoctorId);
                    } else {
                        alert('删除失败: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('删除失败: 网络错误');
                });
            };
            
            // 切换排班停诊状态
            window.toggleScheduleSuspend = function(scheduleId) {
                fetch('../includes/doctor_manage.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=toggleScheduleSuspend&schedule_id=' + scheduleId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const newStatus = data.data.is_suspended;
                        alert(newStatus ? '已停诊' : '已开诊');
                        loadSchedules(currentScheduleDoctorId);
                    } else {
                        alert('操作失败: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('操作失败: 网络错误');
                });
            };
            
            // 提交编辑/添加排班表单
            const editScheduleForm = document.getElementById('editScheduleForm');
            if (editScheduleForm) {
                editScheduleForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const scheduleId = document.getElementById('editScheduleId').value;
                    const formData = new FormData(this);
                    formData.append('action', 'saveSchedule');
                    formData.append('doctor_id', currentScheduleDoctorId);
                    if (scheduleId) {
                        formData.append('id', scheduleId);
                    }
                    
                    fetch('../includes/doctor_manage.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            editScheduleModal.hide();
                            alert('保存成功');
                            loadSchedules(currentScheduleDoctorId);
                        } else {
                            alert('保存失败: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('保存失败: 网络错误');
                    });
                });
            }
        });
    </script>

<?php
// 包含底部模板
require_once __DIR__ . '/../includes/footer.php';
?>