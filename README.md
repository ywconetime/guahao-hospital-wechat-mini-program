# guahao-hospital-wechat-mini-program
基于原生微信小程序+PHP后端开发的医疗自助挂号系统，支持在线预约挂号、专家团队查看、就诊信息管理。

医疗微信小程序自助挂号系统 - 项目开发与部署文档


一、项目概述


这是一个基于微信小程序 + PHP后端的医疗自助挂号系统，用户可通过小程序在线预约挂号、查看专家团队、管理就诊信息。

技术栈


层级	技术
前端	微信小程序（原生开发）
后端	PHP（作者原创原生态后端）
数据库	MySQL
服务器	Apache / Nginx
环境	PHP 7.x + MySQL 5.7+


---

二、项目结构详解


1. 项目根目录 (guahao/)


guahao-hospital-wechat-mini-program/
├── backend/             # PHP后端服务
│   ├── admin/           # PC管理后台
│   ├── api/             # 小程序请求接口
│   ├── config/          # 数据库、全局配置
│   ├── controllers/     # 业务控制器
│   ├── models/          # 数据库模型
│   ├── utils/           # 通用工具函数
│   ├── uploads/         # 图片、文件上传目录
│   ├── data/            # 日志、缓存
│   ├── error/           # 错误页面
│   ├── .htaccess        # Apache伪静态
│   └── nginx.htaccess   # Nginx伪静态
├── miniprogram/         # 微信小程序前端源码
├── database/            # 数据库备份文件
│   └── guahaotest.sql
├── assets/              # 项目打包成品压缩包
│   └── 2026医疗微信小程序自助挂号.zip
├── docs/                # 项目文档、部署说明
├── .gitignore
├── LICENSE
└── README.md

2. 小程序前端结构 (xuaochengxu/)


miniprogram/
├── pages/               # 业务页面
│   ├── index/           # 首页
│   ├── appointment/     # 预约挂号
│   ├── appointmentForm/ # 预约表单
│   ├── appointmentList/ # 我的预约
│   ├── appointmentDetail/ # 预约详情
│   ├── doctor/          # 专家团队
│   ├── doctorDetail/    # 专家详情
│   ├── my/              # 个人中心
│   ├── patient/         # 就诊人管理
│   ├── patientForm/     # 添加就诊人
│   ├── search/          # 搜索
│   ├── notification/    # 消息通知
│   ├── settings/        # 设置页
│   ├── personalInfo/    # 个人信息
│   ├── notificationSettings/ # 通知设置
│   ├── privacySettings/ # 隐私设置
│   └── webview/         # 网页内嵌
├── components/          # 自定义公共组件
│   └── custom-tab-bar/  # 自定义底部导航
├── images/              # 图片静态资源
├── app.js
├── app.json
├── app.wxss
├── project.config.json
└── sitemap.json

3. 后端结构（PHP）


微信小程序后台源码/
backend/
├── admin/        # PC管理后台
├── api/          # 小程序接口
├── config/       # 数据库、应用配置
├── controllers/  # 业务逻辑控制器
├── models/       # 数据库操作模型
├── utils/        # 工具类
├── uploads/      # 文件上传
├── data/         # 缓存、日志
├── error/        # 错误页面
├── miniprogram/  # 小程序专属后台逻辑
├── .htaccess
└── nginx.htaccess

---

三、开发流程清单


阶段一：环境准备


[ ] 安装PHP开发环境：推荐使用 phpStudy（Windows）或宝塔面板（Linux）

[ ] 安装MySQL数据库：版本 5.7 或以上

[ ] 安装微信开发者工具：从微信官方下载最新版

[ ] 注册微信小程序账号：获取 AppID（已有：wx41abfdf09f1499sb）


阶段二：数据库导入


[ ] 创建数据库（如 guahao）

[ ] 导入 数据库文件/guahao.sql

[ ] 检查数据库表是否完整


阶段三：后端部署（本地开发）


[ ] 将 微信小程序后台源码/ 内容复制到 Web 根目录（如 www/guahaotest/）

[ ] 修改 config/database.php 中的数据库连接信息

[ ] 配置伪静态规则（Apache 用 .htaccess，Nginx 参考 nginx.htaccess）

[ ] 访问 http://localhost/guahao/ 测试是否正常


阶段四：小程序配置


[ ] 用微信开发者工具打开 xuaochengxu/ 目录

[ ] 修改 project.config.json 中的 AppID（如需要）

[ ] 修改小程序中 API 请求地址（指向你的后端域名）

