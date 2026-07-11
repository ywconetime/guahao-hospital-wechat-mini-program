// settings.js
Page({
  data: {
    userInfo: {},
    cacheSize: '1.2MB',
    copyright: '© 2026 厦门元火妇科男科医院',
    themeColor: '#007AFF'
  },
  
  onLoad: function(options) {
    // 页面加载时的逻辑
    // 设置页面标题
    wx.setNavigationBarTitle({
      title: '设置'
    });
    
    // 获取主题颜色
    this.setThemeColor();
    
    // 获取用户信息
    const app = getApp();
    this.setData({
      userInfo: app.globalData.userInfo || {}
    });
    
    // 获取版权信息
    this.getSystemSettings();
  },
  
  // 设置主题颜色
  setThemeColor() {
    const app = getApp();
    if (app.globalData.themeColor) {
      this.setData({
        themeColor: app.globalData.themeColor
      });
      // 更新导航栏颜色
      this.updateNavigationBarColor(app.globalData.themeColor);
    } else {
      // 如果全局主题色未设置，从API获取
      wx.request({
        url: app.globalData.baseUrl + '/api/get_theme_color.php',
        method: 'GET',
        success: res => {
          if (res.data && res.data.data && res.data.data.primaryColor) {
            const themeColor = res.data.data.primaryColor;
            this.setData({ themeColor: themeColor });
            app.globalData.themeColor = themeColor;
            wx.setStorageSync('themeColor', themeColor);
            // 更新导航栏颜色
            this.updateNavigationBarColor(themeColor);
          }
        },
        fail: err => {
          console.error('获取主题颜色失败:', err);
          // 失败时使用本地存储或默认值
          const storedThemeColor = wx.getStorageSync('themeColor');
          let themeColor = '#007AFF';
          if (storedThemeColor) {
            themeColor = storedThemeColor;
            app.globalData.themeColor = storedThemeColor;
          }
          this.setData({ themeColor: themeColor });
          // 更新导航栏颜色
          this.updateNavigationBarColor(themeColor);
        }
      });
    }
  },
  
  // 更新导航栏颜色
  updateNavigationBarColor(color) {
    console.log('更新导航栏颜色:', color);
    wx.setNavigationBarColor({
      frontColor: '#ffffff',
      backgroundColor: color,
      animation: {
        duration: 400,
        timingFunc: 'easeInOut'
      },
      success: function(res) {
        console.log('更新导航栏颜色成功:', res);
      },
      fail: function(err) {
        console.error('更新导航栏颜色失败:', err);
      }
    });
  },
  
  // 页面显示
  onShow: function() {
    // 重新获取主题颜色
    this.setThemeColor();
    
    // 页面显示时的逻辑
    const app = getApp();
    this.setData({
      userInfo: app.globalData.userInfo || {}
    });
    
    // 获取版权信息
    this.getSystemSettings();
  },
  
  // 获取系统设置
  getSystemSettings: function() {
    const app = getApp();
    wx.request({
      url: app.globalData.baseUrl + '/api/Settings/getSettings.php',
      method: 'GET',
      success: (res) => {
        if (res.data && res.data.code === 200 && res.data.data) {
          this.setData({
            copyright: res.data.data.copyright || '© 2026 厦门元火妇科男科医院'
          });
        }
      },
      fail: (err) => {
        console.error('获取系统设置失败:', err);
      }
    });
  },
  
  // 个人信息
  handlePersonalInfo: function() {
    wx.navigateTo({
      url: '/pages/personalInfo/personalInfo'
    });
  },
  
  // 通知设置
  handleNotificationSettings: function() {
    wx.navigateTo({
      url: '/pages/notificationSettings/notificationSettings'
    });
  },
  
  // 隐私设置
  handlePrivacySettings: function() {
    wx.navigateTo({
      url: '/pages/privacySettings/privacySettings'
    });
  },
  
  // 清除缓存
  handleClearCache: function() {
    wx.showModal({
      title: '清除缓存',
      content: '确定要清除缓存吗？',
      success: (res) => {
        if (res.confirm) {
          // 模拟清除缓存
          this.setData({
            cacheSize: '0KB'
          });
          wx.showToast({
            title: '缓存已清除',
            icon: 'success'
          });
        }
      }
    });
  },
  
  // 检查更新
  handleCheckUpdate: function() {
    wx.showToast({
      title: '当前已是最新版本',
      icon: 'success'
    });
  }
});