<?php
// 病种管理页面
// 设置浏览器缓存控制
header('Cache-Control: max-age=86400, public');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

require_once __DIR__ . '/../includes/config.php';

// 页面标题
$pageTitle = '病种管理';
// 当前活动页面
$activePage = 'diseases';

$pdo = getAdminDB();

if ($pdo === null) {
    die('数据库连接失败，请检查配置');
}

// 检查并创建必要的表
function createTables($pdo) {
    try {
        // 创建科室表
        $createDepartmentTable = "CREATE TABLE IF NOT EXISTS departments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $pdo->exec($createDepartmentTable);
        
        // 创建病种表
        $createDiseaseTable = "CREATE TABLE IF NOT EXISTS diseases (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            department_id INT,
            description TEXT,
            is_recommended TINYINT(1) DEFAULT 0,
            icon VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
        )";
        $pdo->exec($createDiseaseTable);
        
        // 如果表已存在，添加is_recommended字段
        try {
            $pdo->exec("ALTER TABLE diseases ADD COLUMN IF NOT EXISTS is_recommended TINYINT(1) DEFAULT 0");
        } catch (PDOException $e) {
            // 忽略错误，继续执行
        }
        
        // 如果表已存在，添加icon字段
        try {
            $pdo->exec("ALTER TABLE diseases ADD COLUMN IF NOT EXISTS icon VARCHAR(255)");
        } catch (PDOException $e) {
            // 忽略错误，继续执行
        }
        
        // 创建医生-科室关联表
        $createDoctorDepartmentTable = "CREATE TABLE IF NOT EXISTS doctor_departments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            doctor_id INT NOT NULL,
            department_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
            UNIQUE KEY (doctor_id, department_id)
        )";
        $pdo->exec($createDoctorDepartmentTable);
        
        // 创建医生-病种关联表
        $createDoctorDiseaseTable = "CREATE TABLE IF NOT EXISTS doctor_diseases (
            id INT PRIMARY KEY AUTO_INCREMENT,
            doctor_id INT NOT NULL,
            disease_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
            FOREIGN KEY (disease_id) REFERENCES diseases(id) ON DELETE CASCADE,
            UNIQUE KEY (doctor_id, disease_id)
        )";
        $pdo->exec($createDoctorDiseaseTable);
    } catch (PDOException $e) {
        // 忽略错误，继续执行
    }
}

// 处理图标上传
function handleIconUpload() {
    $file = $_FILES['icon'];
    
    // 检查文件类型
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('只支持JPG、PNG、GIF格式的图片');
    }
    
    // 检查文件大小（1M限制）
    if ($file['size'] > 1 * 1024 * 1024) {
        throw new Exception('图片大小不能超过1M');
    }
    
    // 生成唯一文件名
    $fileName = uniqid('icon_') . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    
    // 确保上传目录存在
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // 移动文件到上传目录
    $filePath = $uploadDir . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('文件上传失败');
    }
    
    // 返回相对路径，用于存储到数据库
    return 'uploads/' . $fileName;
}

// 调用函数创建表
createTables($pdo);

// 确保 is_recommended 列存在
try {
    // 检查列是否存在
    $stmt = $pdo->query("SHOW COLUMNS FROM diseases LIKE 'is_recommended'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // 添加列
        $pdo->exec("ALTER TABLE diseases ADD COLUMN is_recommended TINYINT(1) DEFAULT 0");
    }
} catch (PDOException $e) {
    // 忽略错误，继续执行
}

