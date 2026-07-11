// pages/notification/notification.js
Page({
  data: {
    notifications: [],
    themeColor: '#007AFF'
  },

  /**
   * 生命周期函数--监听页面加载
   */
  onLoad(options) {
    // 设置页面标题
    const app = getApp();
    wx.setNavigationBarTitle({
      title: '消息通知'
    });
    
    // 加载通知数据
    this.loadNotifications();
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

  /**
   * 加载通知数据
   */
  loadNotifications() {
    wx.showLoading({
      title: '加载中...',
    });
    
    const app = getApp();
    
    // 检查登录状态
    if (!app.globalData.token) {
      wx.hideLoading();
      wx.showToast({
        title: '请先登录',
        icon: 'none'
      });
      return;
    }
    
    // 从API获取通知列表
    app.request('/api/notification/getNotifications.php', {
      token: app.globalData.token
    }, 'GET')
      .then(res => {
        wx.hideLoading();
        if (res.code === 200 && res.data) {
          this.setData({
            notifications: res.data
          });
        } else {
          wx.showToast({
            title: '获取通知失败',
            icon: 'none'
          });
        }
      })
      .catch(err => {
        wx.hideLoading();
        console.error('获取通知失败:', err);
        wx.showToast({
          title: '网络错误，请稍后重试',
          icon: 'none'
        });
      });
  },

  /**
   * 生命周期函数--监听页面初次渲染完成
   */
  onReady() {

  },

  /**
   * 生命周期函数--监听页面显示
   */
  onShow() {
    // 获取最新的系统设置
    const app = getApp();
    app.getSystemSettings();
    // 重新获取主题颜色
    this.setThemeColor();
  },

  /**
   * 生命周期函数--监听页面隐藏
   */
  onHide() {

  },

  /**
   * 生命周期函数--监听页面卸载
   */
  onUnload() {

  },

  /**
   * 页面相关事件处理函数--监听用户下拉动作
   */
  onPullDownRefresh() {
    // 刷新页面数据
    this.loadNotifications();
    wx.stopPullDownRefresh();
  },

  /**
   * 页面上拉触底事件的处理函数
   */
  onReachBottom() {
    // 加载更多通知数据
    wx.showToast({
      title: '已加载全部通知',
      icon: 'none',
      duration: 1000
    });
  },

  /**
   * 用户点击右上角分享
   */
  onShareAppMessage() {
    const app = getApp();
    return {
      title: app.globalData.siteName + '消息通知',
      path: '/pages/notification/notification',
      imageUrl: '/images/no-results.png'
    };
  }
})