<?php
// 科室管理页面
// 设置浏览器缓存控制
header('Cache-Control: max-age=86400, public');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

require_once __DIR__ . '/../includes/config.php';

// 页面标题
$pageTitle = '科室管理';
// 当前活动页面
$activePage = 'departments';

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
            is_recommended TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $pdo->exec($createDepartmentTable);
        
        // 如果表已存在，添加is_recommended字段
        try {
            $pdo->exec("ALTER TABLE departments ADD COLUMN IF NOT EXISTS is_recommended TINYINT(1) DEFAULT 0");
        } catch (PDOException $e) {
            // 忽略错误，继续执行
        }
        
        // 创建病种表
        $createDiseaseTable = "CREATE TABLE IF NOT EXISTS diseases (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            department_id INT,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
        )";
        $pdo->exec($createDiseaseTable);
        
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

// 调用函数创建表
createTables($pdo);

// 确保is_recommended列存在
try {
    // 检查列是否存在
    $stmt = $pdo->query("SHOW COLUMNS FROM departments LIKE 'is_recommended'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // 添加列
        $pdo->exec("ALTER TABLE departments ADD COLUMN is_recommended TINYINT(1) DEFAULT 0");
    }
} catch (PDOException $e) {
    // 忽略错误，继续执行
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // 添加科室
                $name = $_POST['name'];
                $description = $_POST['description'];
                $is_recommended = isset($_POST['is_recommended']) ? 1 : 0;
                
                try {
                    // 尝试使用is_recommended列和hospital_id
                    $stmt = $pdo->prepare("INSERT INTO departments (name, description, is_recommended, hospital_id) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $description, $is_recommended, 1]);
                } catch (PDOException $e) {
                    // 如果is_recommended列或hospital_id不存在，使用不含这些列的语句
                    $stmt = $pdo->prepare("INSERT INTO departments (name, description, hospital_id) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $description, 1]);
                }
                break;
                
            case 'edit':
                // 编辑科室
                $id = $_POST['id'];
                $name = $_POST['name'];
                $description = $_POST['description'];
                $is_recommended = isset($_POST['is_recommended']) ? 1 : 0;
                
                try {
                    // 尝试使用is_recommended列和hospital_id
                    $stmt = $pdo->prepare("UPDATE departments SET name = ?, description = ?, is_recommended = ?, hospital_id = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $is_recommended, 1, $id]);
                } catch (PDOException $e) {
                    // 如果is_recommended列或hospital_id不存在，使用不含这些列的语句
                    $stmt = $pdo->prepare("UPDATE departments SET name = ?, description = ?, hospital_id = ? WHERE id = ?");
                    $stmt->execute([$name, $description, 1, $id]);
                }
                break;
                
            case 'update_recommended':
                // 更新优先推荐状态
                $id = $_POST['id'];
                $is_recommended = $_POST['is_recommended'];
                
                try {
                    // 尝试更新is_recommended列和hospital_id
                    $stmt = $pdo->prepare("UPDATE departments SET is_recommended = ?, hospital_id = ? WHERE id = ?");
                    $stmt->execute([$is_recommended, 1, $id]);
                } catch (PDOException $e) {
                    // 如果is_recommended列或hospital_id不存在，忽略错误
                }
                break;
                
            case 'delete':
                // 删除科室
                $id = $_POST['id'];
                
                $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
                $stmt->execute([$id]);
                break;
                
            case 'batch_delete':
                // 批量删除科室
                $ids = $_POST['ids'];
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stmt = $pdo->prepare("DELETE FROM departments WHERE id IN ($placeholders)");
                    $stmt->execute($ids);
                }
                break;
        }
    }
}

// 获取科室列表
try {
    // 尝试使用is_recommended列排序
    $departments = $pdo->query("SELECT * FROM departments ORDER BY is_recommended DESC, created_at DESC")->fetchAll();
} catch (PDOException $e) {
    // 如果is_recommended列不存在，使用默认排序
    $departments = $pdo->query("SELECT * FROM departments ORDER BY created_at DESC")->fetchAll();
}

// 包含头部模板
require_once __DIR__ . '/../includes/header.php';

// 页面特定样式
?>

