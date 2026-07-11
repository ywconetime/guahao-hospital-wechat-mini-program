// doctorDetail.js
Page({
  data: {
    doctor: {
      id: 1,
      name: '宋琼',
      title: '院长/临床工作20多年',
      position: '沈阳附医北方医院名誉院长',
      desc: '对月经不调、妇科炎症、宫颈疾病等常见妇科疾病的诊治具有丰富临床经验，为患者提供个体化诊疗方案。擅长计划生育手术，妇科微创手术等多种术式。',
      avatar: '/images/no-results.png'
    },
    themeColor: '#007AFF',
    schedules: [],
    loading: false
  },
  
  onLoad(options) {
    // 页面加载时获取主题颜色
    this.setThemeColor();
    
    // 确保options不为null
    options = options || {};
    
    const doctorId = options.id || 1;
    const doctorName = decodeURIComponent(options.name || '');
    const doctorTitle = decodeURIComponent(options.title || '');
    const doctorDescription = decodeURIComponent(options.description || '');
    const doctorSpecialty = decodeURIComponent(options.specialty || '');
    const doctorAvatar = decodeURIComponent(options.avatar || '');
    
    console.log('医生参数:', options);
    console.log('解码后的名称:', doctorName);
    console.log('解码后的描述:', doctorDescription);
    console.log('解码后的专长:', doctorSpecialty);
    console.log('解码后的头像:', doctorAvatar);
    
    // 确保doctor对象有完整的属性（使用URL参数作为初始值）
    const initialDoctor = {
      id: doctorId,
      name: doctorName || '未知医生',
      title: doctorTitle || '未知职称',
      position: doctorDescription || '未知职位',
      desc: doctorSpecialty || '暂无专长信息',
      avatar: doctorAvatar || '/images/no-results.png'
    };
    
    console.log('初始医生数据:', initialDoctor);
    
    // 更新医生信息（先显示URL参数的数据）
    this.setData({
      doctor: initialDoctor
    });
    
    // 从后台API获取真实的医生详情（覆盖URL参数，确保数据准确）
    this.loadDoctorDetail(doctorId);
    
    // 从后台API获取真实的排班信息
    this.loadDoctorSchedules(doctorId);
  },
  
  // 从后台API获取排班信息
  loadDoctorSchedules(doctorId) {
    const app = getApp();
    this.setData({ loading: true });
    
    wx.request({
      url: app.globalData.baseUrl + '/api/get_doctor_schedules.php',
      method: 'GET',
      data: {
        doctor_id: doctorId
      },
      success: (res) => {
        console.log('获取排班数据:', res.data);
        
        if (res.data && res.data.success && res.data.data) {
          const schedules = res.data.data.map(item => {
            // 检查该日期的所有时段是否都停诊
            const allSuspended = item.timeSlots && item.timeSlots.every(slot => slot.isSuspended);
            
            return {
              date: item.date,
              week: this.getWeekday(item.date),
              timeSlots: item.timeSlots || [],
              allSuspended: allSuspended
            };
          });
          
          this.setData({
            schedules: schedules
          });
        } else {
          // 如果API没有返回数据，生成默认的排班信息
          this.generateDefaultSchedules();
        }
      },
      fail: (err) => {
        console.error('获取医生排班失败:', err);
        // 请求失败时，生成默认的排班信息
        this.generateDefaultSchedules();
      },
      complete: () => {
        this.setData({ loading: false });
      }
    });
  },
  
  // 生成默认排班信息（当API不可用时使用）
  generateDefaultSchedules() {
    const schedules = [];
    const today = new Date();
    
    for (let i = 1; i <= 7; i++) {
      const date = new Date(today);
      date.setDate(today.getDate() + i);
      
      const dateStr = date.toISOString().split('T')[0];
      const weekday = this.getWeekday(dateStr);
      
      const timeSlots = [
        { time: '上午', count: 0, isSuspended: false },
        { time: '下午', count: 0, isSuspended: false }
      ];
      
      schedules.push({
        date: dateStr,
        week: weekday,
        timeSlots: timeSlots,
        allSuspended: false
      });
    }
    
    this.setData({
      schedules: schedules
    });
  },
  
  loadDoctorDetail(doctorId) {
    const app = getApp();
    console.log('开始从API获取医生详情，医生ID:', doctorId);
    
    wx.request({
      url: app.globalData.baseUrl + '/api/Doctor/getDoctorDetail.php',
      method: 'GET',
      data: {
        doctor_id: doctorId
      },
      success: (res) => {
        console.log('获取医生详情响应:', res);
        
        if (res.data && res.data.code === 200 && res.data.data) {
          const doctorData = res.data.data;
          let avatar = doctorData.avatar;
          
          // 处理头像URL
          if (!avatar) {
            avatar = '/images/no-results.png';
          } else if (!avatar.startsWith('http://') && !avatar.startsWith('https://')) {
            let baseUrl = app.globalData.baseUrl;
            if (baseUrl && !baseUrl.endsWith('/')) {
              baseUrl += '/';
            }
            avatar = baseUrl + avatar;
          }
          
          const doctor = {
            id: doctorData.id || doctorId,
            name: doctorData.name || '未知医生',
            title: doctorData.title || '未知职称',
            position: doctorData.description || doctorData.position || '未知职位',
            desc: doctorData.specialty || '暂无专长信息',
            avatar: avatar
          };
          
          console.log('从API获取的医生数据:', doctor);
          
          // 更新医生信息
          this.setData({
            doctor: doctor
          });
        } else {
          console.log('API返回数据格式不正确，保留URL参数的数据');
        }
      },
      fail: (err) => {
        console.error('获取医生详情失败:', err);
        // 请求失败时保留URL参数的数据，不做修改
      }
    });
  },
  
  // 获取星期几
  getWeekday(dateStr) {
    const date = new Date(dateStr);
    const weekdays = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'];
    return weekdays[date.getDay()];
  },
  
  // 跳转到预约挂号页面
  goToAppointmentForm(e) {
    const doctorId = e.currentTarget.dataset.doctorId || this.data.doctor.id;
    const date = e.currentTarget.dataset.date;
    const timeSlot = e.currentTarget.dataset.timeSlot || '';
    const doctor = this.data.doctor;
    
    let url = `/pages/appointmentForm/appointmentForm?doctor_id=${doctorId}&date=${date}&name=${encodeURIComponent(doctor.name)}&title=${encodeURIComponent(doctor.title)}`;
    if (timeSlot) {
      url += `&time_slot=${timeSlot}`;
    }
    
    wx.navigateTo({
      url: url
    });
  },

  // 分享功能
  onShareAppMessage: function() {
    const app = getApp();
    const doctor = this.data.doctor;
    // 从本地存储获取分享设置
    const shareTitle = wx.getStorageSync('share_title') || app.globalData.siteName || '厦门元火医疗自助挂号';
    const shareDescription = wx.getStorageSync('share_description') || `${doctor.name} - ${doctor.title}`;
    const shareImage = wx.getStorageSync('share_image');
    const baseUrl = app.globalData.baseUrl;
    
    return {
      title: shareTitle,
      desc: shareDescription,
      path: `/pages/doctorDetail/doctorDetail?id=${doctor.id}&name=${encodeURIComponent(doctor.name)}&title=${encodeURIComponent(doctor.title)}`,
      imageUrl: shareImage ? (shareImage.startsWith('http') ? shareImage : baseUrl + shareImage) : (doctor.avatar || '/images/no-results.png'),
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
    const doctor = this.data.doctor;
    // 从本地存储获取分享设置
    const shareTitle = wx.getStorageSync('share_title') || app.globalData.siteName || '厦门元火医疗自助挂号';
    const shareDescription = wx.getStorageSync('share_description') || `${doctor.name} - ${doctor.title}`;
    const shareImage = wx.getStorageSync('share_image');
    const baseUrl = app.globalData.baseUrl;
    
    return {
      title: shareTitle,
      desc: shareDescription,
      path: `/pages/doctorDetail/doctorDetail?id=${doctor.id}&name=${encodeURIComponent(doctor.name)}&title=${encodeURIComponent(doctor.title)}`,
      imageUrl: shareImage ? (shareImage.startsWith('http') ? shareImage : baseUrl + shareImage) : (doctor.avatar || '/images/no-results.png'),
      success: function(res) {
        console.log('分享到朋友圈成功:', res);
      },
      fail: function(res) {
        console.log('分享到朋友圈失败:', res);
      }
    };
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
          wx.setNavigationBarColor({
            frontColor: '#ffffff',
            backgroundColor: themeColor,
            animation: {
              duration: 400,
              timingFunc: 'easeInOut'
            }
          });
        }
      },
      fail: err => {
        console.error('获取主题颜色失败:', err);
        // 失败时使用本地存储或默认值
        let themeColor = '#007AFF';
        const storedThemeColor = wx.getStorageSync('themeColor');
        if (storedThemeColor) {
          themeColor = storedThemeColor;
          app.globalData.themeColor = storedThemeColor;
        } else if (app.globalData.themeColor) {
          themeColor = app.globalData.themeColor;
        }
        this.setData({ themeColor: themeColor });
        // 更新导航栏颜色
        wx.setNavigationBarColor({
          frontColor: '#ffffff',
          backgroundColor: themeColor,
          animation: {
            duration: 400,
            timingFunc: 'easeInOut'
          }
        });
      }
    });
  },
  
  // 页面显示时
  onShow() {
    // 重新获取主题颜色
    this.setThemeColor();
  }
})