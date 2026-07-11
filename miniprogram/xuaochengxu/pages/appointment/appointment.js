Page({
  data: {
    doctors: [],
    departments: [],
    selectedDepartment: null,
    loading: true,
    searchKeyword: '',
    copyright: '© 2026 厦门元火妇科男科医院', // 默认版权信息
    themeColor: '#007AFF',
    themeColorLight: '#e6f7ff',
    themeColorDark: '#0056b3',
    showLoginModal: false, // 控制登录弹窗显示
    showPatientModal: false, // 控制就诊人提示弹窗显示
  },
  
  // 导航到预约表单页面
  navigateToAppointmentForm: function(e) {
    const app = getApp();
    const doctorId = e.currentTarget.dataset.doctorId;
    const doctorName = e.currentTarget.dataset.doctorName;
    
    // 检查后台设置的登录控制开关
    const loginRequired = wx.getStorageSync('login_required');
    console.log('登录控制开关状态:', loginRequired);
    
    // 如果后台未启用强制登录，则直接进入预约页面
    if (loginRequired == 0) {
      console.log('后台设置：不需要强制登录，直接进入预约页面');
      wx.navigateTo({
        url: `/pages/appointmentForm/appointmentForm?doctor_id=${doctorId}&name=${encodeURIComponent(doctorName)}`
      });
      return;
    }
    
    // 检查登录状态
    if (!app.globalData.token) {
      const storedToken = wx.getStorageSync('token');
      if (!storedToken) {
        // 未登录，显示登录弹窗
        this.setData({ showLoginModal: true });
        return;
      }
      app.globalData.token = storedToken;
    }
    
    // 检查后台设置的就诊人强制添加开关
    const patientRequired = wx.getStorageSync('patient_required');
    console.log('就诊人强制添加开关状态:', patientRequired);
    
    // 如果后台未启用强制就诊人，则直接进入预约页面
    if (patientRequired == 0) {
      console.log('后台设置：不需要强制添加就诊人，直接进入预约页面');
      wx.navigateTo({
        url: `/pages/appointmentForm/appointmentForm?doctor_id=${doctorId}&name=${encodeURIComponent(doctorName)}`
      });
      return;
    }
    
    // 检查就诊人状态
    app.request('/api/patient/getPatients.php', { token: app.globalData.token }, 'GET')
      .then(res => {
        const patients = res.data || [];
        if (patients.length === 0) {
          // 显示自定义弹窗
          this.setData({ showPatientModal: true });
        } else {
          wx.navigateTo({
            url: `/pages/appointmentForm/appointmentForm?doctor_id=${doctorId}&name=${encodeURIComponent(doctorName)}`
          });
        }
      })
      .catch(() => {
        // 网络请求失败时显示就诊人提示弹窗
        this.setData({ showPatientModal: true });
      });
  },
  
  // 处理搜索输入
  onSearchInput: function(e) {
    this.setData({
      searchKeyword: e.detail.value
    });
  },
  
  // 处理搜索
  onSearch: function() {
    const keyword = this.data.searchKeyword.trim();
    if (!keyword) {
      wx.showToast({ title: '请输入搜索关键词', icon: 'none' });
      return;
    }
    
    console.log('搜索关键词:', keyword);
    this.setData({ loading: true });
    
    const app = getApp();
    const url = app.globalData.baseUrl + '/api/Doctor/getDoctors.php?keyword=' + encodeURIComponent(keyword);
    
    console.log('搜索API路径:', url);
    
    wx.request({
      url: url,
      method: 'GET',
      header: {
        'Content-Type': 'application/json',
        'Authorization': app.globalData.token ? `Bearer ${app.globalData.token}` : ''
      },
      success: res => {
        console.log('搜索API响应:', res);
        if (res.data.code === 200) {
          const data = res.data.data;
          console.log('搜索结果:', data);
          console.log('搜索结果数量:', data ? data.length : 0);
          // 处理医生数据，确保头像路径正确
          const doctors = data.map(doctor => ({
            ...doctor,
            avatar: doctor.avatar ? (doctor.avatar.startsWith('http://') || doctor.avatar.startsWith('https://')) ? doctor.avatar : (doctor.avatar.startsWith('admin/') || doctor.avatar.startsWith('../')) ? app.globalData.baseUrl + '/admin/uploads/' + doctor.avatar.split('/').pop() : app.globalData.baseUrl + '/admin/' + doctor.avatar : `../../images/default-doctor.png`
          }));
          this.setData({
            doctors: doctors,
            loading: false
          });
          console.log('设置后的医生数据:', this.data.doctors);
        } else {
          console.error('搜索失败:', res.data.message);
          this.setData({ loading: false });
          wx.showToast({ title: '搜索失败', icon: 'none' });
        }
      },
      fail: err => {
        console.error('网络请求失败:', err);
        this.setData({ loading: false });
        wx.showToast({ title: '网络请求失败', icon: 'none' });
      }
    });
  },
  
  // 获取科室列表
  getDepartments: function() {
    const app = getApp();
    console.log('开始获取科室列表');
    console.log('API路径:', app.globalData.baseUrl + '/api/get_departments.php');
    // 只获取优先推荐的科室
    app.request('/api/get_departments.php', { only_recommended: true }, 'GET')
      .then(data => {
        console.log('Departments from API:', data);
        if (data && data.data && Array.isArray(data.data)) {
          const departments = data.data;
          console.log('科室数量:', departments.length);
          this.setData({
            departments: departments || []
          });
          console.log('设置后的科室数据:', this.data.departments);
        } else {
          console.error('科室数据格式错误:', data);
          this.setData({
            departments: []
          });
        }
      })
      .catch(err => {
        console.error('Error getting departments:', err);
        this.setData({
          departments: []
        });
      });
  },
  
  // 获取医生列表
  getDoctors: function(departmentId = null) {
    console.log('开始获取医生列表');
    console.log('科室ID:', departmentId);
    this.setData({ loading: true });
    const app = getApp();
    
    // 直接使用wx.request而不是app.request，确保GET请求正确传递参数
    let url = app.globalData.baseUrl + '/api/Doctor/getDoctors.php';
    if (departmentId) {
      url += '?department_id=' + departmentId;
    }
    
    console.log('API路径:', url);
    
    wx.request({
      url: url,
      method: 'GET',
      header: {
        'Content-Type': 'application/json',
        'Authorization': app.globalData.token ? `Bearer ${app.globalData.token}` : ''
      },
      success: res => {
        console.log('医生API响应:', res);
        if (res.data.code === 200) {
          const data = res.data.data;
          console.log('Doctors from API:', data);
          console.log('医生数量:', data ? data.length : 0);
          // 处理医生数据，确保头像路径正确
          const doctors = data.map(doctor => ({
            ...doctor,
            avatar: doctor.avatar ? (doctor.avatar.startsWith('http://') || doctor.avatar.startsWith('https://')) ? doctor.avatar : (doctor.avatar.startsWith('admin/') || doctor.avatar.startsWith('../')) ? app.globalData.baseUrl + '/admin/uploads/' + doctor.avatar.split('/').pop() : app.globalData.baseUrl + '/admin/' + doctor.avatar : `../../images/default-doctor.png`
          }));
          this.setData({
            doctors: doctors,
            loading: false
          });
          console.log('设置后的医生数据:', this.data.doctors);
        } else {
          console.error('获取医生列表失败:', res.data.message);
          this.setData({ loading: false });
          wx.showToast({ title: '获取医生列表失败', icon: 'none' });
        }
      },
      fail: err => {
        console.error('网络请求失败:', err);
        this.setData({ loading: false });
        wx.showToast({ title: '网络请求失败', icon: 'none' });
      }
    });
  },
  
  // 处理科室选择
  handleDepartmentSelect: function(e) {
    console.log('点击科室:', e);
    const departmentId = e.currentTarget.dataset.id;
    console.log('科室ID:', departmentId);
    const selectedDepartment = departmentId === '' ? null : departmentId;
    console.log('选中的科室:', selectedDepartment);
    this.setData({
      selectedDepartment: selectedDepartment
    });
    console.log('设置后的selectedDepartment:', this.data.selectedDepartment);
    this.getDoctors(selectedDepartment);
  },
  
  // 检查用户登录状态
  checkLoginStatus: function() {
    const app = getApp();
    const that = this;
    
    // 等待主题颜色加载完成（最多等待3秒）
    let waitCount = 0;
    const waitForThemeColor = setInterval(function() {
      if (app.globalData.themeColor !== '#007AFF' || waitCount >= 30) {
        clearInterval(waitForThemeColor);
        
        that.setData({
          themeColor: app.globalData.themeColor || '#007AFF'
        });
        
        // 检查后台设置的登录控制开关
        const loginRequired = wx.getStorageSync('login_required');
        console.log('登录控制开关状态:', loginRequired);
        
        // 如果后台未启用强制登录，则不显示登录弹窗
        if (loginRequired == 0) {
          console.log('后台设置：不需要强制登录');
          that.setData({
            showLoginModal: false
          });
          return;
        }
        
        if (!app.globalData.token) {
          const storedToken = wx.getStorageSync('token');
          if (!storedToken) {
            that.setData({
              showLoginModal: true
            });
          } else {
            app.globalData.token = storedToken;
            that.setData({
              showLoginModal: false
            });
            that.checkPatientStatus();
          }
        } else {
          that.setData({
            showLoginModal: false
          });
          that.checkPatientStatus();
        }
      }
      waitCount++;
    }, 100);
    
    // 返回一个占位值，实际逻辑在回调中执行
    return true;
  },
  
  // 处理登录授权（包含手机号授权）
  handleLogin: function(e) {
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
              .then((loginResult) => {
                wx.hideLoading();
                if (loginResult.data && loginResult.data.token) {
                  app.globalData.token = loginResult.data.token;
                  wx.setStorageSync('token', loginResult.data.token);
                  this.setData({ showLoginModal: false });
                  wx.showToast({ title: '登录成功', icon: 'success' });
                  // 登录成功后检查就诊人状态
                  this.checkPatientStatus();
                } else {
                  wx.showToast({ title: '登录失败，请重试', icon: 'none' });
                }
              })
              .catch((err) => {
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
  checkPatientStatus: function() {
    const app = getApp();
    // 如果没有token，不检查就诊人
    if (!app.globalData.token) {
      return;
    }
    
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
            const patients = res.data || [];
            if (patients.length === 0) {
              // 显示自定义弹窗
              this.setData({ showPatientModal: true });
            }
          })
          .catch(() => {});
      })
      .catch(() => {});
  },
  
  // 跳转到添加就诊人页面
  goToAddPatient: function() {
    this.setData({ showPatientModal: false });
    wx.redirectTo({ url: '/pages/addPatient/addPatient' });
  },
  
  // 页面加载
  onLoad: function(options) {
    // 页面加载时的逻辑
    // 设置页面标题
    const app = getApp();
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
    
    // 检查用户登录状态
    this.checkLoginStatus();
    
    // 获取版权信息
    this.getCopyright();
    // 获取科室列表
    this.getDepartments();
    // 获取医生列表
    this.getDoctors();
    // 应用主题颜色
    this.applyThemeColor();
  },
  
  // 应用主题颜色
  applyThemeColor: function() {
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
  getCopyright: function() {
    const copyright = wx.getStorageSync('copyright');
    if (copyright) {
      this.setData({
        copyright: copyright
      });
    }
  },
  
  // 页面显示
  onShow: function() {
    // 页面显示时的逻辑
    // 获取最新的系统设置
    const app = getApp();
    app.getSystemSettings().then(() => {
      console.log('系统设置获取完成');
      
      // 检查登录状态（此时已获取最新设置）
      this.checkLoginStatus();
      
      // 检查就诊人状态（如果已登录）
      this.checkPatientStatus();
      
      // 设置页面标题（确保使用最新的siteName）
      wx.setNavigationBarTitle({
        title: app.globalData.siteName
      });
      
      // 获取最新的版权信息
      this.getCopyright();
      // 重新获取科室列表
      this.getDepartments();
      // 重新获取医生列表
      this.getDoctors(this.data.selectedDepartment);
      // 重新应用主题颜色
      this.applyThemeColor();
      
      // 设置自定义tabBar选中状态
      if (typeof this.getTabBar === 'function' && this.getTabBar()) {
        this.getTabBar().setData({
          selected: 1
        });
      }
    }).catch((err) => {
      console.log('获取系统设置失败:', err);
    });
  },

  // 分享功能
  onShareAppMessage: function() {
    const app = getApp();
    // 从本地存储获取分享设置
    const shareTitle = wx.getStorageSync('share_title') || app.globalData.siteName || '预约挂号';
    const shareDescription = wx.getStorageSync('share_description') || '专业医疗服务，便捷预约挂号';
    const shareImage = wx.getStorageSync('share_image');
    const baseUrl = app.globalData.baseUrl;
    
    return {
      title: shareTitle,
      desc: shareDescription,
      path: '/pages/appointment/appointment',
      imageUrl: shareImage ? (shareImage.startsWith('http') ? shareImage : baseUrl + shareImage) : '/images/no-results.png',
      success: function(res) {
        console.log('分享成功:', res);
      },
      fail: function(res) {
        console.log('分享失败:', res);
      }
    };
  },

  // 分享到朋友圈
  onShareTimeline: function() {
    const app = getApp();
    // 从本地存储获取分享设置
    const shareTitle = wx.getStorageSync('share_title') || app.globalData.siteName || '预约挂号';
    const shareDescription = wx.getStorageSync('share_description') || '专业医疗服务，便捷预约挂号';
    const shareImage = wx.getStorageSync('share_image');
    const baseUrl = app.globalData.baseUrl;
    
    return {
      title: shareTitle,
      desc: shareDescription,
      path: '/pages/appointment/appointment',
      imageUrl: shareImage ? (shareImage.startsWith('http') ? shareImage : baseUrl + shareImage) : '/images/no-results.png',
      success: function(res) {
        console.log('分享到朋友圈成功:', res);
      },
      fail: function(res) {
        console.log('分享到朋友圈失败:', res);
      }
    };
  }
});