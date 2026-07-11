<?php
// 管理后台用户管理页面
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
        echo '<th>ID</th>';
        echo '<th>昵称</th>';
        echo '<th>手机号</th>';
        echo '<th>真实姓名</th>';
        echo '<th>身份证号</th>';
        echo '<th>注册时间</th>';
        echo '</tr>';
        
        // 输出数据行
        foreach ($data as $row) {
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . $row['nickname'] . '</td>';
            echo '<td>' . ($row['phone'] ?: '-') . '</td>';
            echo '<td>' . ($row['real_name'] ?: '-') . '</td>';
            echo '<td>' . ($row['id_card'] ?: '-') . '</td>';
            echo '<td>' . $row['created_at'] . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        
        // 输出缓冲区内容
        ob_end_flush();
        exit;
    }
    
    // 获取所有用户数据
    $db = getAdminDB();
    $stmt = $db->query('SELECT * FROM users ORDER BY created_at DESC');
    $users = $stmt->fetchAll();
    
    // 导出Excel
    exportToExcel($users, 'users_' . date('YmdHis'));
    exit;
}

// 页面标题
$pageTitle = '用户管理';
// 当前活动页面
$activePage = 'users';

$db = getAdminDB();

if ($db === null) {
    die('数据库连接失败，请检查配置');
}

// 分页配置
$pageSize = 20; // 每页20条数据
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage); // 确保页码至少为1

// 获取总数据量
$totalStmt = $db->query('SELECT COUNT(*) FROM users');
$totalCount = (int)$totalStmt->fetchColumn();

// 计算总页数
$totalPages = ceil($totalCount / $pageSize);
$totalPages = max(1, $totalPages); // 确保至少有1页

// 计算偏移量
$offset = ($currentPage - 1) * $pageSize;

// 获取用户列表（带分页）
$stmt = $db->prepare('SELECT * FROM users ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
$stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

// 包含头部模板
require_once __DIR__ . '/../includes/header.php';

// 页面特定样式
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">用户列表</h5>
        <div class="d-flex gap-2">
            <a href="?action=export" class="btn btn-success btn-sm">
                <i class="fa fa-download"></i> 导出Excel
            </a>
        </div>
    </div>
    <div class="card-body">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>头像</th>
                    <th>昵称</th>
                    <th>手机号</th>
                    <th>真实姓名</th>
                    <th>注册时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td>
                        <?php if (!empty($user['avatar']) && strpos($user['avatar'], 'api.dicebear.com') === false): ?>
                            <img src="<?php echo $user['avatar']; ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;" alt="用户头像">
                        <?php else: ?>
                            <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #FF69B4 0%, #FF1493 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">👤</div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $user['nickname']; ?></td>
                    <td><?php echo $user['phone'] ?: '-'; ?></td>
                    <td><?php echo $user['real_name'] ?: '-'; ?></td>
                    <td><?php echo $user['created_at']; ?></td>
                    <td>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary btn-sm" data-id="<?php echo $user['id']; ?>" data-nickname="<?php echo $user['nickname']; ?>" data-phone="<?php echo $user['phone']; ?>" data-real-name="<?php echo $user['real_name']; ?>" data-id-card="<?php echo $user['id_card']; ?>" data-avatar="<?php echo $user['avatar']; ?>"><i class="fa fa-edit"></i> 编辑</button>
                            <button class="btn btn-danger btn-sm" data-id="<?php echo $user['id']; ?>"><i class="fa fa-trash"></i> 删除</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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

<!-- 编辑用户模态框 -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">编辑用户</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm">
                        <input type="hidden" id="userId" name="id">
                        <div class="mb-3">
                            <label for="userNickname" class="form-label">昵称</label>
                            <input type="text" class="form-control" id="userNickname" name="nickname">
                        </div>
                        <div class="mb-3">
                            <label for="userPhone" class="form-label">手机号</label>
                            <input type="text" class="form-control" id="userPhone" name="phone">
                        </div>
                        <div class="mb-3">
                            <label for="userRealName" class="form-label">真实姓名</label>
                            <input type="text" class="form-control" id="userRealName" name="real_name">
                        </div>
                        <div class="mb-3">
                            <label for="userIdCard" class="form-label">身份证号</label>
                            <input type="text" class="form-control" id="userIdCard" name="id_card">
                        </div>
                        <div class="mb-3">
                            <label for="userAvatar" class="form-label">头像URL</label>
                            <input type="text" class="form-control" id="userAvatar" name="avatar">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" id="saveUserBtn">保存</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 初始化模态框
            const editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            
            // 编辑按钮点击事件
            document.querySelectorAll('.btn-primary.btn-sm').forEach(button => {
                if (button.innerHTML.includes('编辑')) {
                    button.addEventListener('click', function() {
                        const userId = this.getAttribute('data-id');
                        const nickname = this.getAttribute('data-nickname');
                        const phone = this.getAttribute('data-phone') || '';
                        const realName = this.getAttribute('data-real-name') || '';
                        const idCard = this.getAttribute('data-id-card') || '';
                        const avatar = this.getAttribute('data-avatar') || '';
                        
                        // 填充表单数据
                        document.getElementById('userId').value = userId;
                        document.getElementById('userNickname').value = nickname;
                        document.getElementById('userPhone').value = phone;
                        document.getElementById('userRealName').value = realName;
                        document.getElementById('userIdCard').value = idCard;
                        document.getElementById('userAvatar').value = avatar;
                        
                        // 显示模态框
                        editUserModal.show();
                    });
                }
            });
            
            // 保存按钮点击事件
            document.getElementById('saveUserBtn').addEventListener('click', function() {
                const form = document.getElementById('editUserForm');
                const formData = new FormData(form);
                
                // 发送 AJAX 请求
                fetch('user_manage.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.code === 200) {
                        // 保存成功，刷新页面
                        location.reload();
                    } else {
                        // 保存失败，显示错误信息
                        alert(data.message || '保存失败');
                    }
                })
                .catch(error => {
                    console.error('保存失败:', error);
                    alert('保存失败，请重试');
                });
            });
            
            // 删除按钮点击事件
            document.querySelectorAll('.btn-danger.btn-sm').forEach(button => {
                if (button.innerHTML.includes('删除')) {
                    button.addEventListener('click', function() {
                        const userId = this.getAttribute('data-id');
                        
                        if (confirm('确定要删除这个用户吗？')) {
                            // 发送 AJAX 请求
                            fetch('user_manage.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: `action=delete&id=${userId}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.code === 200) {
                                    // 删除成功，刷新页面
                                    location.reload();
                                } else {
                                    // 删除失败，显示错误信息
                                    alert(data.message || '删除失败');
                                }
                            })
                            .catch(error => {
                                console.error('删除失败:', error);
                                alert('删除失败，请重试');
                            });
                        }
                    });
                }
            });
        });
    </script>

<?php
// 包含底部模板
require_once __DIR__ . '/../includes/footer.php';
?>