<style>
    /* 模态框样式 */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.4);
    }
    
    .modal-content {
        background-color: #fefefe;
        margin: 15% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 90%;
        max-width: 500px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    
    .modal-content h2 {
        margin-top: 0;
        margin-bottom: 20px;
        color: #333;
        font-size: 1.25rem;
        font-weight: 600;
    }
    
    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    
    .close:hover,
    .close:focus {
        color: black;
        text-decoration: none;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        color: #555;
    }
    
    .form-group input[type="text"],
    .form-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 14px;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    
    .form-group input[type="text"]:focus,
    .form-group textarea:focus {
        border-color: #80bdff;
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
    }
    
    .form-group textarea {
        resize: vertical;
        min-height: 100px;
    }
    
    .form-group input[type="checkbox"] {
        margin-right: 5px;
    }
    
    .form-group .btn {
        padding: 6px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        background-color: #f8f9fa;
        color: #333;
        cursor: pointer;
        font-size: 14px;
        margin-right: 10px;
    }
    
    .form-group .btn-success {
        background-color: #28a745;
        border-color: #28a745;
        color: white;
    }
    
    .form-group .btn:hover {
        background-color: #e2e6ea;
        border-color: #dae0e5;
    }
    
    .form-group .btn-success:hover {
        background-color: #218838;
        border-color: #1e7e34;
    }
</style>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title">科室列表</h5>
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm" onclick="openAddModal()">
                <i class="fa fa-plus"></i> 添加科室
            </button>
            <button class="btn btn-danger btn-sm" onclick="batchDelete()" id="batchDeleteBtn" disabled>
                <i class="fa fa-trash"></i> 批量删除
            </button>
        </div>
    </div>
    <div class="card-body">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>ID</th>
                    <th>科室名称</th>
                    <th>描述</th>
                    <th>优先推荐</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($departments as $department): ?>
                    <tr>
                        <td><input type="checkbox" class="department-checkbox" value="<?php echo $department['id']; ?>"></td>
                        <td><?php echo $department['id']; ?></td>
                        <td><?php echo $department['name']; ?></td>
                        <td><?php echo $department['description']; ?></td>
                        <td>
                            <input type="checkbox" class="recommended-checkbox" data-id="<?php echo $department['id']; ?>" <?php echo isset($department['is_recommended']) && $department['is_recommended'] ? 'checked' : ''; ?>>
                        </td>
                        <td><?php echo $department['created_at']; ?></td>
                        <td>
                            <div class="d-flex gap-2">
                                <button class="btn btn-primary btn-sm" onclick="openEditModal(<?php echo $department['id']; ?>, '<?php echo addslashes($department['name']); ?>', '<?php echo addslashes($department['description']); ?>', <?php echo isset($department['is_recommended']) ? $department['is_recommended'] : 0; ?>)"><i class="fa fa-edit"></i> 编辑</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteDepartment(<?php echo $department['id']; ?>)"><i class="fa fa-trash"></i> 删除</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 添加科室模态框 -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddModal()">&times;</span>
            <h2>添加科室</h2>
            <form id="addForm" method="post">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label for="name">科室名称</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="description">描述</label>
                    <textarea id="description" name="description"></textarea>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="is_recommended" name="is_recommended"> 优先推荐
                    </label>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-success">保存</button>
                    <button type="button" class="btn" onclick="closeAddModal()">取消</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 编辑科室模态框 -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>编辑科室</h2>
            <form id="editForm" method="post">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="editId" name="id">
                <div class="form-group">
                    <label for="editName">科室名称</label>
                    <input type="text" id="editName" name="name" required>
                </div>
                <div class="form-group">
                    <label for="editDescription">描述</label>
                    <textarea id="editDescription" name="description"></textarea>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="editIsRecommended" name="is_recommended"> 优先推荐
                    </label>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-success">保存</button>
                    <button type="button" class="btn" onclick="closeEditModal()">取消</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // 全选/取消全选
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.department-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBatchDeleteBtn();
        });
        
        // 单个 checkbox 变化时更新批量删除按钮状态
        document.querySelectorAll('.department-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateBatchDeleteBtn);
        });
        
        // 更新批量删除按钮状态
        function updateBatchDeleteBtn() {
            const checkedBoxes = document.querySelectorAll('.department-checkbox:checked');
            document.getElementById('batchDeleteBtn').disabled = checkedBoxes.length === 0;
        }
        
        // 打开添加模态框
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }
        
        // 关闭添加模态框
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        // 打开编辑模态框
        function openEditModal(id, name, description, isRecommended) {
            document.getElementById('editId').value = id;
            document.getElementById('editName').value = name;
            document.getElementById('editDescription').value = description;
            document.getElementById('editIsRecommended').checked = isRecommended === 1;
            document.getElementById('editModal').style.display = 'block';
        }
        
        // 关闭编辑模态框
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // 删除科室
        function deleteDepartment(id) {
            if (confirm('确定要删除这个科室吗？')) {
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
            const checkedBoxes = document.querySelectorAll('.department-checkbox:checked');
            const ids = Array.from(checkedBoxes).map(checkbox => checkbox.value);
            
            if (ids.length > 0 && confirm('确定要删除选中的科室吗？')) {
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
        
        // 点击模态框外部关闭
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            if (event.target === addModal) {
                addModal.style.display = 'none';
            } else if (event.target === editModal) {
                editModal.style.display = 'none';
            }
        }
        
        // 优先推荐复选框点击事件
        document.querySelectorAll('.recommended-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const departmentId = this.dataset.id;
                const isRecommended = this.checked ? 1 : 0;
                
                // 发送请求更新优先推荐状态
                const form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_recommended">
                    <input type="hidden" name="id" value="${departmentId}">
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