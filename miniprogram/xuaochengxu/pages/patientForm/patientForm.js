// patientForm.js
Page({
  data: {
    patient: {
      name: '',
      gender: '男',
      age: '',
      phone: ''
    },
    isEditing: false,
    themeColor: '#007AFF'
  },
  
  onLoad(options) {
    // 页面加载时获取主题颜色
    this.setThemeColor();
    
    // 检查是否是编辑模式
    if (options.id) {
      this.setData({ isEditing: true });
      this.loadPatient(options.id);
    }
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
  
  // 页面显示时
  onShow() {
    // 重新获取主题颜色
    this.setThemeColor();
  },
  
  // 加载就诊人信息
  loadPatient(patientId) {
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
    
    console.log('加载就诊人信息，patientId:', patientId, 'token:', app.globalData.token);
    
    app.request(`/api/patient/getPatient.php`, {
      patient_id: patientId,
      token: app.globalData.token
    }, 'GET')
      .then(res => {
        console.log('获取就诊人响应:', res);
        // API返回的数据结构是 {code: 200, message: '...', data: {...}}
        // res 就是整个 API 返回的对象
        let patientData = null;
        if (res && res.data) {
          // 数据在 res.data 里面
          patientData = res.data;
        } else if (res) {
          // 直接使用 res
          patientData = res;
        }
        
        console.log('解析后的就诊人数据:', patientData);
        
        // 如果数据存在，就设置
        if (patientData) {
          this.setData({ patient: patientData });
        }
      })
      .catch(err => {
        console.error('Error getting patient from API:', err);
        wx.showToast({ title: '获取就诊人信息失败', icon: 'none' });
      });
  },
  
  // 处理姓名输入
  bindNameInput(e) {
    this.setData({
      'patient.name': e.detail.value
    });
  },
  
  // 处理性别选择
  bindGenderChange(e) {
    this.setData({
      'patient.gender': e.detail.value
    });
  },
  
  // 处理年龄输入
  bindAgeInput(e) {
    this.setData({
      'patient.age': e.detail.value
    });
  },
  
  // 处理手机号输入
  bindPhoneInput(e) {
    this.setData({
      'patient.phone': e.detail.value
    });
  },
  
  // 提交表单
  submitForm() {
    const { patient, isEditing } = this.data;
    
    // 验证表单
    if (!patient.name) {
      wx.showToast({ title: '请输入姓名', icon: 'none' });
      return;
    }
    if (!patient.age) {
      wx.showToast({ title: '请输入年龄', icon: 'none' });
      return;
    }
    if (!patient.phone) {
      wx.showToast({ title: '请输入手机号', icon: 'none' });
      return;
    }
    
    // 调用后端API保存就诊人信息
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
    
    console.log('提交就诊人信息，token:', app.globalData.token, 'isEditing:', isEditing, 'patient:', patient);
    
    // 注意：使用addPatient.php而不是createPatient.php
    const url = isEditing ? `/api/patient/updatePatient.php` : `/api/patient/addPatient.php`;
    
    const requestData = {
      name: patient.name,
      gender: patient.gender,
      age: patient.age,
      phone: patient.phone,
      token: app.globalData.token
    };
    
    // 如果是编辑模式，添加id参数
    if (isEditing && patient.id) {
      requestData.id = patient.id;
    }
    
    app.request(url, requestData, 'POST')
      .then(res => {
        console.log('提交就诊人响应:', res);
        wx.showToast({ title: isEditing ? '编辑成功' : '添加成功', icon: 'success' });
        // 跳转到就诊人列表页面
        setTimeout(() => {
          wx.navigateBack();
        }, 1500);
      })
      .catch(err => {
        console.error('Error saving patient:', err);
        wx.showToast({ title: isEditing ? '编辑失败' : '添加失败', icon: 'none' });
      });
  }
})
