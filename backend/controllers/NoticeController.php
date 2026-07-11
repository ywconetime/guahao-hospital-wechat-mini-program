<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/Notice.php';

class NoticeController extends ApiController {
    private $noticeModel;
    
    public function __construct() {
        $this->noticeModel = new Notice();
    }
    
    // 获取系统通知列表
    public function getSystemNotices() {
        $notices = $this->noticeModel->getSystemNotices();
        $this->success($notices, '获取系统通知列表成功');
    }
    
    // 获取用户通知列表
    public function getUserNotices() {
        $userId = $this->getUserId();
        $notices = $this->noticeModel->getUserNotices($userId);
        $this->success($notices, '获取用户通知列表成功');
    }
    
    // 获取通知详情
    public function getNoticeDetail() {
        $params = $this->getParams();
        $noticeId = $params['notice_id'] ?? '';
        
        if (empty($noticeId)) {
            $this->error('缺少notice_id参数');
        }
        
        $notice = $this->noticeModel->getNoticeById($noticeId);
        if ($notice) {
            $this->success($notice, '获取通知详情成功');
        } else {
            $this->error('通知不存在');
        }
    }
}