// 确保 icon 列存在
try {
    // 检查列是否存在
    $stmt = $pdo->query("SHOW COLUMNS FROM diseases LIKE 'icon'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // 添加列
        $pdo->exec("ALTER TABLE diseases ADD COLUMN icon VARCHAR(255)");
    }
} catch (PDOException $e) {
    // 忽略错误，继续执行
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // 处理文件上传
                $icon = '';
                if (isset($_FILES['icon']) && $_FILES['icon']['error'] === UPLOAD_ERR_OK) {
                    $icon = handleIconUpload();
                }
                
                // 添加病种
                $name = $_POST['name'];
                $department_id = $_POST['department_id'];
                $description = $_POST['description'];
                $is_recommended = isset($_POST['is_recommended']) ? 1 : 0;
                
                try {
                    // 尝试使用 icon 列
                    $stmt = $pdo->prepare("INSERT INTO diseases (name, department_id, description, is_recommended, icon) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $department_id, $description, $is_recommended, $icon]);
                } catch (PDOException $e) {
                    // 如果 icon 列不存在，使用不含 icon 的语句
                    $stmt = $pdo->prepare("INSERT INTO diseases (name, department_id, description, is_recommended) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $department_id, $description, $is_recommended]);
                }
                break;
                
            case 'edit':
                // 处理文件上传
                $icon = null;
                if (isset($_FILES['icon']) && $_FILES['icon']['error'] === UPLOAD_ERR_OK) {
                    $icon = handleIconUpload();
                }
                
                // 编辑病种
                $id = $_POST['id'];
                $name = $_POST['name'];
                $department_id = $_POST['department_id'];
                $description = $_POST['description'];
                $is_recommended = isset($_POST['is_recommended']) ? 1 : 0;
                
                if ($icon) {
                    try {
                        // 获取旧图标路径
                        $stmt = $pdo->prepare("SELECT icon FROM diseases WHERE id = ?");
                        $stmt->execute([$id]);
                        $oldIcon = $stmt->fetchColumn();
                        
                        // 删除旧图标
                        if ($oldIcon && file_exists(__DIR__ . '/../' . $oldIcon)) {
                            unlink(__DIR__ . '/../' . $oldIcon);
                        }
                        
                        // 尝试使用 icon 列
                        $stmt = $pdo->prepare("UPDATE diseases SET name = ?, department_id = ?, description = ?, is_recommended = ?, icon = ? WHERE id = ?");
                        $stmt->execute([$name, $department_id, $description, $is_recommended, $icon, $id]);
                    } catch (PDOException $e) {
                        // 如果 icon 列不存在，使用不含 icon 的语句
                        $stmt = $pdo->prepare("UPDATE diseases SET name = ?, department_id = ?, description = ?, is_recommended = ? WHERE id = ?");
                        $stmt->execute([$name, $department_id, $description, $is_recommended, $id]);
                    }
                } else {
                    $stmt = $pdo->prepare("UPDATE diseases SET name = ?, department_id = ?, description = ?, is_recommended = ? WHERE id = ?");
                    $stmt->execute([$name, $department_id, $description, $is_recommended, $id]);
                }
                break;
                
            case 'delete':
                // 删除病种
                $id = $_POST['id'];
                
                $stmt = $pdo->prepare("DELETE FROM diseases WHERE id = ?");
                $stmt->execute([$id]);
                break;
                
            case 'batch_delete':
                // 批量删除病种
                $ids = $_POST['ids'];
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stmt = $pdo->prepare("DELETE FROM diseases WHERE id IN ($placeholders)");
                    $stmt->execute($ids);
                }
                break;
                
            case 'update_recommended':
                // 更新优先推荐状态
                $id = $_POST['id'];
                $is_recommended = $_POST['is_recommended'];
                
                $stmt = $pdo->prepare("UPDATE diseases SET is_recommended = ? WHERE id = ?");
                $stmt->execute([$is_recommended, $id]);
                break;
        }
    }
}

// 获取科室列表
$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();

// 分页配置
$pageSize = 8; // 每页8条数据
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage); // 确保页码至少为1

// 获取总数据量
try {
    $totalStmt = $pdo->query("SELECT COUNT(*) FROM diseases");
    $totalCount = (int)$totalStmt->fetchColumn();
} catch (PDOException $e) {
    $totalCount = 0;
}

// 计算总页数
$totalPages = ceil($totalCount / $pageSize);
$totalPages = max(1, $totalPages); // 确保至少有1页

// 计算偏移量
$offset = ($currentPage - 1) * $pageSize;

