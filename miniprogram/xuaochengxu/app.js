// app.js
App({
  globalData: {
    userInfo: null,
    token: null,
     baseUrl: 'http://guahaotest-2026.mmgcyy.com',
     //baseUrl: 'http://localhost:88',
    siteName: '医院预约挂号中心',
    diseaseId: null,
    departmentId: null,
    themeColor: '#007AFF',
    themeColorLight: '#e6f7ff',
    themeColorDark: '#0056b3',
    settingsLoaded: false,
    authorized: true,
    businessDomainUrl: null,
    businessDomainChecked: false
  },
  
  settingsLoadedCallback: null,
  businessDomainCallback: null,
  
  setSettingsLoadedCallback(callback) {
    this.settingsLoadedCallback = callback;
    if (this.globalData.settingsLoaded) {
      callback();
    }
  },
  
  onLaunch() {
    const app = this;
    
    // 尽早开始检查业务域名（在首页渲染前发起请求）
    this.checkBusinessDomain();
    
    // 先检查系统授权状态
    this.checkAuthStatus().then(() => {
      // 授权检查完成后，继续其他初始化操作
      if (app.globalData.authorized) {
        app.initApp();
      }
    }).catch(() => {
      // 授权检查失败（网络问题等），仍允许继续运行
      app.initApp();
    });
  },
  
  // 检查业务域名配置（尽早调用，在首页加载前发起请求）
  checkBusinessDomain() {
    const app = this;
    wx.request({
      url: app.globalData.baseUrl + '/api/get_business_domain.php',
      method: 'GET',
      timeout: 5000,
      success: res => {
        console.log('业务域名配置(app.js):', res);
        if (res.statusCode === 200 && res.data && res.data.code === 200) {
          const data = res.data.data;
          if (data.use_business_domain && data.business_domain) {
            app.globalData.businessDomainUrl = data.business_domain;
            console.log('启用业务域名:', data.business_domain);
          }
        }
        app.globalData.businessDomainChecked = true;
        // 通知正在等待的页面
        if (app.businessDomainCallback) {
          app.businessDomainCallback();
          app.businessDomainCallback = null;
        }
      },
      fail: err => {
        console.error('获取业务域名配置失败:', err);
        app.globalData.businessDomainChecked = true;
        if (app.businessDomainCallback) {
          app.businessDomainCallback();
          app.businessDomainCallback = null;
        }
      }
    });
  },
  
  checkAuthStatus() {
    const app = this;
    return new Promise((resolve, reject) => {
      wx.request({
        url: app.globalData.baseUrl + '/api/check_auth_status.php',
        method: 'GET',
        timeout: 5000,
        success: res => {
          console.log('授权状态检查结果:', res);
          if (res.statusCode === 200 && res.data) {
            if (res.data.code === 200 && res.data.data && res.data.data.authorized) {
              app.globalData.authorized = true;
              console.log('系统已授权');
              resolve();
            } else {
              app.globalData.authorized = false;
              console.log('系统未授权');
              resolve();
            }
          } else {
            // 响应异常，视为未授权
            app.globalData.authorized = false;
            console.log('授权检查响应异常，视为未授权');
            resolve();
          }
        },
        fail: err => {
          console.log('授权状态检查失败:', err);
          // 网络失败，视为未授权
          app.globalData.authorized = false;
          console.log('授权检查网络失败，视为未授权');
          resolve();  // 即使失败也 resolve，让页面能够显示
        }
      });
    });
  },
  
  initApp() {
    // 从本地存储读取token
    const token = wx.getStorageSync('token');
    if (token) {
      this.globalData.token = token;
      console.log('从本地存储读取token:', token);
    }
    
    // 从本地存储读取网站名称
    this.loadSiteNameFromStorage();
    
    // 从本地存储读取主题颜色
    this.loadThemeColorFromStorage();
    
    // 从API获取主题颜色（覆盖本地存储）
    this.getThemeColor();
    
    // 从API获取系统设置
    this.getSystemSettings();
    
    // 登录
    wx.login({
      success: res => {
        console.log('登录成功', res.code);
      }
    });
  },
  
  loadSiteNameFromStorage() {
    const storedSiteName = wx.getStorageSync('siteName');
    if (storedSiteName) {
      this.globalData.siteName = storedSiteName;
      console.log('从本地存储读取网站名称:', storedSiteName);
    }
  },
  
  loadThemeColorFromStorage() {
    const storedThemeColor = wx.getStorageSync('themeColor');
    const storedThemeColorLight = wx.getStorageSync('themeColorLight');
    const storedThemeColorDark = wx.getStorageSync('themeColorDark');
    
    if (storedThemeColor) {
      this.globalData.themeColor = storedThemeColor;
    }
    if (storedThemeColorLight) {
      this.globalData.themeColorLight = storedThemeColorLight;
    }
    if (storedThemeColorDark) {
      this.globalData.themeColorDark = storedThemeColorDark;
    }
  },
  
  updatePageTitle() {
    wx.setNavigationBarTitle({
      title: this.globalData.siteName
    });
  },
  
  request(url, data = {}, method = 'POST') {
    return new Promise((resolve, reject) => {
      const baseUrl = this.globalData.baseUrl;
      const baseUrlEnd = baseUrl.endsWith('/') ? '' : '/';
      const urlStart = url.startsWith('/') ? url.substring(1) : url;
      const fullUrl = baseUrl + baseUrlEnd + urlStart;
      
      console.log('=== 请求信息 ===');
      console.log('URL:', fullUrl);
      console.log('方法:', method);
      console.log('数据:', JSON.stringify(data));
      
      const headers = {
        'Content-Type': 'application/json'
      };
      
      if (this.globalData.token) {
        headers['Authorization'] = `Bearer ${this.globalData.token}`;
      }
      
      const timeout = 15000;
      
      wx.request({
        url: fullUrl,
        data,
        method,
        header: headers,
        timeout: timeout,
        success: res => {
          console.log('=== 响应信息 ===');
          console.log('状态码:', res.statusCode);
          console.log('响应数据:', res.data);
          
          if (res.statusCode === 200) {
            if (res.data.code === 200 || res.data.token) {
              resolve(res.data);
            } else if (res.data.code) {
              reject(res.data.message || '请求失败');
            } else {
              resolve({ data: res.data });
            }
          } else {
            reject(`HTTP错误: ${res.statusCode}`);
          }
        },
        fail: err => {
          console.error('请求失败:', err);
          if (err.errMsg && err.errMsg.includes('timeout')) {
            reject('请求超时，请检查网络或稍后重试');
          } else {
            reject('网络请求失败');
          }
        }
      });
    });
  },
  
  getSystemSettings() {
    const app = this;
    return new Promise((resolve, reject) => {
      wx.request({
       url: this.globalData.baseUrl + '/api/get_settings.php',
        method: 'GET',
        timeout: 5000,
        header: {
          'Content-Type': 'application/json'
        },
        success: res => {
          console.log('获取系统设置响应:', res);
          if (res.statusCode === 200 && res.data.code === 200 && res.data.data) {
            if (res.data.data.site_name) {
              app.globalData.siteName = res.data.data.site_name;
              wx.setStorageSync('siteName', res.data.data.site_name);
              
              // 主动更新当前页面的标题
              setTimeout(() => {
                const pages = getCurrentPages();
                if (pages.length > 0) {
                  wx.setNavigationBarTitle({
                    title: app.globalData.siteName
                  });
                }
              }, 100);
            }
            if (res.data.data.copyright) {
              wx.setStorageSync('copyright', res.data.data.copyright);
            }
            if (res.data.data.contact_phone) {
              wx.setStorageSync('contact_phone', res.data.data.contact_phone);
            }
            if (res.data.data.phone_enabled !== undefined) {
              wx.setStorageSync('phone_enabled', res.data.data.phone_enabled);
            }
            if (res.data.data.wechat_customer_service !== undefined) {
              wx.setStorageSync('wechat_customer_service', res.data.data.wechat_customer_service);
            }
            if (res.data.data.login_required !== undefined) {
              wx.setStorageSync('login_required', res.data.data.login_required);
              console.log('login_required 设置值:', res.data.data.login_required);
            }
            if (res.data.data.patient_required !== undefined) {
              wx.setStorageSync('patient_required', res.data.data.patient_required);
            }
            if (res.data.data.share_title) {
              wx.setStorageSync('share_title', res.data.data.share_title);
            }
            if (res.data.data.share_description) {
              wx.setStorageSync('share_description', res.data.data.share_description);
            }
            if (res.data.data.share_image) {
              wx.setStorageSync('share_image', res.data.data.share_image);
            }
          }
          // 标记设置已加载
          app.globalData.settingsLoaded = true;
          // 调用回调函数
          if (app.settingsLoadedCallback) {
            app.settingsLoadedCallback();
          }
          resolve(res.data);
        },
        fail: err => {
          console.log('获取系统设置失败，使用本地存储或默认值');
          // 即使失败也标记设置已加载，使用本地存储或默认值
          app.globalData.settingsLoaded = true;
          if (app.settingsLoadedCallback) {
            app.settingsLoadedCallback();
          }
          resolve({});
        }
      });
    });
  },
  
  getThemeColor() {
    const app = this;
    wx.request({
      url: this.globalData.baseUrl + '/api/get_theme_color.php',
      method: 'GET',
      data: { timestamp: new Date().getTime() },
      timeout: 5000,
      header: {
        'Cache-Control': 'no-cache',
        'Pragma': 'no-cache'
      },
      success: res => {
        console.log('获取主题颜色响应:', res);
        if (res.data && res.data.code === 200 && res.data.data && res.data.data.primaryColor) {
          const themeColor = res.data.data.primaryColor;
          const themeColorLight = res.data.data.primaryLight || '#e6f7ff';
          const themeColorDark = res.data.data.primaryDark || '#0056b3';
          
          console.log('设置主题颜色:', themeColor);
          app.globalData.themeColor = themeColor;
          app.globalData.themeColorLight = themeColorLight;
          app.globalData.themeColorDark = themeColorDark;
          
          wx.setStorageSync('themeColor', themeColor);
          wx.setStorageSync('themeColorLight', themeColorLight);
          wx.setStorageSync('themeColorDark', themeColorDark);
          
          app.updateAppColors();
        } else {
          console.log('主题颜色数据格式不正确，使用本地存储或默认值');
          app.applyStoredThemeColor();
        }
      },
      fail: err => {
        console.log('获取主题颜色失败:', err);
        app.applyStoredThemeColor();
      }
    });
  },
  
  applyStoredThemeColor() {
    const storedThemeColor = wx.getStorageSync('themeColor');
    const storedThemeColorLight = wx.getStorageSync('themeColorLight');
    const storedThemeColorDark = wx.getStorageSync('themeColorDark');
    
    if (storedThemeColor) {
      this.globalData.themeColor = storedThemeColor;
      console.log('使用本地存储的主题颜色:', storedThemeColor);
    }
    
    if (storedThemeColorLight) {
      this.globalData.themeColorLight = storedThemeColorLight;
    }
    
    if (storedThemeColorDark) {
      this.globalData.themeColorDark = storedThemeColorDark;
    }
    
    this.updateAppColors();
  },
  
  updateAppColors() {
    const themeColor = this.globalData.themeColor;
    if (!themeColor) return;
    
    wx.setNavigationBarColor({
      frontColor: '#ffffff',
      backgroundColor: themeColor,
      animation: {
        duration: 400,
        timingFunc: 'easeInOut'
      }
    });
    
    wx.setTabBarStyle({
      selectedColor: themeColor,
      borderStyle: 'black'
    });
  }
})