// appointmentForm.js
Page({
  data: {
    doctors: [],
    selectedDoctorId: '',
    selectedDoctorName: '',
    selectedDoctor: null,
    selectedDoctorIndex: 0,
    isLoadingDoctors: false,
    dateTime: '',
    timeSlot: '',
    schedules: [],
    selectedScheduleId: null,
    patient: {
      phone: '',
      name: '',
      gender: '男',
      age: ''
    },
    symptoms: '',
    diseases: [],
    selectedDiseaseId: '',
    selectedDiseaseName: '',
    selectedDisease: null,
    selectedDiseaseIndex: 0,
    patients: [],
    patientOptions: [],
    selectedPatientId: '',
    selectedPatientIndex: 0,
    themeColor: '#007AFF',
    themeColorLight: '#e6f7ff',
    themeColorDark: '#0056b3',
    morningSuspended: false,
    afternoonSuspended: false,
    isSubmitting: false  // 防止重复提交的标志
  },
  
  onLoad(options) {
    // 获取医生列表（始终加载，确保picker有数据）
    this.getDoctorsList();
    
    // 获取就诊人列表
    this.getPatientsList();
    // 应用主题颜色
    this.applyThemeColor();
    
    // 从医生详情页接收参数
    if (options.doctor_id && options.date) {
      // 直接从参数中获取医生信息，并解码URL编码的字符串
      const doctor = {
        id: options.doctor_id,
        name: decodeURIComponent(options.name || ''),
        title: decodeURIComponent(options.title || '')
      };
      
      const timeSlot = options.time_slot || '';
      
      this.setData({
        selectedDoctorId: options.doctor_id,
        dateTime: options.date,
        selectedDoctor: doctor,
        selectedDoctorName: doctor.name,
        timeSlot: timeSlot
      });
      
      // 获取医生绑定的病种
      this.getDoctorDiseases(options.doctor_id);
      // 获取医生排班信息
      this.getDoctorSchedules(options.doctor_id, options.date);
    }
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
  
  // 获取医生列表
  getDoctorsList() {
    const app = getApp();
    const baseUrl = app.globalData.baseUrl;
    const apiUrl = baseUrl.endsWith('/') ? baseUrl : baseUrl + '/';
    console.log('获取医生列表 - API地址:', apiUrl + 'api/get_doctors.php');
    
    this.setData({ isLoadingDoctors: true });
    
    wx.request({
      url: apiUrl + 'api/get_doctors.php',
      method: 'GET',
      timeout: 10000,
      success: res => {
        this.setData({ isLoadingDoctors: false });
        console.log('获取医生列表成功:', res);
        try {
          if (res.data && res.data.code === 200 && Array.isArray(res.data.data) && res.data.data.length > 0) {
            this.setData({
              doctors: res.data.data
            });
            console.log('医生列表已更新:', res.data.data);
          } else if (res.data && res.data.data && Array.isArray(res.data.data)) {
            console.log('医生列表为空:', res.data.data);
          } else {
            console.log('医生列表数据格式不正确:', res.data);
            this.loadDoctorsFromAlternative();
          }
        } catch (error) {
          console.error('处理医生列表数据失败:', error);
          this.loadDoctorsFromAlternative();
        }
      },
      fail: err => {
        this.setData({ isLoadingDoctors: false });
        console.error('获取医生列表失败:', err);
        this.loadDoctorsFromAlternative();
      }
    });
  },
  
  loadDoctorsFromAlternative() {
    const app = getApp();
    const baseUrl = app.globalData.baseUrl;
    const apiUrl = baseUrl.endsWith('/') ? baseUrl : baseUrl + '/';
    console.log('尝试从备用API获取医生列表:', apiUrl + 'api/Doctor/getDoctors.php');
    
    this.setData({ isLoadingDoctors: true });
    
    wx.request({
      url: apiUrl + 'api/Doctor/getDoctors.php',
      method: 'GET',
      timeout: 10000,
      success: res => {
        this.setData({ isLoadingDoctors: false });
        console.log('备用API获取医生列表结果:', res);
        try {
          if (res.data && (res.data.code === 200 || res.data.code === 0) && Array.isArray(res.data.data) && res.data.data.length > 0) {
            this.setData({
              doctors: res.data.data
            });
            console.log('备用API医生列表已更新:', res.data.data);
          } else if (res.data && Array.isArray(res.data) && res.data.length > 0) {
            this.setData({
              doctors: res.data
            });
            console.log('备用API直接返回数组:', res.data);
          } else {
            console.log('备用API也没有返回有效数据');
          }
        } catch (error) {
          console.error('处理备用API医生数据失败:', error);
        }
      },
      fail: err => {
        this.setData({ isLoadingDoctors: false });
        console.error('备用API获取医生列表失败:', err);
      }
    });
  },
  
  // 获取医生信息
  getDoctorInfo(doctorId) {
    const app = getApp();
    const baseUrl = app.globalData.baseUrl;
    const apiUrl = baseUrl.endsWith('/') ? baseUrl : baseUrl + '/';
    wx.request({
      url: apiUrl + 'api/Doctor/getDoctorDetail.php',
      method: 'GET',
      data: { doctor_id: doctorId },
      success: res => {
        try {
          if (res.data && res.data.code === 200 && res.data.data) {
            const doctor = res.data.data;
            this.setData({
              selectedDoctor: doctor,
              selectedDoctorName: doctor.name
            });
          }
        } catch (error) {
          console.error('处理医生信息失败:', error);
        }
      },
      fail: err => {
        console.error('获取医生信息失败:', err);
      }
    });
  },

  // 获取就诊人列表
  getPatientsList() {
    const app = getApp();
    if (!app.globalData.token) {
      console.log('用户未登录，无法获取就诊人列表');
      return;
    }
    
    console.log('获取就诊人列表');
    const baseUrl = app.globalData.baseUrl;
    const apiUrl = baseUrl.endsWith('/') ? baseUrl : baseUrl + '/';
    console.log('API基础路径:', baseUrl);
    console.log('请求URL:', apiUrl + 'api/patient/getPatients.php');
    
    wx.request({
      url: apiUrl + 'api/patient/getPatients.php',
      method: 'POST',
      data: {
        token: app.globalData.token
      },
      header: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${app.globalData.token}`
      },
      success: res => {
        console.log('获取就诊人列表响应:', res);
        try {
          if (res.data && res.data.code === 200 && Array.isArray(res.data.data)) {
            const data = res.data.data;
            console.log('就诊人数据:', data);
            // 生成用于 picker 组件的选项数组
            const patientOptions = data.map(p => p.name + ' ' + p.gender + ' ' + p.age + '岁');
            
            // 根据就诊人数量自动填充表单
            let selectedPatientId = '';
            let selectedPatientIndex = 0;
            let patient = this.data.patient;
            
            if (data.length === 1) {
              // 只有一个就诊人，自动选择并填充表单
              const selectedPatient = data[0];
              selectedPatientId = selectedPatient.id;
              selectedPatientIndex = 0;
              patient = {
                name: selectedPatient.name,
                gender: selectedPatient.gender,
                age: selectedPatient.age,
                phone: selectedPatient.phone
              };
            } else if (data.length > 1) {
              // 多个就诊人，默认选择第一个
              selectedPatientId = data[0].id;
              selectedPatientIndex = 0;
              patient = {
                name: data[0].name,
                gender: data[0].gender,
                age: data[0].age,
                phone: data[0].phone
              };
            }
            
            this.setData({ 
              patients: data,
              patientOptions: patientOptions,
              selectedPatientId: selectedPatientId,
              selectedPatientIndex: selectedPatientIndex,
              patient: patient
            });
          } else {
            console.log('获取就诊人列表失败:', res.data.message || '未知错误');
            this.setData({ patientOptions: [] });
          }
        } catch (error) {
          console.error('处理就诊人数据失败:', error);
          this.setData({ patientOptions: [] });
        }
      },
      fail: err => {
        console.error('获取就诊人列表失败:', err);
        this.setData({ patientOptions: [] });
      }
    });
  },

  // 处理手机号输入
  bindPhoneInput(e) {
    this.setData({
      'patient.phone': e.detail.value
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
  
  // 处理症状描述输入
  bindSymptomsInput(e) {
    this.setData({
      symptoms: e.detail.value
    });
  },
  
  // 获取微信用户绑定的手机号
  getPhoneNumber(e) {
    const app = getApp();
    
    // 检查用户是否已登录
    if (!app.globalData.token) {
      // 未登录，先执行微信登录
      wx.showLoading({ title: '登录中...' });
      wx.login({
        success: (loginRes) => {
          if (loginRes.code) {
            // 调用后端登录接口
            app.request('/api/User/login.php', {
              code: loginRes.code
            }, 'POST')
              .then((loginResult) => {
                wx.hideLoading();
                if (loginResult.data && loginResult.data.token) {
                  // 保存token
                  app.globalData.token = loginResult.data.token;
                  wx.setStorageSync('token', loginResult.data.token);
                  
                  // 登录成功，继续获取手机号
                  this.doGetPhoneNumber(e);
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
      return;
    }
    
    // 已登录，直接获取手机号
    this.doGetPhoneNumber(e);
  },
  
  // 实际获取手机号的逻辑
  doGetPhoneNumber(e) {
    const app = getApp();
    
    if (e.detail.errMsg === 'getPhoneNumber:ok') {
      wx.showLoading({ title: '获取中...' });
      wx.login({
        success: res => {
          if (res.code) {
            const encryptedData = e.detail.encryptedData;
            const iv = e.detail.iv;
            
            // 调用后端API解密获取手机号（使用现有的bindPhone.php接口）
            app.request('/api/User/bindPhone.php', {
              encryptedData: encryptedData,
              iv: iv,
              code: res.code,
              token: app.globalData.token
            }, 'POST')
              .then(res => {
                wx.hideLoading();
                if (res.data && res.data.userInfo && res.data.userInfo.phone) {
                  // 检查是否是测试模式
                  if (res.message && res.message.includes('测试模式')) {
                    wx.showToast({ 
                      title: '获取成功（测试模式，非真实手机号）', 
                      icon: 'success',
                      duration: 2000
                    });
                  } else {
                    wx.showToast({ title: '获取成功', icon: 'success' });
                  }
                  this.setData({
                    'patient.phone': res.data.userInfo.phone
                  });
                } else {
                  wx.showToast({ title: '获取失败，请手动填写', icon: 'none' });
                }
              })
              .catch(err => {
                wx.hideLoading();
                console.error('获取手机号失败:', err);
                // 显示更详细的错误信息
                if (err.includes('IP不在微信白名单')) {
                  wx.showToast({ 
                    title: '获取失败：服务器IP未在微信白名单', 
                    icon: 'none',
                    duration: 2000
                  });
                } else if (err.includes('AppID') || err.includes('AppSecret')) {
                  wx.showToast({ 
                    title: '获取失败：AppID或AppSecret错误', 
                    icon: 'none',
                    duration: 2000
                  });
                } else {
                  wx.showToast({ title: '获取失败，请手动填写', icon: 'none' });
                }
              });
          } else {
            wx.hideLoading();
            wx.showToast({ title: '获取失败，请手动填写', icon: 'none' });
          }
        },
        fail: err => {
          wx.hideLoading();
          wx.showToast({ title: '获取失败，请手动填写', icon: 'none' });
        }
      });
    } else {
      // 用户拒绝授权
      wx.showToast({ title: '您已拒绝授权，可手动填写手机号', icon: 'none' });
    }
  },
  
  // 处理医生选择
  bindDoctorChange(e) {
    const index = parseInt(e.detail.value);
    const doctor = this.data.doctors[index];
    if (doctor) {
      this.setData({
        selectedDoctorId: doctor.id,
        selectedDoctorName: doctor.name,
        selectedDoctor: doctor,
        selectedDoctorIndex: index
      });
      // 获取该医生绑定的病种
      this.getDoctorDiseases(doctor.id);
      // 如果已经选择了日期，获取该日期的排班信息
      if (this.data.dateTime) {
        this.getDoctorSchedules(doctor.id, this.data.dateTime);
      }
    }
  },
  
  // 获取医生绑定的病种
  getDoctorDiseases(doctorId) {
    const app = getApp();
    const baseUrl = app.globalData.baseUrl;
    const apiUrl = baseUrl.endsWith('/') ? baseUrl : baseUrl + '/';
    console.log('获取医生绑定的病种，医生ID:', doctorId);
    console.log('API基础路径:', baseUrl);
    console.log('请求URL:', apiUrl + 'api/Doctor/getDoctorDiseases.php');
    wx.request({
      url: apiUrl + 'api/Doctor/getDoctorDiseases.php',
      method: 'GET',
      data: { doctor_id: doctorId },
      success: res => {
        console.log('获取病种列表响应:', res);
        try {
          if (res.data && res.data.code === 200 && Array.isArray(res.data.data) && res.data.data.length > 0) {
            console.log('病种数据:', res.data.data);
            this.setData({
              diseases: res.data.data,
              selectedDiseaseId: '',
              selectedDiseaseName: '',
              selectedDisease: null,
              selectedDiseaseIndex: 0
            });
          } else {
            console.log('没有病种数据');
            this.setData({
              diseases: [],
              selectedDiseaseId: '',
              selectedDiseaseName: '',
              selectedDisease: null,
              selectedDiseaseIndex: 0
            });
          }
        } catch (error) {
          console.error('处理病种数据失败:', error);
          this.setData({
            diseases: [],
            selectedDiseaseId: '',
            selectedDiseaseName: '',
            selectedDisease: null,
            selectedDiseaseIndex: 0
          });
        }
      },
      fail: err => {
        console.error('获取病种列表失败:', err);
        this.setData({
          diseases: [],
          selectedDiseaseId: '',
          selectedDiseaseName: '',
          selectedDisease: null,
          selectedDiseaseIndex: 0
        });
      }
    });
  },
  
  // 处理病种选择
  bindDiseaseChange(e) {
    const index = parseInt(e.detail.value);
    const disease = this.data.diseases[index];
    if (disease) {
      this.setData({
        selectedDiseaseId: disease.id,
        selectedDiseaseName: disease.name,
        selectedDisease: disease,
        selectedDiseaseIndex: index
      });
    }
  },

  // 处理就诊人选择
  bindPatientChange(e) {
    const index = parseInt(e.detail.value);
    const patient = this.data.patients[index];
    if (patient) {
      this.setData({
        selectedPatientId: patient.id,
        selectedPatientIndex: index,
        patient: {
          name: patient.name,
          gender: patient.gender,
          age: patient.age,
          phone: patient.phone
        }
      });
    }
  },
  
  // 获取医生排班信息
  getDoctorSchedules(doctorId, date) {
    console.log('获取排班信息:', { doctorId, date });
    const app = getApp();
    const baseUrl = app.globalData.baseUrl;
    const apiUrl = baseUrl.endsWith('/') ? baseUrl : baseUrl + '/';
    wx.showLoading({ title: '获取排班信息...' });
    wx.request({
      url: apiUrl + 'api/get_doctor_schedules.php',
      method: 'GET',
      data: {
        doctor_id: doctorId
      },
      success: res => {
        wx.hideLoading();
        console.log('获取排班信息成功:', res);
        try {
          if (res.data && res.data.success && Array.isArray(res.data.data)) {
            // 从返回的数据中找到指定日期的排班
            const daySchedule = res.data.data.find(item => item.date === date);
            if (daySchedule && daySchedule.timeSlots && daySchedule.timeSlots.length > 0) {
              // 转换格式，保持与原有代码兼容，并包含停诊状态
              const schedules = daySchedule.timeSlots.map((slot, index) => ({
                id: index + 1,
                doctor_id: doctorId,
                date: date,
                time_slot: slot.time,
                total_quantity: slot.total || 20,
                remaining_quantity: slot.count || 0,
                start_time: slot.startTime,
                end_time: slot.endTime,
                is_suspended: slot.isSuspended || false
              }));
              console.log('排班数据:', schedules);
              
              // 更新停诊状态
              const morningSchedule = schedules.find(s => s.time_slot === '上午');
              const afternoonSchedule = schedules.find(s => s.time_slot === '下午');
              
              this.setData({ 
                schedules: schedules,
                morningSuspended: morningSchedule ? morningSchedule.is_suspended : false,
                afternoonSuspended: afternoonSchedule ? afternoonSchedule.is_suspended : false
              });
            } else {
              // 如果没有排班信息，生成默认排班数据
              console.log('没有排班信息，生成默认排班数据');
              const defaultSchedules = [
                { id: 1, doctor_id: doctorId, date: date, time_slot: '上午', total_quantity: 20, remaining_quantity: 0, is_suspended: false },
                { id: 2, doctor_id: doctorId, date: date, time_slot: '下午', total_quantity: 20, remaining_quantity: 0, is_suspended: false }
              ];
              this.setData({ 
                schedules: defaultSchedules,
                morningSuspended: false,
                afternoonSuspended: false
              });
            }
          } else {
            // 如果没有排班信息，生成默认排班数据
            console.log('没有排班信息，生成默认排班数据');
            const defaultSchedules = [
              { id: 1, doctor_id: doctorId, date: date, time_slot: '上午', total_quantity: 20, remaining_quantity: 0, is_suspended: false },
              { id: 2, doctor_id: doctorId, date: date, time_slot: '下午', total_quantity: 20, remaining_quantity: 0, is_suspended: false }
            ];
            this.setData({ 
              schedules: defaultSchedules,
              morningSuspended: false,
              afternoonSuspended: false
            });
          }
        } catch (error) {
          wx.hideLoading();
          console.error('处理排班数据失败:', error);
          // 出错时生成默认排班数据
          const defaultSchedules = [
            { id: 1, doctor_id: doctorId, date: date, time_slot: '上午', total_quantity: 20, remaining_quantity: 0 },
            { id: 2, doctor_id: doctorId, date: date, time_slot: '下午', total_quantity: 20, remaining_quantity: 0 }
          ];
          this.setData({ schedules: defaultSchedules });
        }
      },
      fail: err => {
        wx.hideLoading();
        console.error('获取排班信息失败:', err);
        // 失败时生成默认排班数据
        const defaultSchedules = [
          { id: 1, doctor_id: doctorId, date: date, time_slot: '上午', total_quantity: 20, remaining_quantity: 0 },
          { id: 2, doctor_id: doctorId, date: date, time_slot: '下午', total_quantity: 20, remaining_quantity: 0 }
        ];
        this.setData({ schedules: defaultSchedules });
      }
    });
  },
  
  // 处理日期时间选择
  bindDateChange(e) {
    // 格式化日期，只保留年月日
    const selectedDate = e.detail.value;
    this.setData({
      dateTime: selectedDate
    });
    // 如果已经选择了医生，获取该日期的排班信息
    if (this.data.selectedDoctorId) {
      this.getDoctorSchedules(this.data.selectedDoctorId, selectedDate);
    } else {
      // 如果还没有选择医生，生成默认排班数据
      const defaultSchedules = [
        { id: 1, doctor_id: 1, date: selectedDate, time_slot: '上午', total_quantity: 20, remaining_quantity: 20 },
        { id: 2, doctor_id: 1, date: selectedDate, time_slot: '下午', total_quantity: 20, remaining_quantity: 20 }
      ];
      this.setData({ schedules: defaultSchedules });
    }
  },
  


  // 处理时间段选择
  bindTimeSlotChange(e) {
    console.log('时间段选择:', e.detail.value);
    const timeSlot = e.detail.value;
    
    // 从排班列表中找到对应的排班
    let selectedSchedule = null;
    if (this.data.schedules.length > 0) {
      selectedSchedule = this.data.schedules.find(item => {
        // 根据时间段匹配排班
        if (timeSlot === '上午' && item.time_slot === '上午') {
          return true;
        } else if (timeSlot === '下午' && item.time_slot === '下午') {
          return true;
        }
        return false;
      });
    }
    
    console.log('找到的排班:', selectedSchedule);
    
    // 检查是否有对应的排班
    if (!selectedSchedule) {
      // 如果没有排班信息，使用默认的排班ID
      console.log('没有找到对应的排班，使用默认排班ID');
      this.setData({
        timeSlot: timeSlot,
        selectedScheduleId: 1 // 使用默认的排班ID
      });
      return;
    }
    
    // 检查是否停诊
    if (selectedSchedule.is_suspended) {
      wx.showToast({ title: '该时段已停诊', icon: 'none' });
      return;
    }
    
    // 检查是否有剩余号源
    if (selectedSchedule.remaining_quantity <= 0) {
      wx.showToast({ title: '该时段号源已用尽', icon: 'none' });
      return;
    }
    
    // 设置时间段和排班ID
    this.setData({
      timeSlot: timeSlot,
      selectedScheduleId: selectedSchedule.id
    }, () => {
      console.log('timeSlot 已更新:', this.data.timeSlot);
      console.log('selectedScheduleId 已更新:', this.data.selectedScheduleId);
    });
  },
  
  // 提交预约
  submitAppointment() {
    // 防止重复提交
    if (this.data.isSubmitting) {
      console.log('正在提交中，禁止重复提交');
      return;
    }
    
    const { selectedDoctor, dateTime, timeSlot, patient, symptoms, selectedScheduleId, schedules, selectedDisease, selectedDiseaseId, selectedDiseaseName } = this.data;
    
    console.log('提交预约时的数据:', {
      selectedDoctor,
      dateTime,
      timeSlot,
      patient,
      symptoms,
      selectedScheduleId,
      schedules,
      selectedDisease,
      selectedDiseaseId,
      selectedDiseaseName
    });
    
    // 设置提交状态
    this.setData({ isSubmitting: true });
    
    // 验证表单
    if (!selectedDoctor) {
      wx.showToast({ title: '请选择预约医生', icon: 'none' });
      this.setData({ isSubmitting: false });
      return;
    }
    if (!dateTime) {
      wx.showToast({ title: '请选择预约日期', icon: 'none' });
      this.setData({ isSubmitting: false });
      return;
    }
    if (!timeSlot) {
      wx.showToast({ title: '请选择时间段', icon: 'none' });
      this.setData({ isSubmitting: false });
      return;
    }
    if (!selectedDiseaseId) {
      wx.showToast({ title: '请选择病种', icon: 'none' });
      this.setData({ isSubmitting: false });
      return;
    }
    if (!patient.phone) {
      wx.showToast({ title: '请输入手机号码', icon: 'none' });
      this.setData({ isSubmitting: false });
      return;
    }
    if (!patient.name) {
      wx.showToast({ title: '请输入就诊人姓名', icon: 'none' });
      this.setData({ isSubmitting: false });
      return;
    }
    if (!patient.age) {
      wx.showToast({ title: '请输入就诊人年龄', icon: 'none' });
      this.setData({ isSubmitting: false });
      return;
    }
    // 检查是否选择了有效的排班，如果没有，设置默认值
    if (!selectedScheduleId) {
      console.log('没有选择排班，使用默认值');
      // 即使没有选择排班，也允许提交
    }
    
    // 根据选择的时段添加时间信息
    let appointmentTime = dateTime;
    if (timeSlot === '上午') {
      appointmentTime += ' 09:00:00';
    } else if (timeSlot === '下午') {
      appointmentTime += ' 14:00:00';
    }
    
    // 调用后端API创建预约
    const app = getApp();
    console.log('提交预约时的token:', app.globalData.token);
    console.log('提交预约时的selectedDoctor:', selectedDoctor);
    console.log('提交预约时的dateTime:', dateTime);
    console.log('提交预约时的timeSlot:', timeSlot);
    console.log('提交预约时的patient:', patient);
    console.log('提交预约时的symptoms:', symptoms);
    console.log('提交预约时的selectedDisease:', selectedDisease);
    
    // 检查token是否存在
    if (!app.globalData.token) {
      wx.showToast({ title: '请先登录', icon: 'none' });
      return;
    }
    
    // 检查医生ID是否存在
    if (!selectedDoctor || !selectedDoctor.id) {
      wx.showToast({ title: '请选择医生', icon: 'none' });
      return;
    }
    
    // 检查日期和时间是否存在
    if (!dateTime || !timeSlot) {
      wx.showToast({ title: '请选择预约日期和时间', icon: 'none' });
      return;
    }
    
    // 检查病种是否选择
    if (!selectedDiseaseId) {
      wx.showToast({ title: '请选择病种', icon: 'none' });
      return;
    }
    
    // 检查患者信息是否完整
    if (!patient.name || !patient.phone || !patient.age) {
      wx.showToast({ title: '请填写完整的患者信息', icon: 'none' });
      return;
    }
    
    // 构造请求数据
    const requestData = {
      doctor_id: selectedDoctor.id,
      schedule_id: this.data.selectedScheduleId || 1,
      hospital_id: selectedDoctor.hospital_id || 1,
      department_id: selectedDoctor.department_id || 1,
      disease_id: selectedDiseaseId,
      disease_name: selectedDiseaseName,
      appointment_time: appointmentTime,
      time_slot: timeSlot,
      patient_name: patient.name,
      patient_phone: patient.phone,
      patient_gender: patient.gender || '未知',
      patient_age: patient.age,
      symptoms: symptoms || '',
      token: app.globalData.token
    };
    
    console.log('提交预约的请求数据:', requestData);
    
    // 直接使用wx.request而不是app.request，以便更好地控制请求
    const baseUrl = app.globalData.baseUrl;
    const apiUrl = baseUrl.endsWith('/') ? baseUrl : baseUrl + '/';
    wx.request({
      url: apiUrl + 'api/create_appointment.php',
      method: 'POST',
      data: requestData,
      header: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${app.globalData.token}`
      },
      success: (res) => {
        console.log('预约API返回:', res);
        if (res.data && res.data.code === 200) {
          wx.showToast({ title: '预约成功', icon: 'success' });
          
          // 跳转到预约列表页面
          setTimeout(() => {
            wx.navigateTo({
              url: `/pages/appointmentList/appointmentList`
            });
          }, 1500);
        } else {
          console.error('预约失败:', res.data);
          wx.showToast({ title: res.data.message || '预约失败，请重试', icon: 'none' });
          // 重置提交状态
          this.setData({ isSubmitting: false });
        }
      },
      fail: (err) => {
        console.error('网络请求失败:', err);
        wx.showToast({ title: '网络请求失败，请重试', icon: 'none' });
        // 重置提交状态
        this.setData({ isSubmitting: false });
      }
    });
  }
})