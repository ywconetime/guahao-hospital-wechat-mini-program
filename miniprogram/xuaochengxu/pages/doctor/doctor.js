Page({
  data: {
    doctors: [],
    searchKeyword: '',
    copyright: '© 2026 医院预约挂号',
    themeColor: '#007AFF',
    diseaseId: null,
    departmentId: null,
    showLoginModal: false,
    showPatientModal: false
  },

  onLoad(options) {
    const app = getApp();
    wx.setNavigationBarTitle({ title: app.globalData.siteName });
    
    // 注册设置加载完成回调
    app.setSettingsLoadedCallback(() => {
      // 设置加载完成后更新标题
      wx.setNavigationBarTitle({
        title: app.globalData.siteName
      });
    });
    
    this.setData({
      diseaseId: app.globalData.diseaseId,
      departmentId: app.globalData.departmentId,
      themeColor: app.globalData.themeColor
    });
    
    this.getCopyright();
    this.getDoctorsList();
    this.applyThemeColor();
    
    // 检查登录状态
    this.checkLoginStatus();
  },
  
  checkLoginStatus: function() {
    const app = getApp();
    const that = this;
    
    if (app.globalData.settingsLoaded) {
      that.performLoginCheck();
    } else {
      app.setSettingsLoadedCallback(function() {
        that.performLoginCheck();
      });
    }
  },
  
  performLoginCheck: function() {
    const app = getApp();
    const loginRequired = wx.getStorageSync('login_required');
    console.log('登录控制开关状态:', loginRequired);
    
    if (loginRequired == 0) {
      console.log('后台设置：不需要强制登录');
      return;
    }
    
    if (!app.globalData.token) {
      const storedToken = wx.getStorageSync('token');
      if (!storedToken) {
        this.setData({ showLoginModal: true });
      } else {
        app.globalData.token = storedToken;
        // 登录成功后检查就诊人
        this.checkPatientRequired();
      }
    } else {
      // 已登录，检查就诊人
      this.checkPatientRequired();
    }
  },
  
  checkPatientRequired: function() {
    const patientRequired = wx.getStorageSync('patient_required');
    console.log('专家团队页面 - 就诊人强制添加状态:', patientRequired);
    console.log('专家团队页面 - patientRequired 类型:', typeof patientRequired);
    console.log('专家团队页面 - patientRequired 值:', patientRequired);
    
    // 如果后台未启用强制添加就诊人，则不显示提示
    if (patientRequired == 0 || patientRequired === '0' || patientRequired === false) {
      console.log('专家团队页面 - 后台设置：不需要强制添加就诊人');
      return;
    }
    
    console.log('专家团队页面 - 需要检查就诊人');
    // 检查是否已有就诊人
    this.checkPatientExists();
  },
  
  checkPatientExists: function() {
    const app = getApp();
    const that = this;
    
    app.request('/api/patient/getPatients.php', {}, 'GET')
      .then((res) => {
        console.log('专家团队页面 - 就诊人列表:', res);
        
        if (res && res.code === 200 && res.data && Array.isArray(res.data) && res.data.length > 0) {
          console.log('专家团队页面 - 已有就诊人，不显示提示框');
        } else {
          console.log('专家团队页面 - 没有就诊人，显示提示框');
          that.setData({ showPatientModal: true });
        }
      })
      .catch((err) => {
        console.error('专家团队页面 - 检查就诊人失败:', err);
        // 如果请求失败，也显示提示框
        that.setData({ showPatientModal: true });
      });
  },
  
  handleLogin: function(e) {
    const app = getApp();
    const that = this;
    
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
                  // 登录成功后检查就诊人
                  setTimeout(() => {
                    that.checkPatientRequired();
                  }, 1000);
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

  applyThemeColor: function() {
    const app = getApp();
    this.setData({
      themeColor: app.globalData.themeColor,
      themeColorLight: app.globalData.themeColorLight,
      themeColorDark: app.globalData.themeColorDark
    });
    
    if (app.globalData.themeColor) {
      wx.setNavigationBarColor({
        frontColor: '#ffffff',
        backgroundColor: app.globalData.themeColor,
        animation: { duration: 400, timingFunc: 'easeInOut' }
      });
    }
  },

  getCopyright: function() {
    const copyright = wx.getStorageSync('copyright');
    if (copyright) {
      this.setData({ copyright: copyright });
    }
  },

  onSearchInput: function(e) {
    this.setData({ searchKeyword: e.detail.value });
  },

  onSearch: function() {
    const keyword = this.data.searchKeyword.trim();
    if (!keyword) {
      wx.showToast({ title: '请输入搜索关键词', icon: 'none' });
      return;
    }

    const app = getApp();
    const url = app.globalData.baseUrl + '/api/Doctor/getDoctors.php?keyword=' + encodeURIComponent(keyword);

    wx.request({
      url: url,
      method: 'GET',
      success: res => {
        if (res.data.code === 200 && res.data.data !== null) {
          const doctorData = Array.isArray(res.data.data) ? res.data.data : [];
          const doctors = doctorData.map(doctor => {
            let avatar = doctor.avatar;
            if (!avatar) {
              avatar = '/images/no-results.png';
            } else if (!avatar.startsWith('http://') && !avatar.startsWith('https://')) {
              let baseUrl = app.globalData.baseUrl;
              if (baseUrl && !baseUrl.endsWith('/')) {
                baseUrl += '/';
              }
              avatar = baseUrl + avatar;
            }
            return {
              ...doctor,
              expanded: false,
              avatar: avatar,
              credentials: doctor.description || '暂无详细介绍',
              specialty: doctor.specialty || '暂无专长信息'
            };
          });
          this.setData({ doctors: doctors });
        } else {
          wx.showToast({ title: '搜索失败', icon: 'none' });
        }
      },
      fail: () => {
        wx.showToast({ title: '网络请求失败', icon: 'none' });
      }
    });
  },

  getDoctorsList() {
    const app = getApp();
    const { diseaseId, departmentId } = this.data;
    
    let url = app.globalData.baseUrl + '/api/Doctor/getDoctors.php';
    let params = [];
    
    if (diseaseId) {
      params.push(`disease_id=${diseaseId}`);
    }
    if (departmentId) {
      params.push(`department_id=${departmentId}`);
    }
    if (params.length > 0) {
      url += '?' + params.join('&');
    }

    wx.request({
      url: url,
      method: 'GET',
      success: res => {
        if (res.data.code === 200 && res.data.data !== null) {
          const doctorData = Array.isArray(res.data.data) ? res.data.data : [];
          const doctors = doctorData.map(doctor => {
            let avatar = doctor.avatar;
            if (!avatar) {
              avatar = '/images/no-results.png';
            } else if (!avatar.startsWith('http://') && !avatar.startsWith('https://')) {
              let baseUrl = app.globalData.baseUrl;
              if (baseUrl && !baseUrl.endsWith('/')) {
                baseUrl += '/';
              }
              avatar = baseUrl + avatar;
            }
            return {
              ...doctor,
              expanded: false,
              avatar: avatar,
              credentials: doctor.description || '暂无详细介绍',
              specialty: doctor.specialty || '暂无专长信息'
            };
          });
          this.setData({ doctors: doctors });
        } else {
          this.useDefaultDoctors();
        }
      },
      fail: () => {
        this.useDefaultDoctors();
      }
    });
  },

  useDefaultDoctors() {
    const defaultDoctors = [
      {
        id: 1,
        name: '宋琼',
        title: '院长/临床工作20多年',
        department: '妇产科',
        specialty: '对月经不调、妇科炎症、宫颈疾病等常见妇科疾病的诊治具有丰富临床经验。',
        description: '沈阳附医北方医院名誉院长，原同济医学院附属同济医院妇产科主任',
        avatar: '/images/no-results.png',
        expanded: false
      },
      {
        id: 2,
        name: '李萍',
        title: '不孕不育会诊首席专家',
        department: '妇产科',
        specialty: '专注于不孕不育诊疗，涵盖输卵管堵塞、多囊卵巢综合征等多种不孕不育疾病。',
        description: '毕业于四川大学临床医学系，医学理论根基深厚',
        avatar: '/images/no-results.png',
        expanded: false
      },
      {
        id: 3,
        name: '王利',
        title: '学科带头人',
        department: '妇产科',
        specialty: '妇科常见病多发病资深专家，妇科临床经验累积24年。',
        description: '宫腔镜腹腔镜手术资深专家，哈尔滨医科大学附属医院进修',
        avatar: '/images/no-results.png',
        expanded: false
      }
    ];
    this.setData({ doctors: defaultDoctors });
  },

  navigateToDoctorDetail(e) {
    const index = e.currentTarget.dataset.index;
    const doctor = this.data.doctors[index];
    
    if (doctor) {
      wx.navigateTo({
        url: `/pages/doctorDetail/doctorDetail?id=${doctor.id}&name=${encodeURIComponent(doctor.name)}&title=${encodeURIComponent(doctor.title)}&description=${encodeURIComponent(doctor.credentials || doctor.description)}&specialty=${encodeURIComponent(doctor.specialty)}&avatar=${encodeURIComponent(doctor.avatar)}`
      });
    }
  },

  navigateToAppointment(e) {
    const index = e.currentTarget.dataset.index;
    const doctor = this.data.doctors[index];
    
    if (doctor) {
      wx.navigateTo({
        url: `/pages/doctorDetail/doctorDetail?id=${doctor.id}&name=${encodeURIComponent(doctor.name)}&title=${encodeURIComponent(doctor.title)}&description=${encodeURIComponent(doctor.credentials || doctor.description)}&specialty=${encodeURIComponent(doctor.specialty)}&avatar=${encodeURIComponent(doctor.avatar)}`
      });
    }
  },

  toggleExpand(e) {
    const index = e.currentTarget.dataset.index;
    const doctors = [...this.data.doctors];
    doctors[index].expanded = !doctors[index].expanded;
    this.setData({ doctors: doctors });
  },

  onShow() {
    const app = getApp();
    wx.setNavigationBarTitle({ title: app.globalData.siteName });
    
    this.getCopyright();
    this.applyThemeColor();
    
    if (typeof this.getTabBar === 'function' && this.getTabBar()) {
      this.getTabBar().setData({ selected: 2 });
    }
    
    const newDiseaseId = app.globalData.diseaseId;
    const newDepartmentId = app.globalData.departmentId;
    
    if (this.data.diseaseId !== newDiseaseId || this.data.departmentId !== newDepartmentId) {
      this.setData({
        diseaseId: newDiseaseId,
        departmentId: newDepartmentId,
        searchKeyword: ''
      });
      this.getDoctorsList();
    }
    
    // 检查就诊人状态（确保已经有就诊人）
    if (app.globalData.token) {
      this.checkPatientRequired();
    }
  },

  onPullDownRefresh() {
    this.getDoctorsList();
    wx.stopPullDownRefresh();
  },

  onReachBottom() {
    wx.showToast({ title: '已加载全部医生', icon: 'none', duration: 1000 });
  },

  onShareAppMessage() {
    const app = getApp();
    const shareTitle = wx.getStorageSync('share_title') || app.globalData.siteName + '专家团队';
    const shareDescription = wx.getStorageSync('share_description') || '专业医疗服务，便捷预约挂号';
    const shareImage = wx.getStorageSync('share_image');
    const baseUrl = app.globalData.baseUrl;

    return {
      title: shareTitle,
      desc: shareDescription,
      path: '/pages/doctor/doctor',
      imageUrl: shareImage ? (shareImage.startsWith('http') ? shareImage : baseUrl + shareImage) : '/images/no-results.png'
    };
  },

  onShareTimeline() {
    const app = getApp();
    const shareTitle = wx.getStorageSync('share_title') || app.globalData.siteName + '专家团队';
    const shareDescription = wx.getStorageSync('share_description') || '专业医疗服务，便捷预约挂号';
    const shareImage = wx.getStorageSync('share_image');
    const baseUrl = app.globalData.baseUrl;

    return {
      title: shareTitle,
      desc: shareDescription,
      path: '/pages/doctor/doctor',
      imageUrl: shareImage ? (shareImage.startsWith('http') ? shareImage : baseUrl + shareImage) : '/images/no-results.png'
    };
  },
  
  goToAddPatient: function() {
    this.setData({ showPatientModal: false });
    wx.navigateTo({ url: '/pages/patientForm/patientForm' });
  }
})