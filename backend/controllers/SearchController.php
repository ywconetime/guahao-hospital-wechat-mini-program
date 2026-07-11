<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/Doctor.php';

class SearchController extends ApiController {
    private $doctorModel;
    
    public function __construct() {
        $this->doctorModel = new Doctor();
    }
    
    // 搜索功能
    public function search() {
        try {
            $params = $this->getParams();
            $keyword = $params['keyword'] ?? '';
            
            if (empty($keyword)) {
                $this->error('搜索关键词不能为空');
            }
            
            // 搜索医生
            $doctors = $this->searchDoctors($keyword);
            
            // 搜索病种（这里使用模拟数据）
            $diseases = $this->searchDiseases($keyword);
            
            $this->success([
                'doctors' => $doctors,
                'diseases' => $diseases
            ], '搜索成功');
        } catch (Exception $e) {
            $this->error('搜索失败: ' . $e->getMessage());
        }
    }
    
    // 搜索医生
    private function searchDoctors($keyword) {
        // 从数据库中搜索医生
        $doctors = $this->doctorModel->searchDoctors($keyword);
        
        // 如果没有找到医生，返回模拟数据
        if (empty($doctors)) {
            return $this->getMockDoctors($keyword);
        }
        
        return $doctors;
    }
    
    // 搜索病种
    private function searchDiseases($keyword) {
        // 模拟病种数据
        $diseases = [
            ['id' => 1, 'name' => '月经不调', 'icon' => 'https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=menstrual%20health%20icon&image_size=square'],
            ['id' => 2, 'name' => '妇科疾病', 'icon' => 'https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=gynecological%20disease%20icon&image_size=square'],
            ['id' => 3, 'name' => '不孕不育', 'icon' => 'https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=infertility%20icon&image_size=square'],
            ['id' => 4, 'name' => '妇科炎症', 'icon' => 'https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=gynecological%20inflammation%20icon&image_size=square'],
            ['id' => 5, 'name' => '私密整形', 'icon' => 'https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=private%20plastic%20surgery%20icon&image_size=square'],
            ['id' => 6, 'name' => '计划生育', 'icon' => 'https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=family%20planning%20icon&image_size=square'],
            ['id' => 7, 'name' => '宫颈疾病', 'icon' => 'https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=cervical%20disease%20icon&image_size=square'],
            ['id' => 8, 'name' => '妇科微创', 'icon' => 'https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=gynecological%20minimally%20invasive%20surgery%20icon&image_size=square']
        ];
        
        // 过滤病种并重置键名
        return array_values(array_filter($diseases, function($disease) use ($keyword) {
            return strpos($disease['name'], $keyword) !== false;
        }));
    }
    
    // 获取模拟医生数据
    private function getMockDoctors($keyword) {
        $doctors = [
            [
                'id' => 1,
                'name' => '张医生',
                'title' => '主任医师',
                'department' => '妇产科',
                'specialty' => '妇科肿瘤',
                'description' => '从事妇产科临床工作20余年，擅长妇科肿瘤的诊断与治疗',
                'avatar' => 'https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=female%20doctor%20portrait%20professional%20in%20white%20coat&image_size=square'
            ],
            [
                'id' => 2,
                'name' => '李医生',
                'title' => '副主任医师',
                'department' => '妇产科',
                'specialty' => '产前诊断',
                'description' => '专注于产前诊断和高危妊娠管理，经验丰富',
                'avatar' => 'https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=female%20doctor%20portrait%20professional%20in%20white%20coat&image_size=square'
            ],
            [
                'id' => 3,
                'name' => '王医生',
                'title' => '主治医师',
                'department' => '妇产科',
                'specialty' => '不孕不育',
                'description' => '擅长不孕不育的诊断与治疗，帮助众多家庭实现生育愿望',
                'avatar' => 'https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=female%20doctor%20portrait%20professional%20in%20white%20coat&image_size=square'
            ]
        ];
        
        // 过滤医生并重置键名
        return array_values(array_filter($doctors, function($doctor) use ($keyword) {
            return strpos($doctor['name'], $keyword) !== false ||
                   strpos($doctor['title'], $keyword) !== false ||
                   strpos($doctor['department'], $keyword) !== false ||
                   strpos($doctor['specialty'], $keyword) !== false ||
                   strpos($doctor['description'], $keyword) !== false;
        }));
    }
}
?>