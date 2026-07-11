Component({
  data: {
    showPhone: true,
    showWechatService: true,
    showChatRoom: false,
    showGroupQrcode: true,
    contactPhone: '18002890888',
    themeColor: '#007AFF',
    groupQrcodeUrl: '',
    onlineCount: 0,
    showModal: false,
    chatRoomSessionId: '',
    chatRoomNickname: ''
  },
  attached() {
    this.loadSettings();
    this.loadGroupQrcode();
    this.loadOnlineCount();
    this.startHeartbeat();
  },
  pageLifetimes: {
    show() {
      this.loadSettings();
      this.loadGroupQrcode();
      this.loadOnlineCount();
      this.startHeartbeat();
    },
    hide() {
      this.stopHeartbeat();
    }
  },
  methods: {
    loadSettings() {
      const app = getApp();
      app.getSystemSettings().then(() => {
        const phoneEnabled = wx.getStorageSync('phone_enabled');
        const wechatCustomerService = wx.getStorageSync('wechat_customer_service');
        const chatRoomEnabled = wx.getStorageSync('chat_room_enabled');
        const contactPhone = wx.getStorageSync('contact_phone');
        const themeColor = wx.getStorageSync('themeColor');
        
        this.setData({
          showPhone: phoneEnabled !== '' ? phoneEnabled == 1 : true,
          showWechatService: wechatCustomerService !== '' ? wechatCustomerService == 1 : true,
          showChatRoom: chatRoomEnabled !== '' ? chatRoomEnabled == 1 : false,
          contactPhone: contactPhone || '18002890888',
          themeColor: themeColor || '#007AFF'
        });
        
        console.log('漂浮按钮组件已加载');
        console.log('电话功能:', this.data.showPhone);
        console.log('客服功能:', this.data.showWechatService);
        console.log('聊天室功能:', this.data.showChatRoom);
      }).catch(() => {
        const phoneEnabled = wx.getStorageSync('phone_enabled');
        const wechatCustomerService = wx.getStorageSync('wechat_customer_service');
        const chatRoomEnabled = wx.getStorageSync('chat_room_enabled');
        const contactPhone = wx.getStorageSync('contact_phone');
        const themeColor = wx.getStorageSync('themeColor');
        
        this.setData({
          showPhone: phoneEnabled !== '' ? phoneEnabled == 1 : true,
          showWechatService: wechatCustomerService !== '' ? wechatCustomerService == 1 : true,
          showChatRoom: chatRoomEnabled !== '' ? chatRoomEnabled == 1 : false,
          contactPhone: contactPhone || '18002890888',
          themeColor: themeColor || '#007AFF'
        });
        
        console.log('漂浮按钮组件已加载(使用本地存储)');
      });
    },
    
    loadGroupQrcode() {
      const app = getApp();
      wx.request({
        url: app.globalData.baseUrl + '/license_system/api/customer_service.php?action=get_group_qrcode',
        method: 'GET',
        success: (res) => {
          console.log('获取微信群二维码:', res);
          if (res.data && res.data.success && res.data.data) {
            this.setData({
              groupQrcodeUrl: res.data.data.qrcode_url,
              showGroupQrcode: true
            });
          } else {
            this.setData({
              showGroupQrcode: false
            });
          }
        },
        fail: () => {
          this.setData({
            showGroupQrcode: false
          });
        }
      });
    },
    
    loadOnlineCount() {
      const app = getApp();
      wx.request({
        url: app.globalData.baseUrl + '/license_system/api/customer_service.php?action=get_online_count',
        method: 'GET',
        success: (res) => {
          console.log('获取在线人数:', res);
          if (res.data && res.data.success && res.data.data) {
            this.setData({
              onlineCount: res.data.data.count
            });
          }
        },
        fail: () => {
          console.log('获取在线人数失败');
        }
      });
    },
    
    heartbeatTimer: null,
    
    startHeartbeat() {
      this.stopHeartbeat();
      
      const sendHeartbeat = () => {
        const app = getApp();
        
        if (!this.data.chatRoomSessionId) {
          this.data.chatRoomSessionId = uniqid('chat_', true);
        }
        if (!this.data.chatRoomNickname) {
          this.data.chatRoomNickname = '游客' + Math.floor(Math.random() * 9000 + 1000);
        }
        
        wx.request({
          url: app.globalData.baseUrl + '/license_system/api/customer_service.php?action=heartbeat',
          method: 'POST',
          data: {
            session_id: this.data.chatRoomSessionId,
            nickname: this.data.chatRoomNickname
          },
          success: (res) => {
            if (res.data && res.data.success && res.data.data) {
              this.setData({
                onlineCount: res.data.data.count
              });
            }
          },
          fail: () => {},
          complete: () => {}
        });
      };
      
      sendHeartbeat();
      this.heartbeatTimer = setInterval(sendHeartbeat, 30000);
    },
    
    stopHeartbeat() {
      if (this.heartbeatTimer) {
        clearInterval(this.heartbeatTimer);
        this.heartbeatTimer = null;
      }
    },
    
    makePhoneCall() {
      wx.makePhoneCall({
        phoneNumber: this.data.contactPhone,
        fail: () => {
          wx.showToast({
            title: '拨打电话失败',
            icon: 'none'
          });
        }
      });
    },
    
    backToTop() {
      console.log('点击返回顶部');
      wx.pageScrollTo({
        scrollTop: 0,
        duration: 300
      });
    },
    
    openChatRoom() {
      wx.navigateTo({
        url: '/pages/chatroom/chatroom'
      });
    },
    
    showGroupQrcodeModal() {
      this.setData({
        showModal: true
      });
    },
    
    closeModal() {
      this.setData({
        showModal: false
      });
    },
    
    stopPropagation() {
    }
  }
});

function uniqid(prefix = '', more_entropy = false) {
  const md5 = (string) => {
    let hash = 0;
    for (let i = 0; i < string.length; i++) {
      const char = string.charCodeAt(i);
      hash = ((hash << 5) - hash) + char;
      hash = hash & hash;
    }
    return Math.abs(hash).toString(16);
  };
  
  const now = Date.now();
  const random = Math.random().toString(36).substr(2, 9);
  
  let result = prefix + now.toString(36) + random;
  
  if (more_entropy) {
    result += '.' + Math.random().toString(36).substr(2, 6);
  }
  
  return result;
}