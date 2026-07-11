<?php
class ApiController {
    protected function getParams() {
        // 从JSON请求体获取参数
        $content = file_get_contents('php://input');
        $params = json_decode($content, true) ?: [];
        
        // 合并GET查询参数
        if (!empty($_GET)) {
            $params = array_merge($params, $_GET);
        }
        
        return $params;
    }
    
    protected function success($data = null, $message = '操作成功') {
        $response = [
            'code' => 200,
            'message' => $message,
            'data' => $data
        ];
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    protected function error($message = '操作失败', $code = 400) {
        $response = [
            'code' => $code,
            'message' => $message,
            'data' => null
        ];
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    protected function getUserId() {
        // 这里应该从JWT token或session中获取用户ID
        // 暂时返回1作为测试
        return 1;
    }
}