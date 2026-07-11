// privacySettings.js
Page({
  data: {
    userInfo: {},
    // 数据隐私设置
    dataPrivacy: {
      personalDataCollection: true,
      marketingCommunications: true
    },
    // 权限设置
    permissions: {
      location: true,
      camera: true,
      album: true
    },
    themeColor: '#007AFF'
  },
  
  onLoad: function(options) {
    // 页面加载时的逻辑
    // 设置页面标题
    wx.setNavigationBarTitle({
      title: '隐私设置'
    });
    
    // 获取用户信息
    const app = getApp();
    this.setData({
      userInfo: app.globalData.userInfo || {}
    });
    
    // 获取主题颜色
    this.setThemeColor();
  },
  
  // 设置主题颜色
  setThemeColor() {
    const app = getApp();
    // 直接从API获取最新的主题颜色
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
        } else if (app.globalData.themeColor) {
          themeColor = app.globalData.themeColor;
        }
        this.setData({ themeColor: themeColor });
        // 更新导航栏颜色
        this.updateNavigationBarColor(themeColor);
      }
    });
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
    // 页面显示时的逻辑
    const app = getApp();
    this.setData({
      userInfo: app.globalData.userInfo || {}
    });
    // 重新获取主题颜色
    this.setThemeColor();
  },
  
  // 切换数据隐私设置
  toggleDataPrivacy: function(e) {
    const key = e.currentTarget.dataset.key;
    const value = e.detail.value;
    this.setData({
      [`dataPrivacy.${key}`]: value
    });
    
    // 保存设置到本地存储
    wx.setStorageSync('dataPrivacy', this.data.dataPrivacy);
  },
  
  // 切换权限设置
  togglePermission: function(e) {
    const key = e.currentTarget.dataset.key;
    const value = e.detail.value;
    this.setData({
      [`permissions.${key}`]: value
    });
    
    // 保存设置到本地存储
    wx.setStorageSync('permissions', this.data.permissions);
  },
  
  // 查看隐私政策
  viewPrivacyPolicy: function() {
    wx.showModal({
      title: '隐私政策',
      content: '我们重视您的隐私保护，致力于为您提供安全、可靠的服务。我们会收集您的个人信息用于提供预约挂号服务，并严格保护您的信息安全。',
      showCancel: false,
      confirmText: '我知道了'
    });
  },
  
  // 查看服务条款
  viewTermsOfService: function() {
    wx.showModal({
      title: '服务条款',
      content: '欢迎使用我们的预约挂号服务。使用本服务即表示您同意我们的服务条款，包括但不限于预约规则、隐私保护等内容。',
      showCancel: false,
      confirmText: '我知道了'
    });
  },
  
  // 导出个人数据
  exportPersonalData: function() {
    wx.showModal({
      title: '导出个人数据',
      content: '确定要导出您的个人数据吗？',
      success: (res) => {
        if (res.confirm) {
          // 模拟导出数据
          wx.showToast({
            title: '数据导出成功',
            icon: 'success'
          });
        }
      }
    });
  },
  
  // 管理应用权限
  manageAppPermissions: function() {
    wx.showModal({
      title: '应用权限管理',
      content: '您可以在手机设置中管理应用的权限设置。',
      confirmText: '去设置',
      cancelText: '取消',
      success: (res) => {
        if (res.confirm) {
          // 跳转到系统设置
          wx.openSetting({
            success: (res) => {
              console.log('设置权限结果:', res);
            }
          });
        }
      }
    });
  },
  
  // 修改密码
  changePassword: function() {
    wx.showModal({
      title: '修改密码',
      content: '密码修改功能开发中',
      showCancel: false
    });
  },
  
  // 绑定手机
  bindPhone: function() {
    wx.showModal({
      title: '绑定手机',
      content: '请在个人信息页面绑定手机。',
      confirmText: '去绑定',
      cancelText: '取消',
      success: (res) => {
        if (res.confirm) {
          wx.navigateTo({
            url: '/pages/personalInfo/personalInfo'
          });
        }
      }
    });
  },
  
  // 删除账号
  deleteAccount: function() {
    wx.showModal({
      title: '删除账号',
      content: '确定要删除您的账号吗？删除后所有数据将不可恢复。',
      confirmText: '确定删除',
      cancelText: '取消',
      success: (res) => {
        if (res.confirm) {
          // 二次确认
          wx.showModal({
            title: '确认删除',
            content: '再次确认要删除您的账号吗？',
            confirmText: '确定删除',
            cancelText: '取消',
            success: (res) => {
              if (res.confirm) {
                // 模拟删除账号
                wx.showToast({
                  title: '账号删除成功',
                  icon: 'success'
                });
                // 清除用户信息
                const app = getApp();
                app.globalData.userInfo = null;
                app.globalData.token = null;
                wx.removeStorageSync('userInfo');
                wx.removeStorageSync('token');
                // 跳转到登录页面
                wx.navigateTo({
                  url: '/pages/my/my'
                });
              }
            }
          });
        }
      }
    });
  }
});