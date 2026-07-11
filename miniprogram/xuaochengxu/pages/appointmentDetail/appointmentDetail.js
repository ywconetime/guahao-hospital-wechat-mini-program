// appointmentDetail.js
Page({
  data: {
    appointment: {
      doctorName: '',
      date: '',
      timeSlot: '',
      diseaseName: '',
      patientName: '',
      patientGender: '',
      patientAge: '',
      patientPhone: '',
      orderId: '',
      createTime: '',
      status: ''
    },
    themeColor: '#007AFF'
  },
  
  onLoad(options) {
    // 页面加载时获取主题颜色
    this.setThemeColor();
    const appointmentId = options.id;
    if (appointmentId) {
      // 先尝试从本地存储中获取完整的预约信息
      wx.getStorage({
        key: 'appointments',
        success: (res) => {
          const appointments = res.data || [];
          const localAppointment = appointments.find(item => item.id == appointmentId);
          if (localAppointment) {
            console.log('Appointment from local storage:', localAppointment);
            // 处理日期和时段
            const processedAppointment = {
              ...localAppointment,
              date: localAppointment.date ? this.getDateOnly(localAppointment.date) : '',
              timeSlot: localAppointment.timeSlot || '',
              diseaseName: localAppointment.disease_name || localAppointment.diseaseName || '',
              orderId: localAppointment.order_id || localAppointment.orderId || ''
            };
            this.setData({ appointment: processedAppointment });
          } else {
            // 本地存储中没有，从后端API获取
            this.getAppointmentFromApi(appointmentId);
          }
        },
        fail: () => {
          // 本地存储读取失败，从后端API获取
          this.getAppointmentFromApi(appointmentId);
        }
      });
    } else {
      // 从本地存储中读取预约数据（兼容旧版本）
      wx.getStorage({ 
        key: 'currentAppointment', 
        success: (res) => {
          const appointment = res.data;
          console.log('Appointment from local storage:', appointment);
          // 处理日期和时段
          const processedAppointment = {
            ...appointment,
            date: appointment.date ? this.getDateOnly(appointment.date) : '',
            timeSlot: appointment.timeSlot || ''
          };
          this.setData({ appointment: processedAppointment });
        },
        fail: (err) => {
          console.error('Error getting appointment from local storage:', err);
          // 使用默认模拟数据
          this.setData({
            appointment: {
              doctorName: '宋琼',
              date: '2026-03-19',
              timeSlot: '下午',
              patientName: '老薛',
              patientGender: '女',
              patientAge: '40',
              patientPhone: '13542141589',
              orderId: '20260319452016',
              createTime: '2026-03-19 00:53:57',
              status: '已预约,未就诊'
            }
          });
        }
      });
    }
  },
  
  // 从API获取预约详情
  getAppointmentFromApi(appointmentId) {
    const app = getApp();
    app.request(`/appointment/getAppointmentDetail`, { appointment_id: appointmentId })
      .then(data => {
        console.log('Appointment from API:', data);
        // 转换状态显示
          let statusText = '待确认到诊';
          if (data.status === 'confirmed') {
            statusText = '已确认到诊';
          } else if (data.status === 'completed') {
            statusText = '已完成到诊';
          } else if (data.status === 'cancelled') {
            statusText = '已取消到诊';
          }
        
        const appointment = {
          id: data.id,
          doctorName: data.doctor_name || '',
          date: data.appointment_time ? this.getDateOnly(data.appointment_time) : '',
          timeSlot: data.appointment_time ? this.getTimeSlot(data.appointment_time) : '',
          diseaseName: data.disease_name || '',
          patientName: data.patient_name || '未设置',
          patientGender: data.patient_gender || '未知',
          patientAge: data.patient_age || '未知',
          patientPhone: data.patient_phone || '未设置',
          orderId: data.order_id || '',
          createTime: data.created_at || '',
          status: statusText
        };
        this.setData({ appointment });
      })
      .catch(err => {
        console.error('Error getting appointment from API:', err);
        wx.showToast({ title: '获取预约详情失败', icon: 'none' });
        // 使用默认模拟数据
        this.setData({
          appointment: {
            doctorName: '宋琼',
            date: '2026-03-19',
            timeSlot: '下午',
            patientName: '老薛',
            patientGender: '女',
            patientAge: '40',
            patientPhone: '13542141589',
            orderId: '20260319452016',
            createTime: '2026-03-19 00:53:57',
            status: '已预约,未就诊'
          }
        });
      });
  },
  
  // 取消预约
  cancelAppointment() {
    wx.showModal({
      title: '取消预约',
      content: '确定要取消这个预约吗？',
      success: (res) => {
        if (res.confirm) {
          // 调用后端API取消预约
          const app = getApp();
          const appointmentId = this.data.appointment.id || 1; // 实际项目中应该使用真实的预约ID
          app.request(`/api/appointment/cancelAppointment.php`, { appointment_id: appointmentId, token: app.globalData.token })
            .then(res => {
              wx.showToast({ title: '预约已取消', icon: 'success' });
              
              // 从本地存储中移除对应的预约记录
              wx.getStorage({ 
                key: 'appointments', 
                success: (res) => {
                  let appointments = res.data || [];
                  // 过滤掉当前预约（使用id或orderId）
                  appointments = appointments.filter(item => 
                    item.id !== this.data.appointment.id && 
                    item.orderId !== this.data.appointment.orderId
                  );
                  // 更新本地存储
                  wx.setStorage({ key: 'appointments', data: appointments });
                }
              });
              
              // 跳转到预约列表页面
              setTimeout(() => {
                wx.navigateTo({
                  url: '/pages/appointmentList/appointmentList'
                });
              }, 1500);
            })
            .catch(err => {
              wx.showToast({ title: err, icon: 'none' });
            });
        }
      }
    });
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
  onShow() {
    // 重新获取主题颜色
    this.setThemeColor();
  }
})