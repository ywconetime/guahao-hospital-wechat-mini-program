// notificationSettings.js
Page({
  data: {
    notificationSettings: {
      appointmentStatus: true,
      doctorReply: true,
      system: true,
      promotion: false,
      miniprogram: true,
      sms: false
    },
    themeColor: '#007AFF'
  },
  
  onLoad: function(options) {
    // 页面加载时的逻辑
    // 设置页面标题
    wx.setNavigationBarTitle({
      title: '通知设置'
    });
    
    // 获取主题颜色
    this.setThemeColor();
    
    // 从本地存储获取通知设置
    this.getNotificationSettings();
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
  
  // 页面显示时
  onShow: function() {
    // 重新获取主题颜色
    this.setThemeColor();
  },
  
  // 从本地存储获取通知设置
  getNotificationSettings: function() {
    const settings = wx.getStorageSync('notificationSettings');
    if (settings) {
      this.setData({
        notificationSettings: settings
      });
    }
  },
  
  // 预约状态变更通知开关
  onAppointmentStatusChange: function(e) {
    this.setData({
      'notificationSettings.appointmentStatus': e.detail.value
    });
  },
  
  // 医生回复通知开关
  onDoctorReplyChange: function(e) {
    this.setData({
      'notificationSettings.doctorReply': e.detail.value
    });
  },
  
  // 系统通知开关
  onSystemChange: function(e) {
    this.setData({
      'notificationSettings.system': e.detail.value
    });
  },
  
  // 活动通知开关
  onPromotionChange: function(e) {
    this.setData({
      'notificationSettings.promotion': e.detail.value
    });
  },
  
  // 小程序通知开关
  onMiniprogramChange: function(e) {
    this.setData({
      'notificationSettings.miniprogram': e.detail.value
    });
  },
  
  // 短信通知开关
  onSmsChange: function(e) {
    this.setData({
      'notificationSettings.sms': e.detail.value
    });
  },
  
  // 清空所有通知
  clearAllNotifications: function() {
    wx.showModal({
      title: '清空通知',
      content: '确定要清空所有通知吗？',
      success: (res) => {
        if (res.confirm) {
          // 这里可以调用后端API来清空通知
          // 现在只是模拟清空成功
          wx.showToast({
            title: '通知已清空',
            icon: 'success'
          });
        }
      }
    });
  },
  
  // 查看通知历史
  viewNotificationHistory: function() {
    wx.navigateTo({
      url: '/pages/notification/notification'
    });
  },
  
  // 保存设置
  saveSettings: function() {
    // 保存到本地存储
    wx.setStorageSync('notificationSettings', this.data.notificationSettings);
    
    // 这里可以调用后端API来保存设置
    // 现在只是模拟保存成功
    wx.showToast({
      title: '保存成功',
      icon: 'success'
    });
    
    // 保存后返回上一页
    setTimeout(() => {
      wx.navigateBack();
    }, 1500);
  }
});