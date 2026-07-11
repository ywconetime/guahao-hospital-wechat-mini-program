// search.js
Page({
  data: {
    keyword: '',
    showResults: false,
    doctors: [],
    diseases: [],
    history: [],
    hotKeywords: ['妇科炎症', '月经不调', '不孕不育', '产前检查', '妇科肿瘤'],
    copyright: '© 2026 厦门元火妇科男科医院', // 默认版权信息
    themeColor: '#007AFF'
  },
  
  onLoad: function(options) {
    // 页面加载时，获取搜索历史
    this.getSearchHistory();
    // 获取版权信息
    this.getCopyright();
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
  
  // 获取版权信息
  getCopyright: function() {
    const copyright = wx.getStorageSync('copyright');
    if (copyright) {
      this.setData({
        copyright: copyright
      });
    }
  },
  
  // 获取搜索历史
  getSearchHistory: function() {
    const history = wx.getStorageSync('searchHistory') || [];
    this.setData({
      history: history
    });
  },
  
  // 输入关键词
  onInput: function(e) {
    this.setData({
      keyword: e.detail.value
    });
  },
  
  // 清除关键词
  clearKeyword: function() {
    this.setData({
      keyword: ''
    });
  },
  
  // 取消搜索
  onCancel: function() {
    wx.navigateBack();
  },
  
  // 执行搜索
  onSearch: function() {
    const keyword = this.data.keyword.trim();
    if (!keyword) return;
    
    // 保存搜索历史
    this.saveSearchHistory(keyword);
    
    // 显示加载状态
    wx.showLoading({
      title: '搜索中...',
    });
    
    // 调用搜索 API
    this.search(keyword);
  },
  
  // 保存搜索历史
  saveSearchHistory: function(keyword) {
    let history = wx.getStorageSync('searchHistory') || [];
    // 移除重复的关键词
    history = history.filter(item => item !== keyword);
    // 添加到历史记录的开头
    history.unshift(keyword);
    // 只保留最近 10 条记录
    history = history.slice(0, 10);
    // 保存到本地存储
    wx.setStorageSync('searchHistory', history);
    // 更新页面数据
    this.setData({
      history: history
    });
  },
  
  // 清除搜索历史
  clearHistory: function() {
    wx.removeStorageSync('searchHistory');
    this.setData({
      history: []
    });
  },
  
  // 搜索历史点击
  searchHistory: function(e) {
    const keyword = e.currentTarget.dataset.keyword;
    this.setData({
      keyword: keyword
    });
    this.onSearch();
  },
  
  // 热门搜索点击
  searchHot: function(e) {
    const keyword = e.currentTarget.dataset.keyword;
    this.setData({
      keyword: keyword
    });
    this.onSearch();
  },
  
  // 搜索 API
  search: function(keyword) {
    const app = getApp();
    
    // 同时搜索医生和病种
    Promise.all([
      this.searchDoctors(keyword),
      this.searchDiseases(keyword)
    ]).then(([doctors, diseases]) => {
      wx.hideLoading();
      this.setData({
        showResults: true,
        doctors: doctors,
        diseases: diseases
      });
    }).catch(err => {
      wx.hideLoading();
      console.error('搜索失败:', err);
      wx.showToast({
        title: '网络错误，请稍后重试',
        icon: 'none'
      });
      // 使用本地模拟数据
      this.useMockData(keyword);
    });
  },
  
  // 搜索医生
  searchDoctors: function(keyword) {
    const app = getApp();
    return new Promise((resolve, reject) => {
      wx.request({
        url: app.globalData.baseUrl + '/api/Doctor/getDoctors.php',
        method: 'GET',
        data: {
          keyword: keyword
        },
        success: res => {
          console.log('搜索医生API返回数据:', res);
          if (res.data.code === 200 && res.data.data) {
            console.log('原始医生数据:', res.data.data);
            // 过滤医生数据
            const filteredDoctors = res.data.data.filter(doctor => {
              console.log('医生数据:', doctor);
              return doctor.name.includes(keyword) || 
                doctor.title.includes(keyword) || 
                doctor.department.includes(keyword) || 
                (doctor.specialty && doctor.specialty.includes(keyword)) || 
                (doctor.description && doctor.description.includes(keyword));
            });
            console.log('过滤后的医生数据:', filteredDoctors);
            
            // 为没有头像的医生添加默认头像
            const doctorsWithAvatars = filteredDoctors.map(doctor => {
              const app = getApp();
              if (!doctor.avatar || doctor.avatar === '') {
                console.log('为医生添加默认头像:', doctor.name);
                doctor.avatar = `../../images/default-doctor.png`;
              } else if (!doctor.avatar.startsWith('http://') && !doctor.avatar.startsWith('https://')) {
                // 如果是相对路径，添加基础URL
                let baseUrl = app.globalData.baseUrl;
                if (baseUrl && !baseUrl.endsWith('/')) {
                  baseUrl += '/';
                }
                doctor.avatar = baseUrl + doctor.avatar;
              }
              return doctor;
            });
            console.log('处理后的医生数据:', doctorsWithAvatars);
            resolve(doctorsWithAvatars);
          } else {
            resolve([]);
          }
        },
        fail: err => {
          reject(err);
        }
      });
    });
  },
  
  // 搜索病种
  searchDiseases: function(keyword) {
    const app = getApp();
    return new Promise((resolve, reject) => {
      wx.request({
        url: app.globalData.baseUrl + '/api/get_diseases.php',
        method: 'GET',
        data: {
          keyword: keyword
        },
        success: res => {
          if (res.data.code === 200 && res.data.data) {
            // 过滤病种数据
            const filteredDiseases = res.data.data.filter(disease => 
              disease.name.includes(keyword)
            );
            
            // 处理病种图标路径
            const diseasesWithIcons = filteredDiseases.map(disease => {
              if (!disease.icon) {
                // 如果没有图标，使用默认图标
                disease.icon = '/images/no-results.png';
              } else if (!disease.icon.startsWith('http://') && !disease.icon.startsWith('https://')) {
                // 如果是相对路径，添加基础URL
                let baseUrl = app.globalData.baseUrl;
                if (baseUrl && !baseUrl.endsWith('/')) {
                  baseUrl += '/';
                }
                disease.icon = baseUrl + disease.icon;
              }
              return disease;
            });
            
            resolve(diseasesWithIcons);
          } else {
            resolve([]);
          }
        },
        fail: err => {
          reject(err);
        }
      });
    });
  },
  
  // 使用本地模拟数据（已移除测试数据）
  useMockData: function(keyword) {
    // 模拟医生数据 - 从API获取，此处为空
    const mockDoctors = [];
    
    // 模拟病种数据
    const mockDiseases = [
      {
        id: 1,
        name: '月经不调',
        icon: '/images/no-results.png'
      },
      {
        id: 2,
        name: '妇科疾病',
        icon: '/images/no-results.png'
      },
      {
        id: 3,
        name: '不孕不育',
        icon: '/images/no-results.png'
      },
      {
        id: 4,
        name: '妇科炎症',
        icon: '/images/no-results.png'
      },
      {
        id: 5,
        name: '私密整形',
        icon: '/images/no-results.png'
      },
      {
        id: 6,
        name: '计划生育',
        icon: '/images/no-results.png'
      },
      {
        id: 7,
        name: '宫颈疾病',
        icon: '/images/no-results.png'
      },
      {
        id: 8,
        name: '妇科微创',
        icon: '/images/no-results.png'
      }
    ];
    
    // 过滤数据并重置键名
    const filteredDoctors = Array.from(mockDoctors.filter(doctor => 
      doctor.name.includes(keyword) || 
      doctor.title.includes(keyword) || 
      doctor.department.includes(keyword) || 
      doctor.specialty.includes(keyword) || 
      doctor.description.includes(keyword)
    ));
    
    const filteredDiseases = Array.from(mockDiseases.filter(disease => 
      disease.name.includes(keyword)
    ));
    
    this.setData({
      showResults: true,
      doctors: filteredDoctors,
      diseases: filteredDiseases
    });
  },
  
  // 跳转到医生详情页面
  navigateToDoctorDetail: function(e) {
    const doctorId = e.currentTarget.dataset.id;
    const doctor = this.data.doctors.find(d => d.id == doctorId);
    if (doctor) {
      wx.navigateTo({
        url: `/pages/doctorDetail/doctorDetail?id=${doctor.id}&name=${encodeURIComponent(doctor.name)}&title=${encodeURIComponent(doctor.title)}&description=${encodeURIComponent(doctor.description || '')}&specialty=${encodeURIComponent(doctor.specialty || '')}&avatar=${encodeURIComponent(doctor.avatar || '')}`
      });
    }
  },
  
  // 跳转到预约页面
  navigateToAppointment: function(e) {
    const disease = e.currentTarget.dataset.disease;
    wx.switchTab({
      url: `/pages/appointment/appointment?disease=${encodeURIComponent(disease)}`
    });
  },
  
  // 页面显示
  onShow: function() {
    // 获取最新的版权信息
    this.getCopyright();
    // 重新获取主题颜色
    this.setThemeColor();
  }
});