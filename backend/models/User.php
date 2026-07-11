<?php
class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // 根据openid获取用户信息
    public function getUserByOpenid($openid) {
        $sql = "SELECT * FROM users WHERE openid = ?";
        return $this->db->fetchOne($sql, [$openid]);
    }
    
    // 创建新用户
    public function createUser($data) {
        $sql = "INSERT INTO users (openid, nickname, avatar, phone, id_card, real_name, gender, age) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $data['openid'],
            $data['nickname'],
            $data['avatar'] ?? null,
            $data['phone'] ?? null,
            $data['id_card'] ?? null,
            $data['real_name'] ?? null,
            $data['gender'] ?? null,
            $data['age'] ?? null
        ];
        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }
    
    // 更新用户信息
    public function updateUser($userId, $data) {
        $fields = [];
        $params = [];
        
        if (isset($data['nickname'])) {
            $fields[] = "nickname = ?";
            $params[] = $data['nickname'];
        }
        if (isset($data['avatar'])) {
            $fields[] = "avatar = ?";
            $params[] = $data['avatar'];
        }
        if (isset($data['phone'])) {
            $fields[] = "phone = ?";
            $params[] = $data['phone'];
        }
        if (isset($data['id_card'])) {
            $fields[] = "id_card = ?";
            $params[] = $data['id_card'];
        }
        if (isset($data['real_name'])) {
            $fields[] = "real_name = ?";
            $params[] = $data['real_name'];
        }
        if (isset($data['gender'])) {
            $fields[] = "gender = ?";
            $params[] = $data['gender'];
        }
        if (isset($data['age'])) {
            $fields[] = "age = ?";
            $params[] = $data['age'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = ?";
        return $this->db->execute($sql, $params) > 0;
    }
    
    // 根据ID获取用户信息
    public function getUserById($userId) {
        $sql = "SELECT * FROM users WHERE id = ?";
        return $this->db->fetchOne($sql, [$userId]);
    }
}