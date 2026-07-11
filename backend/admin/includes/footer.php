        </div>
    </div>
    
    <!-- 通知弹窗 -->
    <div class="notification-modal-overlay" id="notificationModal">
        <div class="notification-modal">
            <div class="notification-modal-header">
                <span class="notification-modal-title" id="notificationTitle"></span>
                <button class="notification-modal-close" onclick="closeNotificationModal()">&times;</button>
            </div>
            <div class="notification-modal-body">
                <div class="notification-content" id="notificationContent"></div>
                <div class="notification-attachments" id="notificationAttachments" style="display: none;">
                    <div class="attachments-title">📎 附件</div>
                    <div id="attachmentList"></div>
                </div>
                <div class="notification-download" id="notificationDownload" style="display: none;">
                    <button class="download-button" onclick="openDownloadUrl()">📥 立即下载</button>
                </div>
            </div>
            <div class="notification-modal-footer">
                <button class="notification-close-btn" onclick="closeNotificationModal()">我知道了</button>
            </div>
        </div>
    </div>
    
    <script src="<?php echo $activePage == 'index' ? 'assets/js/bootstrap.bundle.min.js' : '../assets/js/bootstrap.bundle.min.js'; ?>"></script>
    <script>
        // 通知数据
        let notificationData = null;
        
        // 获取 API 基础路径（根据环境自动切换）
        function getApiBasePath() {
            const host = window.location.host;
            // 检查是否是本地/局域网环境
            const isLocal = host.includes('localhost') || 
                           host.includes('127.0.0.1') || 
                           host.startsWith('192.168.') || 
                           host.startsWith('10.');
            
            if (isLocal) {
                return 'http://localhost:88/license_system/api/';
            } else {
                return 'http://shouquan.mmgcyy.com/license_system/api/';
            }
        }
        
        // 获取 cookie
        function getCookie(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
            return '';
        }
        
        // 设置 cookie
        function setCookie(name, value, days = 365) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            const expires = `expires=${date.toUTCString()}`;
            document.cookie = `${name}=${value}; ${expires}; path=/`;
        }
        
        // 获取通知状态
        function getNotificationStatus(notificationId) {
            const cookieName = `notification_${notificationId}_status`;
            const cookieValue = getCookie(cookieName);
            if (cookieValue) {
                try {
                    return JSON.parse(cookieValue);
                } catch (e) {
                    return null;
                }
            }
            return null;
        }
        
        // 设置通知首次显示时间
        function setNotificationFirstShown(notificationId) {
            const cookieName = `notification_${notificationId}_status`;
            const status = getNotificationStatus(notificationId) || {};
            status.first_show_time = Math.floor(Date.now() / 1000);
            status.dismissed = false;
            setCookie(cookieName, JSON.stringify(status));
        }
        
        // 设置通知已关闭
        function setNotificationDismissed(notificationId) {
            const cookieName = `notification_${notificationId}_status`;
            const status = getNotificationStatus(notificationId) || {};
            status.last_show_time = Math.floor(Date.now() / 1000);
            status.dismissed = true;
            setCookie(cookieName, JSON.stringify(status));
        }
        
        // 检查通知
    function checkNotification() {
        const basePath = getApiBasePath();
        console.log('API 路径:', basePath + 'get_notification.php');
        fetch(basePath + 'get_notification.php?source=admin')
            .then(response => response.json())
            .then(data => {
                if (data.show && data.notification) {
                    notificationData = data.notification;
                    // 设置首次显示时间（用于时间控制）
                    if (data.notification.auto_mode) {
                        setNotificationFirstShown(data.notification.id);
                    }
                    showNotificationModal(data.notification, data.attachments || []);
                    markNotificationShown(data.notification.id);
                }
            })
            .catch(error => {
                console.error('获取通知失败:', error);
            });
    }
        
        // 显示通知弹窗
        function showNotificationModal(notification, attachments) {
            document.getElementById('notificationTitle').textContent = notification.title;
            document.getElementById('notificationContent').innerHTML = notification.content;
            
            // 处理附件
            const attachmentSection = document.getElementById('notificationAttachments');
            const attachmentList = document.getElementById('attachmentList');
            if (attachments && attachments.length > 0) {
                attachmentSection.style.display = 'block';
                attachmentList.innerHTML = attachments.map(attachment => {
                    return `
                        <a href="${attachment.file_url}" class="attachment-item" target="_blank">
                            <span class="attachment-icon">📄</span>
                            <span class="attachment-name">${attachment.file_name}</span>
                        </a>
                    `;
                }).join('');
            } else {
                attachmentSection.style.display = 'none';
            }
            
            // 处理下载链接
            const downloadSection = document.getElementById('notificationDownload');
            if (notification.download_url) {
                downloadSection.style.display = 'block';
            } else {
                downloadSection.style.display = 'none';
            }
            
            document.getElementById('notificationModal').classList.add('show');
        }
        
        // 关闭通知弹窗
        function closeNotificationModal() {
            document.getElementById('notificationModal').classList.remove('show');
            if (notificationData) {
                dismissNotification(notificationData.id);
                // 设置 cookie 记录关闭状态
                if (notificationData.auto_mode) {
                    setNotificationDismissed(notificationData.id);
                }
            }
        }
        
        // 标记通知已显示
        function markNotificationShown(notificationId) {
            const basePath = getApiBasePath();
            fetch(basePath + 'mark_notification_shown.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ notification_id: notificationId })
            });
        }
        
        // 标记通知已关闭
        function dismissNotification(notificationId) {
            const basePath = getApiBasePath();
            fetch(basePath + 'close_notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ notification_id: notificationId })
            });
        }
        
        // 打开下载链接
        function openDownloadUrl() {
            if (notificationData && notificationData.download_url) {
                window.open(notificationData.download_url, '_blank');
            }
        }
        
        // 页面加载完成后检查通知
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(checkNotification, 500);
            
            // ============================================================================
            // 【灵码修复】站点信息自动采集功能
            // 修复日期: 2026-05-28
            // 修复工具: 通义灵码 (Tongyi Lingma)
            // 功能说明: 页面加载时自动采集站点信息并上报到授权系统
            // ============================================================================
            
            // 获取授权码
            const licenseCode = localStorage.getItem('license_code');
            
            // 如果有授权码，自动采集站点信息
            if (licenseCode) {
                console.log('开始采集站点信息...');
                collectSiteInfo(licenseCode);
            } else {
                console.log('未找到授权码，跳过站点信息采集');
            }
        });
        
        // 采集站点信息并上报
        async function collectSiteInfo(licenseCode) {
            try {
                const basePath = getApiBasePath();
                // 【灵码修复】使用动态 API 路径，支持本地和云端
                // 修复日期: 2026-05-28
                        
                const response = await fetch(basePath + 'site_info.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action: 'collect',
                        license_code: licenseCode
                    })
                });
                        
                const result = await response.json();
                console.log('站点信息采集结果:', result);
                        
                if (result.code === 200) {
                    console.log('✅ 站点信息上报成功');
                } else {
                    console.warn('⚠️ 站点信息上报失败:', result.message);
                }
            } catch (error) {
                console.error('❌ 站点信息采集失败:', error);
                // 静默失败，不影响用户
            }
        }
        
        // 点击遮罩层关闭弹窗
        document.getElementById('notificationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeNotificationModal();
            }
        });
    </script>
</body>
</html>