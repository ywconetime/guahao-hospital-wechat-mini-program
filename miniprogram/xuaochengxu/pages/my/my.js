// my.js - 完整恢复版本
Page({
  data: {
    userInfo: {
      nickName: '未登录',
      avatarUrl: '',
      phone: ''
    },
    isLoading: false,
    copyright: '© 2026 东莞清溪华美妇产医院预约挂号',
    themeColor: '#007AFF',
    themeColorLight: '#e6f7ff',
    themeColorDark: '#0056b3',
    showLoginModal: false
  },
  
  onLoad() {
    console.log('my 页面 onLoad');
    const app = getApp();
    
    // 设置页面标题
    wx.setNavigationBarTitle({
      title: app.globalData.siteName
    });
    
    // 注册设置加载完成回调
    app.setSettingsLoadedCallback(() => {
      // 设置加载完成后更新标题
      wx.setNavigationBarTitle({
        title: app.globalData.siteName
      });
    });
    
    this.checkLoginStatus();
    this.getCopyright();
    this.applyThemeColor();
  },
  
  onShow() {
    console.log('my 页面 onShow');
    // 每次显示页面都检查登录状态
    this.checkLoginStatus();
    
    // 检查就诊人状态（如果已登录）
    const app = getApp();
    if (app.globalData.token) {
      this.checkPatientStatus();
    }
  },
  
  // 检查登录状态
  checkLoginStatus() {
    const app = getApp();
    console.log('开始检查登录状态');
    console.log('当前app.globalData.token:', app.globalData.token);
    
    // 先从本地存储中读取token
    const storedToken = wx.getStorageSync('token');
    console.log('从本地存储读取的token:', storedToken);
    
    if (storedToken) {
      app.globalData.token = storedToken;
      console.log('已将本地存储的token赋值给app.globalData.token:', app.globalData.token);
    }
    
    // 检查后台设置的登录控制开关
    const loginRequired = wx.getStorageSync('login_required');
    console.log('登录控制开关状态:', loginRequired);
    
    // 如果后台未启用强制登录，则不显示登录弹窗
    if (loginRequired == 0) {
      console.log('后台设置：不需要强制登录');
      this.setData({ showLoginModal: false });
      return;
    }
    
    // 检查是否已有token
    if (app.globalData.token) {
      // 已有token，直接获取用户信息
      console.log('已有token，开始加载用户信息');
      this.setData({ showLoginModal: false });
      this.loadUserInfo();
      // 检查就诊人状态
      this.checkPatientStatus();
    } else {
      // 未登录，显示登录弹窗
      console.log('未登录，显示登录弹窗');
      this.setData({ showLoginModal: true });
    }
  },
  
  // 加载用户信息
  loadUserInfo() {
    const app = getApp();
    console.log('加载用户信息时的token:', app.globalData.token);
    
    // 如果没有token，直接显示未登录状态
    if (!app.globalData.token) {
      console.log('没有token，显示未登录状态');
      this.setData({
        userInfo: {
          nickName: '未登录',
          avatarUrl: '',
          phone: ''
        }
      });
      return;
    }
    
    const url = '/api/User/getUserInfo.php';
    console.log('完整API路径:', app.globalData.baseUrl + url);
    
    app.request(url, { token: app.globalData.token }, 'GET')
      .then(res => {
        console.log('获取用户信息成功:', res);
        
        // 兼容不同的数据结构
        const userData = res.data?.userInfo || res.data || {};
        
        this.setData({
          userInfo: {
            nickName: userData.nickname || userData.nickName || '微信用户',
            avatarUrl: userData.avatar || userData.avatarUrl || '',
            phone: userData.phone || userData.mobile || ''
          }
        });
        
        console.log('用户信息已更新:', this.data.userInfo);
      })
      .catch(err => {
        console.error('获取用户信息失败:', err);
        // 即使获取用户信息失败，也不要清除token
        this.setData({
          userInfo: {
            nickName: '未登录',
            avatarUrl: '',
            phone: ''
          }
        });
      });
  },
  
  // 点击登录按钮
  handleLogin(e) {
    const app = getApp();
    
    if (e.detail.errMsg === 'getPhoneNumber:ok') {
      wx.showLoading({ title: '登录中...' });
      
      const encryptedData = e.detail.encryptedData;
      const iv = e.detail.iv;
      
      wx.login({
        success: (loginRes) => {
          if (loginRes.code) {
            app.request('/api/User/login.php', {
              code: loginRes.code,
              encryptedData: encryptedData,
              iv: iv
            }, 'POST')
              .then(res => {
                wx.hideLoading();
                if (res.data && res.data.token) {
                  app.globalData.token = res.data.token;
                  wx.setStorageSync('token', res.data.token);
                  this.setData({ showLoginModal: false });
                  wx.showToast({ title: '登录成功', icon: 'success' });
                  this.loadUserInfo();
                  this.checkPatientStatus();
                } else {
                  wx.showToast({ title: '登录失败，请重试', icon: 'none' });
                }
              })
              .catch(err => {
                wx.hideLoading();
                console.error('登录失败:', err);
                wx.showToast({ title: '登录失败，请重试', icon: 'none' });
              });
          } else {
            wx.hideLoading();
            wx.showToast({ title: '登录失败，请重试', icon: 'none' });
          }
        },
        fail: () => {
          wx.hideLoading();
          wx.showToast({ title: '登录失败，请重试', icon: 'none' });
        }
      });
    } else {
      wx.showToast({ title: '请授权手机号登录', icon: 'none' });
    }
  },
  

  
  // 检查就诊人状态
  checkPatientStatus() {
    const app = getApp();
    
    // 先获取系统设置，检查是否强制要求添加就诊人
    app.request('/api/get_settings.php', {}, 'GET')
      .then(settingsRes => {
        console.log('获取系统设置响应:', settingsRes);
        const patientRequired = settingsRes.data?.patient_required === '1';
        
        if (!patientRequired) {
          console.log('就诊人强制添加已关闭，跳过检查');
          return;
        }
        
        // 如果强制添加就诊人，再检查就诊人数量
        app.request('/api/patient/getPatients.php', { token: app.globalData.token }, 'GET')
          .then(res => {
            console.log('检查就诊人响应:', res);
            const patients = res.data || [];
            console.log('就诊人数量:', patients.length);
            if (patients.length === 0) {
              wx.showModal({
                title: '提示',
                content: '请先添加就诊人信息',
                showCancel: false,
                success: () => {
                  wx.redirectTo({ url: '/pages/addPatient/addPatient' });
                }
              });
            }
          })
          .catch(err => {
            console.error('检查就诊人失败:', err);
          });
      })
      .catch(err => {
        console.error('获取系统设置失败:', err);
      });
  },
  
  // 绑定手机号
  bindPhoneNumber(e) {
    if (this.data.isBindingPhone) return;
    
    if (e.detail.errMsg === 'getPhoneNumber:ok') {
      this.setData({ isBindingPhone: true });
      
      const app = getApp();
      const encryptedData = e.detail.encryptedData;
      const iv = e.detail.iv;
      
      wx.login({
        success: (loginRes) => {
          if (loginRes.code) {
            app.request('/api/User/bindPhone.php', {
              encryptedData: encryptedData,
              iv: iv,
              code: loginRes.code,
              token: app.globalData.token
            }, 'POST')
              .then(res => {
                // 更新用户信息
                if (res.data.userInfo) {
                  app.globalData.userInfo = res.data.userInfo;
                  this.setData({
                    userInfo: {
                      nickName: res.data.userInfo.nickname,
                      avatarUrl: res.data.userInfo.avatar,
                      phone: res.data.userInfo.phone
                    }
                  });
                }
                wx.showToast({ title: '手机号绑定成功', icon: 'success' });
              })
              .catch(err => {
                console.error('绑定手机号失败:', err);
                wx.showToast({ title: '绑定手机号失败，请重试', icon: 'none' });
              })
              .finally(() => {
                this.setData({ isBindingPhone: false });
              });
          } else {
            console.error('获取登录凭证失败:', loginRes.errMsg);
            this.setData({ isBindingPhone: false });
            wx.showToast({ title: '获取登录凭证失败，请重试', icon: 'none' });
          }
        },
        fail: (err) => {
          console.error('微信登录失败:', err);
          this.setData({ isBindingPhone: false });
          wx.showToast({ title: '微信登录失败，请重试', icon: 'none' });
        }
      });
    } else {
      console.error('获取手机号失败:', e.detail.errMsg);
      wx.showToast({ title: '获取手机号失败，请重试', icon: 'none' });
    }
  },
  
  // 应用主题颜色
  applyThemeColor() {
    const app = getApp();
    // 确保主题颜色已加载
    if (!app.globalData.themeColor) {
      app.getThemeColor();
    }
    // 更新页面主题颜色数据
    this.setData({
      themeColor: app.globalData.themeColor,
      themeColorLight: app.globalData.themeColorLight,
      themeColorDark: app.globalData.themeColorDark
    });
    // 确保主题颜色存在
    if (app.globalData.themeColor) {
      console.log('页面更新导航栏颜色:', app.globalData.themeColor);
      // 更新导航栏颜色
      wx.setNavigationBarColor({
        frontColor: '#ffffff',
        backgroundColor: app.globalData.themeColor,
        animation: {
          duration: 400,
          timingFunc: 'easeInOut'
        },
        success: function(res) {
          console.log('页面更新导航栏颜色成功:', res);
        },
        fail: function(err) {
          console.error('页面更新导航栏颜色失败:', err);
        }
      });
    }
  },
  
  // 获取版权信息
  getCopyright() {
    const copyright = wx.getStorageSync('copyright');
    if (copyright) {
      this.setData({
        copyright: copyright
      });
    }
  },
  
  // 头像加载失败处理
  onAvatarError() {
    console.log('头像加载失败，使用默认头像');
    const currentUserInfo = this.data.userInfo;
    this.setData({
      userInfo: {
        ...currentUserInfo,
        avatarUrl: '/images/no-results.png'
      }
    });
  },
  
  // 退出登录
  logout() {
    wx.showModal({
      title: '退出登录',
      content: '确定要退出登录吗？',
      success: (res) => {
        if (res.confirm) {
          // 清除用户信息和token
          const app = getApp();
          app.globalData.userInfo = null;
          app.globalData.token = null;
          
          // 清除本地存储中的token
          wx.removeStorageSync('token');
          console.log('token已从本地存储中清除');
          
          // 更新页面数据
          this.setData({
            userInfo: {
              nickName: '未登录',
              avatarUrl: '',
              phone: ''
            }
          });
          
          wx.showToast({ title: '已退出登录', icon: 'success' });
        }
      }
    });
  }
})
