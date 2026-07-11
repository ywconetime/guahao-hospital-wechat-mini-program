Page({
  data: {
    messages: [],
    inputValue: '',
    scrollToId: '',
    onlineCount: 0,
    sessionId: '',
    nickname: '',
    refreshTimer: null
  },

  onLoad() {
    this.data.sessionId = uniqid('chat_', true);
    this.data.nickname = '游客' + Math.floor(Math.random() * 9000 + 1000);
    
    this.loadMessages();
    this.loadOnlineCount();
    this.startRefresh();
    this.sendHeartbeat();
  },

  onShow() {
    this.loadMessages();
    this.loadOnlineCount();
  },

  onUnload() {
    this.stopRefresh();
  },

  onHide() {
    this.stopRefresh();
  },

  loadMessages() {
    const app = getApp();
    wx.request({
      url: app.globalData.baseUrl + '/license_system/api/customer_service.php?action=get_chatroom_messages',
      method: 'GET',
      success: (res) => {
        if (res.data && res.data.success && res.data.data) {
          this.setData({
            messages: res.data.data
          });
          this.scrollToBottom();
        }
      },
      fail: () => {
        console.log('获取消息失败');
      }
    });
  },

  loadOnlineCount() {
    const app = getApp();
    wx.request({
      url: app.globalData.baseUrl + '/license_system/api/customer_service.php?action=get_online_count',
      method: 'GET',
      success: (res) => {
        if (res.data && res.data.success && res.data.data) {
          this.setData({
            onlineCount: res.data.data.count
          });
        }
      },
      fail: () => {}
    });
  },

  sendHeartbeat() {
    const app = getApp();
    wx.request({
      url: app.globalData.baseUrl + '/license_system/api/customer_service.php?action=heartbeat',
      method: 'POST',
      data: {
        session_id: this.data.sessionId,
        nickname: this.data.nickname
      },
      success: (res) => {
        if (res.data && res.data.success && res.data.data) {
          this.setData({
            onlineCount: res.data.data.count
          });
        }
      },
      fail: () => {}
    });
  },

  refreshTimer: null,

  startRefresh() {
    this.stopRefresh();
    this.refreshTimer = setInterval(() => {
      this.loadMessages();
      this.sendHeartbeat();
    }, 5000);
  },

  stopRefresh() {
    if (this.refreshTimer) {
      clearInterval(this.refreshTimer);
      this.refreshTimer = null;
    }
  },

  onInput(e) {
    this.setData({
      inputValue: e.detail.value
    });
  },

  sendMessage() {
    const content = this.data.inputValue.trim();
    if (!content) {
      wx.showToast({ title: '请输入消息内容', icon: 'none' });
      return;
    }

    const app = getApp();
    wx.request({
      url: app.globalData.baseUrl + '/license_system/api/customer_service.php?action=send_chatroom_message',
      method: 'POST',
      data: {
        nickname: this.data.nickname,
        content: content,
        message_type: 'text'
      },
      success: (res) => {
        if (res.data && res.data.success) {
          this.setData({
            inputValue: ''
          });
          this.loadMessages();
        } else {
          wx.showToast({ title: '发送失败', icon: 'none' });
        }
      },
      fail: () => {
        wx.showToast({ title: '发送失败', icon: 'none' });
      }
    });
  },

  scrollToBottom() {
    setTimeout(() => {
      const messages = this.data.messages;
      if (messages.length > 0) {
        this.setData({
          scrollToId: 'msg-' + messages[messages.length - 1].id
        });
      }
    }, 100);
  },

  goBack() {
    wx.navigateBack();
  }
});

function uniqid(prefix = '', more_entropy = false) {
  const now = Date.now();
  const random = Math.random().toString(36).substr(2, 9);
  let result = prefix + now.toString(36) + random;
  if (more_entropy) {
    result += '.' + Math.random().toString(36).substr(2, 6);
  }
  return result;
}