// patient.js
Page({
  data: {
    patients: [],
    themeColor: '#007AFF'
  },
  
  onLoad() {
    // 页面加载时获取主题颜色
    this.setThemeColor();
    // 页面加载时获取就诊人列表
    this.loadPatients();
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
  
  // 加载就诊人列表
  loadPatients() {
    const app = getApp();
    
    // 先从本地存储读取token
    const storedToken = wx.getStorageSync('token');
    if (storedToken) {
      app.globalData.token = storedToken;
    }
    
    // 检查是否登录
    if (!app.globalData.token) {
      wx.showToast({ title: '请先登录', icon: 'none' });
      return;
    }
    
    console.log('加载就诊人列表，token:', app.globalData.token);
    
    // 从后端API获取就诊人列表
    app.request(`/api/patient/getPatients.php`, {
      token: app.globalData.token
    }, 'GET')
      .then(res => {
        console.log('Patients from API:', res);
        const data = res.data || [];
        this.setData({ patients: data });
      })
      .catch(err => {
        console.error('Error getting patients from API:', err);
        wx.showToast({ title: '获取就诊人列表失败', icon: 'none' });
      });
  },
  
  // 添加就诊人
  addPatient() {
    wx.navigateTo({
      url: '/pages/patientForm/patientForm'
    });
  },
  
  // 编辑就诊人
  editPatient(e) {
    const patientId = e.currentTarget.dataset.id;
    wx.navigateTo({
      url: `/pages/patientForm/patientForm?id=${patientId}`
    });
  },
  
  // 删除就诊人
  deletePatient(e) {
    const patientId = e.currentTarget.dataset.id;
    
    wx.showModal({
      title: '删除就诊人',
      content: '确定要删除这个就诊人吗？',
      success: (res) => {
        if (res.confirm) {
          const app = getApp();
          
          // 先从本地存储读取token
          const storedToken = wx.getStorageSync('token');
          if (storedToken) {
            app.globalData.token = storedToken;
          }
          
          // 检查是否登录
          if (!app.globalData.token) {
            wx.showToast({ title: '请先登录', icon: 'none' });
            return;
          }
          
          app.request(`/api/patient/deletePatient.php`, {
            patient_id: patientId,
            token: app.globalData.token
          }, 'POST')
            .then(res => {
              wx.showToast({ title: '删除成功', icon: 'success' });
              // 重新加载就诊人列表
              this.loadPatients();
            })
            .catch(err => {
              wx.showToast({ title: '删除失败，请重试', icon: 'none' });
            });
        }
      }
    });
  },
  
  // 页面显示时
  onShow() {
    // 重新获取主题颜色
    this.setThemeColor();
    // 重新加载就诊人列表
    this.loadPatients();
  }
})
