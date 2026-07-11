Page({
  data: {
    name: '',
    gender: '男',
    age: '',
    phone: '',
    themeColor: '#007AFF',
    themeColorLight: '#e6f7ff',
    themeColorDark: '#0056b3'
  },
  
  onLoad: function() {
    // 获取主题颜色
    this.getThemeColor();
    // 检查用户是否已经有就诊人，如果有就跳转到首页
    this.checkPatientAndRedirect();
  },
  
  // 获取主题颜色
  getThemeColor: function() {
    const app = getApp();
    // 确保主题颜色已加载
    if (!app.globalData.themeColor) {
      app.getThemeColor();
    }
    // 更新页面主题颜色数据
    this.setData({
      themeColor: app.globalData.themeColor || '#007AFF',
      themeColorLight: app.globalData.themeColorLight || '#e6f7ff',
      themeColorDark: app.globalData.themeColorDark || '#0056b3'
    });
    // 设置导航栏颜色
    wx.setNavigationBarColor({
      frontColor: '#ffffff',
      backgroundColor: app.globalData.themeColor || '#007AFF'
    });
  },
  
  onShow: function() {
    // 每次页面显示时都检查就诊人状态
    // 防止用户通过返回按钮绕过就诊人添加
    this.checkPatientAndRedirect();
  },
  
  // 检查就诊人并跳转
  checkPatientAndRedirect: function() {
    const app = getApp();
    const token = wx.getStorageSync('token');
    if (token) {
      app.globalData.token = token;
    }
    
    if (!app.globalData.token) {
      return;
    }
    
    app.request('/api/patient/getPatients.php', { token: app.globalData.token }, 'GET')
      .then(res => {
        const patients = res.data || [];
        if (patients.length > 0) {
          // 已经有就诊人，直接跳转到首页
          wx.redirectTo({ url: '/pages/index/index' });
        }
      })
      .catch(err => {
        console.error('检查就诊人失败:', err);
      });
  },
  
  bindNameInput: function(e) {
    this.setData({ name: e.detail.value });
  },
  
  bindAgeInput: function(e) {
    this.setData({ age: e.detail.value });
  },
  
  bindPhoneInput: function(e) {
    this.setData({ phone: e.detail.value });
  },
  
  selectGender: function(e) {
    this.setData({ gender: e.currentTarget.dataset.gender });
  },
  
  submitForm: function() {
    if (!this.data.name.trim()) {
      wx.showToast({ title: '请填写姓名', icon: 'none' });
      return;
    }
    if (!this.data.age.trim()) {
      wx.showToast({ title: '请填写年龄', icon: 'none' });
      return;
    }
    if (!this.data.phone.trim()) {
      wx.showToast({ title: '请填写手机号', icon: 'none' });
      return;
    }
    if (!/^1[3-9]\d{9}$/.test(this.data.phone)) {
      wx.showToast({ title: '请输入正确的手机号', icon: 'none' });
      return;
    }
    
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
    
    console.log('添加就诊人，token:', app.globalData.token);
    
    wx.showLoading({ title: '提交中...' });
    
    app.request('/api/patient/addPatient.php', {
      token: app.globalData.token,
      name: this.data.name,
      gender: this.data.gender,
      age: this.data.age,
      phone: this.data.phone
    }, 'POST')
      .then(res => {
        wx.hideLoading();
        if (res.code === 200) {
          wx.showToast({ title: '添加成功', icon: 'success' });
          // 清除就诊人提示标记，让下次检查能正常工作
          wx.removeStorageSync('hasSeenPatientPrompt');
          setTimeout(() => {
            wx.switchTab({ url: '/pages/index/index' });
          }, 1500);
        } else {
          wx.showToast({ title: res.message || res.data?.message || '添加失败', icon: 'none' });
        }
      })
      .catch(err => {
        wx.hideLoading();
        wx.showToast({ title: '添加失败，请重试', icon: 'none' });
      });
  }
});