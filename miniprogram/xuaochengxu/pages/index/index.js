Page({
  data: {
    doctors: [],
    departments: [],
    departmentDiseases: [],
    selectedDepartmentId: null,
    carousel: [],
    copyright: '© 2026 医院预约挂号',
    showBackToTop: false,
    phoneEnabled: true,
    contactPhone: '13800138000',
    wechatCustomerService: true,
    themeColor: '#007AFF',
    showLoginModal: false,
    showAuthBlock: false,
    authErrorMessage: '',
    // 通知相关
    showNotificationModal: false,
    notificationData: null,
    notificationAttachments: [],
    hideAll: false,  // 业务域名跳转时隐藏所有内容
    loadingMessage: '正在加载中...',  // 加载提示语
    homeModules: [
      { title: '预约挂号', subtitle: '线上预约，快速便捷', icon: '', link_type: 'tabbar', link_url: '/pages/appointment/appointment' },
      { title: '我的预约', subtitle: '查看个人预约', icon: '', link_type: 'page', link_url: '/pages/appointmentList/appointmentList' },
      { title: '专家团队', subtitle: '按医生预约挂号', icon: '', link_type: 'tabbar', link_url: '/pages/doctor/doctor' }
    ],
    // 授权同步相关
    authCheckTimer: null,  // 定时检查授权状态的定时器
    authCheckInterval: 3000  // 每 3 秒检查一次（毫秒）
  },

  onLoad: function(options) {
    const app = getApp();
    const that = this;
    
    // ★ 业务域名检查 - 最高优先级
    // 如果业务域名已确定，立即跳转，不渲染首页任何内容
    if (app.globalData.businessDomainUrl) {
      console.log('业务域名已配置，立即跳转:', app.globalData.businessDomainUrl);
      wx.redirectTo({
        url: '/pages/webview/webview?url=' + encodeURIComponent(app.globalData.businessDomainUrl)
      });
      return;
    }
    
    // 如果业务域名尚未检查完毕（还在加载中），等待回调
    if (!app.globalData.businessDomainChecked) {
      console.log('业务域名检查中，等待结果...');
      this.setData({ hideAll: true, loadingMessage: '正在加载中...' });
      
      // 注册回调，等待业务域名检查完成
      app.businessDomainCallback = function() {
        if (app.globalData.businessDomainUrl) {
          console.log('业务域名检查完成，跳转:', app.globalData.businessDomainUrl);
          wx.redirectTo({
            url: '/pages/webview/webview?url=' + encodeURIComponent(app.globalData.businessDomainUrl)
          });
        } else {
          console.log('未启用业务域名，正常渲染首页');
          that.setData({ hideAll: false });
          that.initPage(options);
        }
      };
      
      // 设置超时保护（最多等6秒）
      setTimeout(function() {
        if (app.globalData.businessDomainChecked === false) {
          console.log('业务域名检查超时，按未启用处理');
          app.globalData.businessDomainChecked = true;
          that.setData({ hideAll: false });
          that.initPage(options);
        }
      }, 6000);
      
      return;
    }
    
    // 业务域名检查已完成，没有业务域名，正常渲染
    this.initPage(options);
  },
  
  // 将原有 onLoad 的初始化逻辑提取到这里
  initPage: function(options) {
    const app = getApp();
    
    // 先设置当前的标题
    wx.setNavigationBarTitle({
      title: app.globalData.siteName
    });

    this.setData({
      themeColor: app.globalData.themeColor
    });
    
    // 注册设置加载完成回调
    app.setSettingsLoadedCallback(() => {
      // 设置加载完成后更新标题
      wx.setNavigationBarTitle({
        title: app.globalData.siteName
      });
    });
    
    // 检查授权状态
    this.checkAuthorization();
  },
  
  checkAuthorization: function() {
    const app = getApp();
    const that = this;
    
    wx.request({
      url: app.globalData.baseUrl + '/api/check_auth_status.php',
      method: 'GET',
      timeout: 5000,
      success: res => {
        console.log('首页授权检查:', res);
        if (res.statusCode === 200 && res.data) {
          if (res.data.code === 200 && res.data.data && res.data.data.authorized) {
            // 已授权，隐藏授权提醒，正常加载页面
            that.setData({ showAuthBlock: false });
            that.loadPageContent();
            // 设置 authId（从 res.data.data.id 获取）
            if (res.data.data.id) {
              app.globalData.authId = res.data.data.id;
              console.log('设置 authId:', app.globalData.authId);
            }
            // 授权检查完成后检查通知（暂时禁用）
            // that.checkNotification();
            // 停止定时检查（已授权不需要再检查）
            that.stopAuthCheckTimer();
          } else {
            // 未授权，显示授权提醒页面
            that.setData({
              showAuthBlock: true,
              authErrorMessage: res.data.message || '系统未授权，请联系管理员'
            });
            // 未授权也检查通知（访客模式）（暂时禁用）
            // that.checkNotification();
            // 启动定时检查（每 3 秒检查一次，实现实时同步）
            that.startAuthCheckTimer();
          }
        } else {
          // 响应异常，显示授权提醒页面
          that.setData({
            showAuthBlock: true,
            authErrorMessage: '系统未授权，请联系管理员'
          });
          // 启动定时检查
          that.startAuthCheckTimer();
        }
      },
      fail: err => {
        console.error('授权检查失败:', err);
        // 网络失败，显示授权提醒页面
        that.setData({
          showAuthBlock: true,
          authErrorMessage: '网络错误，无法检查授权状态'
        });
        // 启动定时检查
        that.startAuthCheckTimer();
      }
    });
  },
  
  // 启动授权状态定时检查
  startAuthCheckTimer: function() {
    const that = this;
    // 先清除之前的定时器
    that.stopAuthCheckTimer();
    // 设置新的定时器
    that.data.authCheckTimer = setInterval(function() {
      console.log('定时检查授权状态...');
      that.checkAuthorization();
    }, that.data.authCheckInterval);
  },
  
  // 停止授权状态定时检查
  stopAuthCheckTimer: function() {
    if (this.data.authCheckTimer) {
      clearInterval(this.data.authCheckTimer);
      this.data.authCheckTimer = null;
      console.log('已停止授权状态定时检查');
    }
  },
  
  // 页面卸载时清理定时器
  onUnload: function() {
    this.stopAuthCheckTimer();
  },
  
  // 页面隐藏时清理定时器
  onHide: function() {
    this.stopAuthCheckTimer();
  },
  
  // 页面显示时重新启动定时器（如果是未授权状态）
  onShow: function() {
    if (this.data.showAuthBlock) {
      this.startAuthCheckTimer();
    }
  },
  
  loadPageContent: function() {
    const app = getApp();
    
    this.getPhoneEnabled();
    this.getWechatCustomerService();
    this.getContactPhone();
    this.getCopyright();
    this.getCarousel();
    this.getDoctorsList();
    this.getDepartments();
    this.getHomeModules();
    this.applyThemeColor();
    
    // 检查登录状态
    this.checkLoginStatus();
  },
  
  checkLoginStatus: function() {
    const app = getApp();
    const that = this;
    
    // 如果设置已经加载，直接检查
    if (app.globalData.settingsLoaded) {
      that.performLoginCheck();
    } else {
      // 设置尚未加载，注册回调
      app.setSettingsLoadedCallback(function() {
        that.performLoginCheck();
      });
    }
  },
  
  performLoginCheck: function() {
    const app = getApp();
    const loginRequired = wx.getStorageSync('login_required');
    console.log('首页 - 登录控制开关状态:', loginRequired);
    console.log('首页 - app.globalData.token:', app.globalData.token);
    console.log('首页 - 本地存储 token:', wx.getStorageSync('token'));
    
    // 如果后台未启用强制登录，则不显示登录弹窗
    if (loginRequired == 0 || loginRequired === '0' || loginRequired === false) {
      console.log('首页 - 后台设置：不需要强制登录');
      return;
    }
    
    if (!app.globalData.token) {
      const storedToken = wx.getStorageSync('token');
      console.log('首页 - storedToken:', storedToken);
      if (!storedToken) {
        console.log('首页 - 没有 token，显示登录弹窗');
        // 在显示弹窗前确保主题颜色已更新
        this.setData({
          themeColor: app.globalData.themeColor
        });
        this.setData({ showLoginModal: true });
      } else {
        console.log('首页 - 从本地存储读取 token');
        app.globalData.token = storedToken;
        // 登录成功后检查就诊人
        this.checkPatientRequired();
      }
    } else {
      console.log('首页 - 已有 token，检查就诊人');
      // 已登录，检查就诊人
      this.checkPatientRequired();
    }
  },
  
  checkPatientRequired: function() {
    const patientRequired = wx.getStorageSync('patient_required');
    console.log('就诊人强制添加状态:', patientRequired);
    
    // 如果后台未启用强制添加就诊人，则不显示提示
    if (patientRequired == 0) {
      console.log('后台设置：不需要强制添加就诊人');
      return;
    }
    
    // 检查是否已有就诊人
    this.checkPatientExists();
  },
  
  checkPatientExists: function() {
    const app = getApp();
    wx.showLoading({ title: '检查就诊人...' });
    
    app.request('/api/patient/getPatients.php', {}, 'GET')
      .then((res) => {
        wx.hideLoading();
        console.log('就诊人列表:', res);
        
        if (res.code === 200 && res.data && res.data.length > 0) {
          console.log('已有就诊人');
        } else {
          // 没有就诊人，显示提示框
          this.setData({ showPatientModal: true });
        }
      })
      .catch((err) => {
        wx.hideLoading();
        console.error('检查就诊人失败:', err);
        // 如果请求失败，也显示提示框
        this.setData({ showPatientModal: true });
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
        animation: {
          duration: 400,
          timingFunc: 'easeInOut'
        }
      });
    }
  },

  getCopyright: function() {
    const copyright = wx.getStorageSync('copyright');
    if (copyright) {
      this.setData({ copyright: copyright });
    }
  },

  getPhoneEnabled: function() {
    const phoneEnabled = wx.getStorageSync('phone_enabled');
    if (phoneEnabled !== '') {
      this.setData({ phoneEnabled: phoneEnabled == 1 });
    }
  },

  getContactPhone: function() {
    const contactPhone = wx.getStorageSync('contact_phone');
    if (contactPhone) {
      this.setData({ contactPhone: contactPhone });
    }
  },

  getWechatCustomerService: function() {
    const wechatCustomerService = wx.getStorageSync('wechat_customer_service');
    if (wechatCustomerService !== '') {
      this.setData({ wechatCustomerService: wechatCustomerService == 1 });
    }
  },

  getHomeModules: function() {
    const app = getApp();
    wx.request({
      url: app.globalData.baseUrl + '/models/api/home_modules/getModules.php',
      method: 'GET',
      success: (res) => {
        if (res.data && res.data.code === 200 && res.data.data && res.data.data.length > 0) {
          const modules = res.data.data.map(module => {
            if (module.icon && !module.icon.startsWith('http://') && !module.icon.startsWith('https://')) {
              let baseUrl = app.globalData.baseUrl;
              if (baseUrl && !baseUrl.endsWith('/')) {
                baseUrl += '/';
              }
              module.icon = baseUrl + module.icon;
            }
            return module;
          });
          this.setData({ homeModules: modules });
        }
      },
      fail: () => {
        console.log('使用默认首页模块配置');
      }
    });
  },

  onShow: function() {
    const app = getApp();
    wx.setNavigationBarTitle({
      title: app.globalData.siteName
    });

    this.getPhoneEnabled();
    this.getWechatCustomerService();
    this.getContactPhone();
    this.getCopyright();
    
    // 重新应用主题颜色（确保使用最新的颜色）
    this.setData({
      themeColor: app.globalData.themeColor
    });
    
    if (app.globalData.themeColor) {
      wx.setNavigationBarColor({
        frontColor: '#ffffff',
        backgroundColor: app.globalData.themeColor,
        animation: {
          duration: 400,
          timingFunc: 'easeInOut'
        }
      });
    }

    if (typeof this.getTabBar === 'function' && this.getTabBar()) {
      this.getTabBar().setData({ selected: 0 });
    }
  },

  onPageScroll: function(e) {
    this.setData({ showBackToTop: e.scrollTop > 300 });
  },

  navigateToAppointment: function() {
    wx.switchTab({ url: '/pages/appointment/appointment' });
  },

  navigateToMyAppointment: function() {
    wx.navigateTo({ url: '/pages/appointmentList/appointmentList' });
  },

  navigateToDoctor: function() {
    wx.switchTab({ url: '/pages/doctor/doctor' });
  },

  navigateToDoctorDetail: function(e) {
    const doctorId = e.currentTarget.dataset.id;
    const doctor = this.data.doctors.find(d => d.id == doctorId);
    
    if (doctor) {
      wx.navigateTo({
        url: `/pages/doctorDetail/doctorDetail?id=${doctor.id}&name=${encodeURIComponent(doctor.name)}&title=${encodeURIComponent(doctor.title)}&description=${encodeURIComponent(doctor.credentials || doctor.description)}&specialty=${encodeURIComponent(doctor.specialty)}&avatar=${encodeURIComponent(doctor.avatar)}`
      });
    }
  },

  selectDepartment: function(e) {
    const departmentId = e.currentTarget.dataset.id;
    this.setData({
      selectedDepartmentId: departmentId,
      departmentDiseases: []
    });
    
    setTimeout(() => {
      this.getDepartmentDiseases(departmentId);
    }, 100);
  },

  getDepartments: function() {
    const app = getApp();
    app.request('/api/get_departments.php', { only_recommended: true }, 'GET')
      .then(data => {
        if (data && data.data && Array.isArray(data.data)) {
          const departments = data.data;
          this.setData({ departments: departments || [] });
          
          if (departments && departments.length > 0) {
            this.setData({ selectedDepartmentId: departments[0].id });
            this.getDepartmentDiseases(departments[0].id);
          }
        }
      })
      .catch(err => {
        console.error('Error getting departments:', err);
      });
  },

  getDepartmentDiseases: function(departmentId) {
    const app = getApp();
    this.setData({ departmentDiseases: [] });

    wx.request({
      url: app.globalData.baseUrl + '/api/get_diseases_simple.php',
      method: 'GET',
      data: {
        department_id: departmentId,
        only_recommended: true,
        timestamp: new Date().getTime()
      },
      success: (res) => {
        if (res.data && res.data.code === 200) {
          let data = res.data.data;
          if (Array.isArray(data)) {
            data = data.slice(0, 9);
            const processedDiseases = data.map(disease => {
              if (!disease.icon) {
                disease.icon = '/images/no-results.png';
              } else if (!disease.icon.startsWith('http://') && !disease.icon.startsWith('https://')) {
                let baseUrl = app.globalData.baseUrl;
                if (baseUrl && !baseUrl.endsWith('/')) {
                  baseUrl += '/';
                }
                disease.icon = baseUrl + disease.icon;
              }
              return disease;
            });
            this.setData({ departmentDiseases: processedDiseases });
          }
        }
      },
      fail: (err) => {
        console.error('Error getting department diseases:', err);
      }
    });
  },

  navigateToSearch: function() {
    wx.navigateTo({ url: '/pages/search/search' });
  },

  navigateToDiseaseDoctors: function(e) {
    const diseaseId = e.currentTarget.dataset.id;
    const departmentId = e.currentTarget.dataset.departmentId;
    
    if (diseaseId && departmentId) {
      const app = getApp();
      app.globalData.diseaseId = diseaseId;
      app.globalData.departmentId = departmentId;
      
      setTimeout(() => {
        wx.switchTab({ url: '/pages/doctor/doctor' });
      }, 100);
    } else {
      wx.showToast({ title: '参数错误', icon: 'none' });
    }
  },

  getDoctorsList: function() {
    const app = getApp();

    wx.request({
      url: app.globalData.baseUrl + '/api/Doctor/getDoctors.php',
      method: 'GET',
      success: res => {
        if (res.data.code === 200 && res.data.data) {
          const doctors = res.data.data.map(doctor => {
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
      fail: err => {
        this.useDefaultDoctors();
      }
    });
  },

  useDefaultDoctors: function() {
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

  getCarousel: function() {
    const app = getApp();
    wx.request({
      url: app.globalData.baseUrl + '/api/carousel/getCarousel.php',
      method: 'GET',
      success: (res) => {
        if (res.data && res.data.code === 200) {
          let carousel = res.data.data;
          if (Array.isArray(carousel)) {
            carousel = carousel.map(item => {
              if (item.image_url && !item.image_url.startsWith('http://') && !item.image_url.startsWith('https://')) {
                item.image_url = app.globalData.baseUrl + item.image_url;
              }
              return item;
            });
          }
          this.setData({ carousel: carousel || [] });
        } else {
          this.useDefaultCarousel();
        }
      },
      fail: () => {
        this.useDefaultCarousel();
      }
    });
  },

  useDefaultCarousel: function() {
    const defaultCarousel = [
      { id: 1, image_url: '/images/no-results.png', title: '专业医疗服务', link: '' },
      { id: 2, image_url: '/images/no-results.png', title: '专家团队', link: '' },
      { id: 3, image_url: '/images/no-results.png', title: '现代医疗环境', link: '' }
    ];
    this.setData({ carousel: defaultCarousel });
  },

  openCustomerService: function() {
    wx.openCustomerServiceChat({
      success: () => {},
      fail: () => {
        wx.showToast({ title: '请联系客服电话', icon: 'none' });
      }
    });
  },

  makePhoneCall: function() {
    wx.makePhoneCall({
      phoneNumber: this.data.contactPhone,
      fail: () => {
        wx.showToast({ title: '拨打电话失败', icon: 'none' });
      }
    });
  },

  onCarouselTap: function(e) {
    const item = e.currentTarget.dataset.item;
    if (!item.link) return;
    
    const link = item.link.trim();
    if (link.startsWith('http://') || link.startsWith('https://')) {
      wx.navigateTo({ url: '/pages/webview/webview?url=' + encodeURIComponent(link) });
    } else {
      let pagePath = link.startsWith('/') ? link : '/' + link;
      const tabbarPages = ['/pages/index/index', '/pages/appointment/appointment', '/pages/doctor/doctor', '/pages/my/my'];
      
      if (tabbarPages.includes(pagePath)) {
        wx.switchTab({ url: pagePath });
      } else {
        wx.navigateTo({ url: pagePath });
      }
    }
  },

  backToTop: function() {
    wx.pageScrollTo({ scrollTop: 0, duration: 300 });
  },

  onShareAppMessage: function() {
    const app = getApp();
    const firstCarousel = this.data.carousel && this.data.carousel.length > 0 ? this.data.carousel[0] : null;
    const shareTitle = wx.getStorageSync('share_title') || app.globalData.siteName || '医院预约挂号';
    const shareDescription = wx.getStorageSync('share_description') || '专业医疗服务，便捷预约挂号';
    const shareImage = wx.getStorageSync('share_image');
    const baseUrl = app.globalData.baseUrl;

    return {
      title: shareTitle,
      desc: shareDescription,
      path: '/pages/index/index',
      imageUrl: shareImage ? (shareImage.startsWith('http') ? shareImage : baseUrl + shareImage) : (firstCarousel?.image_url || '/images/no-results.png')
    };
  },

  onShareTimeline: function() {
    const app = getApp();
    const firstCarousel = this.data.carousel && this.data.carousel.length > 0 ? this.data.carousel[0] : null;
    const shareTitle = wx.getStorageSync('share_title') || app.globalData.siteName || '医院预约挂号';
    const shareDescription = wx.getStorageSync('share_description') || '专业医疗服务，便捷预约挂号';
    const shareImage = wx.getStorageSync('share_image');
    const baseUrl = app.globalData.baseUrl;

    return {
      title: shareTitle,
      desc: shareDescription,
      path: '/pages/index/index',
      imageUrl: shareImage ? (shareImage.startsWith('http') ? shareImage : baseUrl + shareImage) : (firstCarousel?.image_url || '/images/no-results.png')
    };
  },
  
  goToAddPatient: function() {
    this.setData({ showPatientModal: false });
    wx.navigateTo({ url: '/pages/patientForm/patientForm' });
  },
  
  refreshAuthStatus: function() {
    const that = this;
    wx.showLoading({ title: '检查授权...' });
    
    const app = getApp();
    wx.request({
      url: app.globalData.baseUrl + '/api/check_auth_status.php',
      method: 'GET',
      timeout: 5000,
      success: res => {
        wx.hideLoading();
        console.log('刷新授权检查:', res);
        if (res.statusCode === 200 && res.data) {
          if (res.data.code === 200 && res.data.data && res.data.data.authorized) {
            // 已授权，隐藏授权提醒，正常加载页面
            that.setData({ showAuthBlock: false });
            that.loadPageContent();
            wx.showToast({ title: '授权成功', icon: 'success' });
          } else {
            // 仍未授权，保持显示授权提醒
            that.setData({
              showAuthBlock: true,
              authErrorMessage: res.data.message || '系统未授权，请联系管理员'
            });
          }
        } else {
          that.setData({ showAuthBlock: false });
          that.loadPageContent();
        }
      },
      fail: err => {
        wx.hideLoading();
        console.log('刷新授权检查失败:', err);
        wx.showToast({ title: '检查失败', icon: 'none' });
      }
    });
  },
  
  // 检查通知 - 先检查全局通知（优先级更高），再检查用户级别通知
  checkNotification: function() {
    const app = getApp();
    const that = this;
    
    // 获取用户标识（从本地存储获取手机号和auth_id）
    const phone = wx.getStorageSync('contact_phone') || wx.getStorageSync('phone') || '';
    const authId = app.globalData.authId || 0;
    
    console.log('===== 检查通知 =====');
    console.log('authId:', authId);
    console.log('phone:', phone);
    
    // 先检查全局通知（优先级更高）
    console.log('先检查全局通知（优先级更高）');
    that.checkGlobalNotification(phone, authId);
  },
  
  // 检查用户级别通知
  checkUserSpecificNotification: function(authId, phone) {
    const app = getApp();
    const that = this;
    
    console.log('===== 检查用户级别通知 =====');
    console.log('authId:', authId);
    
    const apiUrl = app.globalData.baseUrl + '/license_system/api/get_user_notification.php';
    console.log('用户级别通知 API 路径:', apiUrl);
    
    wx.request({
      url: apiUrl,
      method: 'GET',
      data: {
        auth_id: authId
      },
      timeout: 10000,
      success: res => {
        console.log('获取用户级别通知成功:', res);
        if (res.statusCode === 200 && res.data) {
          console.log('用户级别通知数据:', res.data);
          if (res.data.show && res.data.notification) {
            console.log('用户级别通知已开启，显示用户级别通知');
            that.showNotification(res.data.notification, res.data.attachments || [], phone, 'user');
          } else {
            console.log('用户级别通知未开启或没有数据');
            console.log('show:', res.data.show);
            console.log('notification:', res.data.notification);
          }
        } else {
          console.log('用户级别通知 API 返回异常');
        }
      },
      fail: err => {
        console.error('获取用户级别通知失败:', err);
        console.log('错误详情:', JSON.stringify(err));
      }
    });
  },
  
  // 检查全局通知（优先级更高）
  checkGlobalNotification: function(phone, authId) {
    const app = getApp();
    const that = this;
    
    const apiUrl = app.globalData.baseUrl + '/license_system/api/get_notification.php';
    console.log('全局通知 API 路径:', apiUrl);
    console.log('Phone:', phone);
    console.log('authId:', authId);
    
    wx.request({
      url: apiUrl,
      method: 'GET',
      data: {
        source: 'miniprogram',
        phone: phone
      },
      timeout: 10000,
      success: res => {
        console.log('获取全局通知成功:', res);
        if (res.statusCode === 200 && res.data) {
          console.log('全局通知数据:', res.data);
          // 只有当 show 为 true 时才显示全局通知
          if (res.data.show && res.data.notification) {
            console.log('全局通知已开启，显示全局通知');
            that.showNotification(res.data.notification, res.data.attachments || [], phone, 'global');
          } else {
            console.log('全局通知已关闭或没有通知数据');
            console.log('show:', res.data.show);
            console.log('notification:', res.data.notification);
            // 全局通知关闭，检查用户级别通知
            if (authId && authId > 0) {
              console.log('全局通知关闭，检查用户级别通知');
              that.checkUserSpecificNotification(authId, phone);
            }
          }
        } else {
          console.log('全局通知 API 返回异常');
          // API异常，检查用户级别通知
          if (authId && authId > 0) {
            console.log('API异常，检查用户级别通知');
            that.checkUserSpecificNotification(authId, phone);
          }
        }
      },
      fail: err => {
        console.error('获取全局通知失败:', err);
        console.log('错误详情:', JSON.stringify(err));
        // 获取失败，检查用户级别通知
        if (authId && authId > 0) {
          console.log('获取全局通知失败，检查用户级别通知');
          that.checkUserSpecificNotification(authId, phone);
        }
      }
    });
  },
  
  // 显示通知弹窗
  showNotification: function(notification, attachments, phone, type) {
    const that = this;
    const hasAutoMode = notification.auto_mode || false;
    const firstDelay = notification.first_delay || 0;
    const intervalDelay = notification.interval_delay || 3600;
    
    // 获取本地存储的通知状态（用于时间控制）
    const statusKey = type + '_notification_' + notification.id + '_status';
    const statusData = wx.getStorageSync(statusKey);
    const now = Date.now() / 1000;
    
    // 检查时间控制
    let shouldShow = true;
    if (hasAutoMode) {
      if (statusData) {
        if (statusData.dismissed) {
          // 已关闭，检查间隔时间
          if (statusData.dismiss_time && (now - statusData.dismiss_time < intervalDelay)) {
            shouldShow = false;
            const delay = intervalDelay - (now - statusData.dismiss_time);
            console.log('通知已关闭，等待间隔时间:', delay, '秒');
            setTimeout(() => {
              that.checkNotification();
            }, delay * 1000);
          }
        } else {
          // 未关闭，检查首次延迟
          if (statusData.first_show_time && (now - statusData.first_show_time < firstDelay)) {
            shouldShow = false;
            const delay = firstDelay - (now - statusData.first_show_time);
            console.log('首次访问，等待延迟时间:', delay, '秒');
            setTimeout(() => {
              that.checkNotification();
            }, delay * 1000);
          }
        }
      } else {
        // 首次访问，检查首次延迟
        if (firstDelay > 0) {
          shouldShow = false;
          console.log('首次访问，等待延迟时间:', firstDelay, '秒');
          setTimeout(() => {
            that.checkNotification();
          }, firstDelay * 1000);
        }
      }
    }
    
    if (shouldShow) {
      that.setData({
        showNotificationModal: true,
        notificationData: notification,
        notificationAttachments: attachments
      });
      
      // 更新本地存储状态
      const newStatus = statusData || {
        first_show_time: now,
        last_show_time: now,
        dismissed: false
      };
      newStatus.last_show_time = now;
      if (!newStatus.first_show_time) {
        newStatus.first_show_time = now;
      }
      wx.setStorageSync(statusKey, newStatus);
      
      // 标记通知已显示（仅已登录用户）
      if (phone) {
        that.markNotificationShown(notification.id, phone);
      }
    }
  },
  
  // 未登录访客的通知检查（使用本地存储）
  checkNotificationForGuest: function() {
    const app = getApp();
    const that = this;
    
    wx.request({
      url: app.globalData.baseUrl + '/license_system/api/get_notification.php',
      method: 'GET',
      data: {
        source: 'miniprogram'
      },
      success: res => {
        console.log('访客获取通知:', res);
        if (res.statusCode === 200 && res.data) {
          if (res.data.show && res.data.notification) {
            // 获取本地存储的通知状态
            const notificationId = res.data.notification.id;
            const statusKey = 'notification_' + notificationId + '_status';
            const statusData = wx.getStorageSync(statusKey);
            
            if (statusData) {
              const now = Date.now() / 1000;
              const firstDelay = res.data.notification.first_delay || 900;
              const intervalDelay = res.data.notification.interval_delay || 3600;
              
              if (statusData.dismissed) {
                // 已关闭，检查间隔时间
                if (statusData.dismiss_time && (now - statusData.dismiss_time < intervalDelay)) {
                  console.log('访客通知：已关闭，等待间隔时间');
                  const delay = intervalDelay - (now - statusData.dismiss_time);
                  setTimeout(() => {
                    that.checkNotificationForGuest();
                  }, delay * 1000);
                  return;
                }
              } else {
                // 未关闭，检查首次延迟
                if (statusData.first_show_time && (now - statusData.first_show_time < firstDelay)) {
                  console.log('访客通知：首次访问，等待延迟时间');
                  const delay = firstDelay - (now - statusData.first_show_time);
                  setTimeout(() => {
                    that.checkNotificationForGuest();
                  }, delay * 1000);
                  return;
                }
              }
            }
            
            // 显示通知
            that.setData({
              showNotificationModal: true,
              notificationData: res.data.notification,
              notificationAttachments: res.data.attachments || []
            });
            
            // 更新本地存储状态
            const newStatus = statusData || {
              first_show_time: Date.now() / 1000,
              last_show_time: Date.now() / 1000,
              dismissed: false
            };
            newStatus.last_show_time = Date.now() / 1000;
            wx.setStorageSync(statusKey, newStatus);
          }
        }
      },
      fail: err => {
        console.log('访客获取通知失败:', err);
      }
    });
  },
  
  // 标记通知已显示
  markNotificationShown: function(notificationId, phone = '') {
    const app = getApp();
    wx.request({
      url: app.globalData.baseUrl + '/license_system/api/mark_notification_shown.php',
      method: 'POST',
      data: {
        notification_id: notificationId,
        phone: phone
      }
    });
  },
  
  // 关闭通知弹窗
  closeNotification: function() {
    this.setData({
      showNotificationModal: false
    });
    // 标记用户已关闭通知
    const notificationId = this.data.notificationData?.id;
    const phone = wx.getStorageSync('contact_phone') || wx.getStorageSync('phone') || '';
    
    if (notificationId) {
      if (phone) {
        // 已登录用户：调用 API
        this.dismissNotification(notificationId, phone);
      } else {
        // 未登录访客：更新本地存储
        const statusKey = 'notification_' + notificationId + '_status';
        const statusData = wx.getStorageSync(statusKey) || {};
        statusData.dismissed = true;
        statusData.dismiss_time = Date.now() / 1000;
        wx.setStorageSync(statusKey, statusData);
      }
    }
  },
  
  // 标记通知已关闭
  dismissNotification: function(notificationId, phone = '') {
    const app = getApp();
    wx.request({
      url: app.globalData.baseUrl + '/license_system/api/close_notification.php',
      method: 'POST',
      data: {
        notification_id: notificationId,
        phone: phone
      }
    });
  },
  
  // 打开下载链接
  openDownloadUrl: function() {
    const url = this.data.notificationData?.download_url;
    if (url) {
      wx.showModal({
        title: '下载提醒',
        content: '即将打开下载链接',
        success: (res) => {
          if (res.confirm) {
            wx.navigateTo({
              url: '/pages/webview/webview?url=' + encodeURIComponent(url)
            });
          }
        }
      });
    }
  },
  
  // 预览附件
  previewAttachment: function(e) {
    const url = e.currentTarget.dataset.url;
    const name = e.currentTarget.dataset.name;
    
    wx.showLoading({ title: '加载中...' });
    
    wx.downloadFile({
      url: url,
      success: (res) => {
        wx.hideLoading();
        if (res.statusCode === 200) {
          wx.openDocument({
            filePath: res.tempFilePath,
            fileType: this.getFileType(name),
            success: () => {
              console.log('打开文档成功');
            },
            fail: (err) => {
              console.log('打开文档失败:', err);
              wx.showToast({ title: '无法打开此文件', icon: 'none' });
            }
          });
        }
      },
      fail: (err) => {
        wx.hideLoading();
        console.log('下载文件失败:', err);
        wx.showToast({ title: '下载失败', icon: 'none' });
      }
    });
  },
  
  // 获取文件类型
  getFileType: function(filename) {
    const ext = filename.split('.').pop().toLowerCase();
    const typeMap = {
      'pdf': 'pdf',
      'doc': 'doc', 'docx': 'doc',
      'ppt': 'ppt', 'pptx': 'ppt',
      'xls': 'xls', 'xlsx': 'xls',
      'jpg': 'jpg', 'jpeg': 'jpg', 'png': 'jpg', 'gif': 'jpg', 'bmp': 'jpg',
      'mp3': 'mp3', 'wav': 'mp3',
      'mp4': 'mp4', 'mov': 'mp4', 'avi': 'mp4'
    };
    return typeMap[ext] || 'pdf';
  }
})