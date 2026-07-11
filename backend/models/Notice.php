<?php
class Notice {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // 获取系统通知列表
    public function getSystemNotices() {
        $sql = "SELECT * FROM notices WHERE type = 'system' ORDER BY created_at DESC";
        return $this->db->fetchAll($sql);
    }
    
    // 获取用户的通知列表
    public function getUserNotices($userId) {
        $sql = "SELECT * FROM notices WHERE user_id = ? OR type = 'system' ORDER BY created_at DESC";
        return $this->db->fetchAll($sql, [$userId]);
    }
    
    // 创建新通知
    public function createNotice($data) {
        $sql = "INSERT INTO notices (title, content, type, user_id) VALUES (?, ?, ?, ?)";
        $params = [
            $data['title'],
            $data['content'],
            $data['type'] ?? 'system',
            $data['user_id'] ?? null
        ];
        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }
    
    // 根据ID获取通知详情
    public function getNoticeById($noticeId) {
        $sql = "SELECT * FROM notices WHERE id = ?";
        return $this->db->fetchOne($sql, [$noticeId]);
    }
}