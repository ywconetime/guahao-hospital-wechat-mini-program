// appointmentList.js
Page({
  data: {
    appointments: [],
    themeColor: '#007AFF'
  },
  
  onLoad() {
    // 页面加载时获取主题颜色
    this.setThemeColor();
    // 页面加载时获取用户预约列表
    this.loadAppointments();
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
  
  onShow() {
    // 重新获取主题颜色
    this.setThemeColor();
    // 页面显示时重新获取用户预约列表，确保数据与后台同步
    this.loadAppointments();
  },
  
  loadAppointments() {
    console.log('loadAppointments');
    
    const app = getApp();
    
    console.log('Current token:', app.globalData.token);
    
    // 检查是否已登录
    if (!app.globalData.token) {
      console.log('User not logged in, showing empty list');
      // 未登录状态，显示空列表
      this.setData({
        appointments: []
      });
      // 清除本地存储中的预约记录
      wx.removeStorageSync('appointments');
      console.log('Appointments cleared from local storage');
      return;
    }
    
    // 从后端API获取预约列表
    app.request(`/api/appointment/getUserAppointments.php`, {
      token: app.globalData.token
    })
      .then(res => {
        console.log('Appointments from API:', res);
        const data = res.data || [];
        
        // 转换状态显示
        const appointments = data.map(item => {
          let statusText = '待确认到诊';
          if (item.status === 'confirmed') {
            statusText = '已确认到诊';
          } else if (item.status === 'completed') {
            statusText = '已完成到诊';
          } else if (item.status === 'cancelled') {
            statusText = '已取消到诊';
          }
          
          return {
            id: item.id,
            orderId: item.order_id || '',
            order_id: item.order_id || '',
            status: statusText,
            doctorName: item.doctor_name || '',
            date: item.appointment_time ? this.getDateOnly(item.appointment_time) : '',
            timeSlot: item.appointment_time ? this.getTimeSlot(item.appointment_time) : '',
            diseaseName: item.disease_name || '',
            disease_name: item.disease_name || '',
            patientName: item.patient_name || '未设置',
            patientGender: item.patient_gender || '未知',
            patientAge: item.patient_age || '未知',
            patientPhone: item.patient_phone || '未设置',
            createTime: item.created_at || ''
          };
        });
        
        // 更新本地存储，保存完整的预约信息
        wx.setStorage({ key: 'appointments', data: appointments });
        console.log('Appointments stored to local storage:', appointments);
        
        // 如果没有预约数据，显示空列表
        if (appointments.length === 0) {
          console.log('No appointments found, showing empty list');
        }
        
        this.setData({
          appointments: appointments
        });
        console.log('Appointments set to data:', appointments);
      })
      .catch(err => {
        console.error('Error getting appointments from API:', err);
        wx.showToast({ title: '获取预约列表失败', icon: 'none' });
        
        // 未登录状态，显示空列表
        this.setData({
          appointments: []
        });
        // 清除本地存储中的预约记录
        wx.removeStorageSync('appointments');
        console.log('Appointments cleared from local storage due to API error');
      });
  },
  
  // 查看详情
  viewDetail(e) {
    const appointmentId = e.currentTarget.dataset.id;
    const appointment = this.data.appointments.find(item => item.id == appointmentId);
    if (appointment) {
      // 将当前预约信息存储到本地存储，供详情页面使用
      wx.setStorage({ key: 'currentAppointment', data: appointment });
      // 跳转到挂号详情页面，只传递预约 ID
      wx.navigateTo({
        url: `/pages/appointmentDetail/appointmentDetail?id=${appointmentId}`
      });
    } else {
      wx.showToast({ title: '未找到预约信息', icon: 'none' });
    }
  },
  
  // 取消预约
  cancelAppointment(e) {
    const appointmentId = e.currentTarget.dataset.id;
    const appointment = this.data.appointments.find(item => item.id == appointmentId);
    
    wx.showModal({
      title: '取消预约',
      content: '确定要取消这个预约吗？',
      success: (res) => {
        if (res.confirm) {
          // 调用后端API取消预约
          const app = getApp();
          app.request(`/api/appointment/cancelAppointment.php`, {
            appointment_id: appointmentId,
            token: app.globalData.token
          })
            .then(res => {
              wx.showToast({ title: '预约已取消', icon: 'success' });
              
              // 从本地存储中移除对应的预约记录
              wx.getStorage({ 
                key: 'appointments', 
                success: (res) => {
                  let appointments = res.data || [];
                  // 过滤掉当前预约
                  appointments = appointments.filter(item => item.id != appointmentId);
                  // 更新本地存储
                  wx.setStorage({ key: 'appointments', data: appointments });
                  // 重新加载预约列表
                  this.loadAppointments();
                },
                fail: () => {
                  // 如果本地存储操作失败，直接重新加载预约列表
                  this.loadAppointments();
                }
              });
            })
            .catch(err => {
              wx.showToast({ title: err, icon: 'none' });
            });
        }
      }
    });
  },
  
  // 从预约时间中提取日期部分，只显示年月日
  getDateOnly(appointmentTime) {
    if (!appointmentTime) return '';
    
    // 尝试从时间字符串中提取日期部分
    const dateRegex = /\d{4}-\d{2}-\d{2}/;
    const match = appointmentTime.match(dateRegex);
    if (match) {
      return match[0];
    }
    
    return appointmentTime;
  },
  
  // 根据预约时间获取时段
  getTimeSlot(appointmentTime) {
    if (!appointmentTime) return '';
    
    // 检查是否包含"上午"或"下午"字符串
    if (appointmentTime.includes('上午')) return '上午';
    if (appointmentTime.includes('下午')) return '下午';
    
    // 尝试从时间字符串中提取小时
    const timeRegex = /\d{2}:\d{2}:\d{2}/;
    const match = appointmentTime.match(timeRegex);
    if (match) {
      const timeStr = match[0];
      const hour = parseInt(timeStr.split(':')[0]);
      if (hour >= 6 && hour < 12) return '上午';
      if (hour >= 12 && hour < 18) return '下午';
      if (hour >= 18) return '晚上';
    }
    
    return '';
  }
})