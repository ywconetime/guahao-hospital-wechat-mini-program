# guahao-hospital-wechat-mini-program
项目作者微信号:ywconetime

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

<img width="364" height="448" alt="image" src="https://github.com/user-attachments/assets/4a2cc263-71fa-4bb7-9507-427f05127876" />


3. 小程序前端结构 (xuaochengxu/)
miniprogram/
<img width="377" height="497" alt="image" src="https://github.com/user-attachments/assets/6d36039c-2aaa-4d2d-b1b1-72bbadcebf62" />


4. 后端结构（PHP）
微信小程序后台源码/
<img width="393" height="271" alt="image" src="https://github.com/user-attachments/assets/82869cd1-31b7-4f27-b910-0715c024ea1d" />


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

案例演示：
微信小程序后台演示：https://yu.gd7.cn/admin/login.php
测试账号：admin
测试密码：admin

<img width="414" height="780" alt="image" src="https://github.com/user-attachments/assets/e760f636-f76b-4fc8-abeb-bf0b9ae2173a" />
<img width="414" height="780" alt="image" src="https://github.com/user-attachments/assets/876564f1-4254-40be-a358-5f897d5572d2" />
<img width="414" height="780" alt="image" src="https://github.com/user-attachments/assets/f76c656d-de29-45fa-9274-fd04e618a9a2" />
<img width="414" height="780" alt="image" src="https://github.com/user-attachments/assets/aeecc44c-fbec-4e18-8172-de41cda756c8" />
<img width="414" height="780" alt="image" src="https://github.com/user-attachments/assets/9c68a70a-3df9-420f-9534-3c83395c0dfd" />
<img width="414" height="780" alt="image" src="https://github.com/user-attachments/assets/e486f02e-ee59-4992-baf6-3e71db3fa76c" />
<img width="414" height="780" alt="image" src="https://github.com/user-attachments/assets/da3ba3ee-0974-42db-a6b8-1b79961623c7" />
<img width="414" height="780" alt="image" src="https://github.com/user-attachments/assets/3c9dc967-0598-4944-a4b7-3085e3d9e2fc" />