// 获取病种列表（带分页）
try {
    // 尝试使用 is_recommended 和 icon 列
    $stmt = $pdo->prepare("SELECT d.*, de.name as department_name FROM diseases d LEFT JOIN departments de ON d.department_id = de.id ORDER BY d.is_recommended DESC, d.created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $diseases = $stmt->fetchAll();
} catch (PDOException $e) {
    try {
        // 尝试使用 is_recommended 列（不含 icon）
        $stmt = $pdo->prepare("SELECT d.*, de.name as department_name FROM diseases d LEFT JOIN departments de ON d.department_id = de.id ORDER BY d.is_recommended DESC, d.created_at DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $diseases = $stmt->fetchAll();
    } catch (PDOException $e) {
        // 如果 is_recommended 列也不存在，使用默认排序
        $stmt = $pdo->prepare("SELECT d.*, de.name as department_name FROM diseases d LEFT JOIN departments de ON d.department_id = de.id ORDER BY d.created_at DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $diseases = $stmt->fetchAll();
    }
}

// 包含头部模板
require_once __DIR__ . '/../includes/header.php';

// 页面特定样式
?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center bg-white border-bottom">
                        <h5 class="card-title mb-0">病种列表</h5>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary btn-sm" onclick="openAddModal()">
                                <i class="fa fa-plus me-1"></i> 添加病种
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="batchDelete()" id="batchDeleteBtn" disabled>
                                <i class="fa fa-trash me-1"></i> 批量删除
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th><input type="checkbox" id="selectAll"></th>
                                        <th>ID</th>
                                        <th>图标</th>
                                        <th>病种名称</th>
                                        <th>所属科室</th>
                                        <th>描述</th>
                                        <th>优先推荐</th>
                                        <th>创建时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($diseases as $disease): ?>
                                        <tr>
                                            <td><input type="checkbox" class="disease-checkbox" value="<?php echo $disease['id']; ?>"></td>
                                            <td><?php echo $disease['id']; ?></td>
                                            <td><?php echo isset($disease['icon']) ? '<img src="../' . $disease['icon'] . '" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">' : '-'; ?></td>
                                            <td><?php echo $disease['name']; ?></td>
                                            <td><?php echo $disease['department_name']; ?></td>
                                            <td><?php echo $disease['description']; ?></td>
                                            <td>
                                                <input type="checkbox" class="recommended-checkbox" data-id="<?php echo $disease['id']; ?>" <?php echo isset($disease['is_recommended']) && $disease['is_recommended'] ? 'checked' : ''; ?>>
                                            </td>
                                            <td><?php echo $disease['created_at']; ?></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <button class="btn btn-primary btn-sm" onclick="openEditModal(<?php echo $disease['id']; ?>, '<?php echo addslashes($disease['name']); ?>', <?php echo $disease['department_id']; ?>, '<?php echo addslashes($disease['description']); ?>', <?php echo isset($disease['is_recommended']) ? $disease['is_recommended'] : 0; ?>, '<?php echo isset($disease['icon']) ? addslashes($disease['icon']) : ''; ?>')"><i class="fa fa-edit me-1"></i> 编辑</button>
                                                    <button class="btn btn-danger btn-sm" onclick="deleteDisease(<?php echo $disease['id']; ?>)"><i class="fa fa-trash me-1"></i> 删除</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- 分页控件 -->
                    <div class="card-footer bg-white border-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted">
                                共 <?php echo $totalCount; ?> 条数据
                            </div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination mb-0">
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
                            <div class="text-muted">
                                当前第 <?php echo $currentPage; ?>/<?php echo $totalPages; ?> 页
                            </div>
                        </div>
                    </div>
                </div>

    <!-- 添加病种模态框 -->
    <div id="addModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="addModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addModalLabel">添加病种</h5>
                    <button type="button" class="btn-close" onclick="closeAddModal()"></button>
                </div>
                <div class="modal-body">
                    <form id="addForm" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="name" class="form-label">病种名称</label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="department_id" class="form-label">所属科室</label>
                            <select id="department_id" name="department_id" class="form-select">
                                <option value="">请选择科室</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo $department['id']; ?>"><?php echo $department['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">描述</label>
                            <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" id="is_recommended" name="is_recommended" class="form-check-input">
                            <label class="form-check-label" for="is_recommended">优先推荐</label>
                        </div>
                        <div class="mb-3">
                            <label for="icon" class="form-label">病种图标</label>
                            <input type="file" id="icon" name="icon" class="form-control" accept="image/jpeg,image/png,image/gif">
                            <p class="text-muted text-sm mt-1">支持JPG、PNG、GIF格式，大小不超过1M</p>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">取消</button>
                    <button type="submit" form="addForm" class="btn btn-primary">保存</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 编辑病种模态框 -->
    <div id="editModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">编辑病种</h5>
                    <button type="button" class="btn-close" onclick="closeEditModal()"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" id="editId" name="id">
                        <div class="mb-3">
                            <label for="editName" class="form-label">病种名称</label>
                            <input type="text" id="editName" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="editDepartmentId" class="form-label">所属科室</label>
                            <select id="editDepartmentId" name="department_id" class="form-select">
                                <option value="">请选择科室</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo $department['id']; ?>"><?php echo $department['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editDescription" class="form-label">描述</label>
                            <textarea id="editDescription" name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" id="editIsRecommended" name="is_recommended" class="form-check-input">
                            <label class="form-check-label" for="editIsRecommended">优先推荐</label>
                        </div>
                        <div class="mb-3">
                            <label for="editIcon" class="form-label">病种图标</label>
                            <input type="file" id="editIcon" name="icon" class="form-control" accept="image/jpeg,image/png,image/gif">
                            <p class="text-muted text-sm mt-1">支持JPG、PNG、GIF格式，大小不超过1M</p>
                            <div id="editIconPreview" class="mt-3"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">取消</button>
                    <button type="submit" form="editForm" class="btn btn-primary">保存</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // 全选/取消全选
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.disease-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBatchDeleteBtn();
        });
        
        // 单个 checkbox 变化时更新批量删除按钮状态
        document.querySelectorAll('.disease-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateBatchDeleteBtn);
        });
        
        // 更新批量删除按钮状态
        function updateBatchDeleteBtn() {
            const checkedBoxes = document.querySelectorAll('.disease-checkbox:checked');
            document.getElementById('batchDeleteBtn').disabled = checkedBoxes.length === 0;
        }
        
        // 打开添加模态框
        function openAddModal() {
            const addModal = new bootstrap.Modal(document.getElementById('addModal'));
            addModal.show();
        }
        
        // 关闭添加模态框
        function closeAddModal() {
            const addModal = bootstrap.Modal.getInstance(document.getElementById('addModal'));
            if (addModal) {
                addModal.hide();
            }
        }
        
        // 打开编辑模态框
        function openEditModal(id, name, departmentId, description, isRecommended, icon) {
            document.getElementById('editId').value = id;
            document.getElementById('editName').value = name;
            document.getElementById('editDepartmentId').value = departmentId;
            document.getElementById('editDescription').value = description;
            document.getElementById('editIsRecommended').checked = isRecommended === 1;
            
            // 显示当前图标
            const editIconPreview = document.getElementById('editIconPreview');
            if (icon) {
                editIconPreview.innerHTML = `<img src="../${icon}" style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px;">`;
            } else {
                editIconPreview.innerHTML = '<p class="text-muted">暂无图标</p>';
            }
            
            const editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }
        
        // 关闭编辑模态框
        function closeEditModal() {
            const editModal = bootstrap.Modal.getInstance(document.getElementById('editModal'));
            if (editModal) {
                editModal.hide();
            }
        }
        
        // 删除病种
        function deleteDisease(id) {
            if (confirm('确定要删除这个病种吗？')) {
                const form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // 批量删除
        function batchDelete() {
            const checkedBoxes = document.querySelectorAll('.disease-checkbox:checked');
            const ids = Array.from(checkedBoxes).map(checkbox => checkbox.value);
            
            if (ids.length > 0 && confirm('确定要删除选中的病种吗？')) {
                const form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = `<input type="hidden" name="action" value="batch_delete">`;
                ids.forEach(id => {
                    form.innerHTML += `<input type="hidden" name="ids[]" value="${id}">`;
                });
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // 优先推荐复选框点击事件
        document.querySelectorAll('.recommended-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const diseaseId = this.dataset.id;
                const isRecommended = this.checked ? 1 : 0;
                
                // 发送请求更新优先推荐状态
                const form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_recommended">
                    <input type="hidden" name="id" value="${diseaseId}">
                    <input type="hidden" name="is_recommended" value="${isRecommended}">
                `;
                document.body.appendChild(form);
                form.submit();
            });
        });
    </script>

<?php
// 包含底部模板
require_once __DIR__ . '/../includes/footer.php';
?>