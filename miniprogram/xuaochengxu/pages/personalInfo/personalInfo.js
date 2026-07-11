// personalInfo.js
Page({
  data: {
    userInfo: {},
    genderArray: ['未知', '男', '女'],
    genderIndex: 0,
    themeColor: '#007AFF'
  },
  
  onLoad: function(options) {
    // 页面加载时的逻辑
    // 设置页面标题
    wx.setNavigationBarTitle({
      title: '个人信息'
    });
    
    // 获取主题颜色
    this.setThemeColor();
    
    // 获取用户信息
    this.getUserInfo();
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
  
  // 获取用户信息
  getUserInfo: function() {
    const app = getApp();
    const userInfo = app.globalData.userInfo || {};
    
    // 设置性别索引
    let genderIndex = 0;
    if (userInfo.gender === 1) {
      genderIndex = 1; // 男
    } else if (userInfo.gender === 2) {
      genderIndex = 2; // 女
    }
    
    this.setData({
      userInfo: userInfo,
      genderIndex: genderIndex
    });
  },
  
  // 选择头像
  chooseAvatar: function() {
    wx.chooseImage({
      count: 1,
      sizeType: ['original', 'compressed'],
      sourceType: ['album', 'camera'],
      success: (res) => {
        const tempFilePaths = res.tempFilePaths;
        // 这里可以上传头像到服务器，现在只是本地预览
        this.setData({
          'userInfo.avatarUrl': tempFilePaths[0]
        });
      }
    });
  },
  
  // 昵称变化
  onNickNameChange: function(e) {
    this.setData({
      'userInfo.nickName': e.detail.value
    });
  },
  
  // 性别变化
  onGenderChange: function(e) {
    const genderIndex = e.detail.value;
    let gender = 0;
    if (genderIndex == 1) {
      gender = 1; // 男
    } else if (genderIndex == 2) {
      gender = 2; // 女
    }
    
    this.setData({
      genderIndex: genderIndex,
      'userInfo.gender': gender
    });
  },
  
  // 生日变化
  onBirthdayChange: function(e) {
    this.setData({
      'userInfo.birthday': e.detail.value
    });
  },
  
  // 绑定手机号
  bindPhoneNumber: function(e) {
    // 这里需要调用后端API来解密手机号并绑定
    // 现在只是模拟绑定成功
    wx.showToast({
      title: '手机号绑定成功',
      icon: 'success'
    });
    
    // 模拟绑定手机号
    this.setData({
      'userInfo.phone': '138****8888'
    });
  },
  
  // 保存个人信息
  savePersonalInfo: function() {
    const app = getApp();
    app.globalData.userInfo = this.data.userInfo;
    
    // 这里可以调用后端API来保存个人信息
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