[ ] 调试并测试所有页面功能


阶段五：功能测试


[ ] 首页展示测试

[ ] 专家团队列表与详情

[ ] 预约挂号流程（选择科室 → 选择医生 → 填写信息 → 提交）

[ ] 我的预约列表与详情

[ ] 就诊人管理（增删改）

[ ] 个人中心与设置

[ ] 通知推送测试


阶段六：上线准备


[ ] 小程序代码上传与审核

[ ] 配置服务器 HTTPS（小程序要求 HTTPS）

[ ] 配置合法域名（在小程序后台添加 request 合法域名）

[ ] 提交审核前进行充分测试


---

四、云服务器部署完整教程


1. 服务器准备


推荐配置：
CPU：1核以上

内存：2GB 以上

系统：CentOS 7.9 / Ubuntu 20.04 / Windows Server

带宽：3Mbps 以上


云服务商推荐： 阿里云、腾讯云、华为云

2. 环境安装（以 CentOS 7 + 宝塔面板为例）


#### 步骤 2.1：安装宝塔面板
yum install -y wget && wget -O install.sh http://download.bt.cn/install/install_6.0.sh && sh install.sh

安装完成后，记录面板地址、用户名和密码。

#### 步骤 2.2：通过宝塔面板安装环境
登录宝塔面板 → 软件商店

安装：Nginx（或 Apache）、PHP 7.4、MySQL 5.7

安装：phpMyAdmin（可选，用于管理数据库）


3. 上传项目代码


#### 方式一：通过宝塔面板上传
进入宝塔面板 → 文件管理

定位到网站根目录（如 /www/wwwroot/guahao/）

上传 微信小程序后台源码/ 全部内容

解压（如有压缩包）


#### 方式二：通过 FTP/SFTP 上传
# 示例：使用 scp 命令
scp -r ./微信小程序后台源码/* root@你的服务器IP:/www/wwwroot/guahao/


4. 创建网站与数据库


#### 步骤 4.1：创建数据库
宝塔面板 → 数据库 → 添加数据库

数据库名：guahao

用户名：自定义（如 guahao_user）

密码：自定义并记录

导入 guahao.sql 文件（可通过 phpMyAdmin 或命令行导入）


命令行导入方式：
mysql -u root -p guahaotest < /path/to/guahao.sql


#### 步骤 4.2：创建网站
宝塔面板 → 网站 → 添加站点

域名：填写你的域名（如 guanhao.yourdomain.com）

根目录：/www/wwwroot/guahao/

PHP版本：选择 PHP 7.4

提交后记录 Nginx/Apache 配置


5. 配置数据库连接


修改项目中的数据库配置文件：
vim /www/wwwroot/guahaotest/config/database.php


// 示例配置（根据实际情况修改）
return [
    'hostname' => '127.0.0.1',
    'database' => 'guahao',
    'username' => 'guahao_user',
    'password' => '你的数据库密码',
    'hostport' => '3306',
    'charset'  => 'utf8mb4',
    // ...
];


6. 配置伪静态（Nginx）


在宝塔面板 → 网站 → 设置 → 伪静态，添加以下规则（参考 nginx.htaccess）：

location / {
    if (!-e $request_filename) {
        rewrite ^(.*)$ /index.php?s=$1 last;
        break;
    }
}


7. 配置 HTTPS（必须）


宝塔面板 → 网站 → 设置 → SSL

选择「Let's Encrypt」或「宝塔SSL」

一键申请并开启强制 HTTPS


8. 配置小程序合法域名


登录微信小程序后台（https://mp.weixin.qq.com/）

开发 → 开发管理 → 开发设置 → 服务器域名

添加 request 合法域名：https://guanhao.yourdomain.com


9. 上传小程序代码


微信开发者工具 → 点击「上传」→ 填写版本号

登录微信小程序后台 → 版本管理 → 提交审核

审核通过后发布上线


10. 常见问题排查


问题	解决方案
数据库连接失败	检查 database.php 配置、MySQL 服务状态
页面 404	检查伪静态配置是否正确
小程序请求失败	检查 HTTPS 是否开启、域名是否在小程序后台配置
上传文件失败	检查 uploads/ 目录权限（设置为 755）


---

五、运维建议


1. 定期备份：数据库和代码定期备份到云存储
2. 监控日志：查看 data/log/ 下的错误日志
3. 安全加固：修改默认后台路径、使用强密码
4. 性能优化：开启 PHP OpCache、启用 Nginx 缓存
