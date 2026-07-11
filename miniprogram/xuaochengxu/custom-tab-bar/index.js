// custom-tab-bar/index.js
Component({
  data: {
    selected: 0,
    color: "#999",
    selectedColor: "#333333",
    defaultList: [
      {
        pagePath: "/pages/index/index",
        text: "首页",
        iconPath: "/images/home.png",
        selectedIconPath: "/images/home-active.png"
      },
      {
        pagePath: "/pages/appointment/appointment",
        text: "预约挂号",
        iconPath: "/images/appointment.png",
        selectedIconPath: "/images/appointment-active.png"
      },
      {
        pagePath: "/pages/doctor/doctor",
        text: "专家团队",
        iconPath: "/images/doctor.png",
        selectedIconPath: "/images/doctor-active.png"
      },
      {
        pagePath: "/pages/my/my",
        text: "我的",
        iconPath: "/images/my.png",
        selectedIconPath: "/images/my-active.png"
      }
    ],
    list: [
      {
        pagePath: "/pages/index/index",
        text: "首页",
        iconPath: "/images/home.png",
        selectedIconPath: "/images/home-active.png"
      },
      {
        pagePath: "/pages/appointment/appointment",
        text: "预约挂号",
        iconPath: "/images/appointment.png",
        selectedIconPath: "/images/appointment-active.png"
      },
      {
        pagePath: "/pages/doctor/doctor",
        text: "专家团队",
        iconPath: "/images/doctor.png",
        selectedIconPath: "/images/doctor-active.png"
      },
      {
        pagePath: "/pages/my/my",
        text: "我的",
        iconPath: "/images/my.png",
        selectedIconPath: "/images/my-active.png"
      }
    ]
  },
  attached() {
    this.updateTabbarConfig();
  },
  pageLifetimes: {
    show() {
      this.updateTabbarConfig();
    }
  },
  methods: {
    updateTabbarConfig() {
      const app = getApp();
      const baseUrl = app.globalData.baseUrl;
      const apiUrl = baseUrl.endsWith('/') ? baseUrl : baseUrl + '/';
      
      wx.request({
        url: apiUrl + 'api/tabbar/getTabbar.php',
        method: 'GET',
        timeout: 10000,
        success: res => {
          console.log('获取底部菜单配置成功', res.data);
          if (res.statusCode === 200 && res.data && res.data.list && Array.isArray(res.data.list)) {
            let tabbarList = res.data.list.map((item, index) => {
              // 强制使用本地图标文件，不受 HTTP/HTTPS 限制
              return {
                pagePath: '/' + item.pagePath,
                text: item.text,
                iconPath: this.getLocalIcon(index, false),
                selectedIconPath: this.getLocalIcon(index, true)
              };
            });
            
            this.setData({
              list: tabbarList
            });
            console.log('底部菜单配置已更新（使用本地图标）');
          } else {
            console.log('底部菜单数据格式错误，使用默认配置');
            this.loadDefaultList();
          }
        },
        fail: err => {
          console.log('获取底部菜单配置失败:', err);
          this.loadDefaultList();
        }
      });
    },
    getLocalIcon(index, isSelected) {
      const icons = ['home', 'appointment', 'doctor', 'my'];
      const suffix = isSelected ? '-active' : '';
      const iconName = icons[index] || 'home';
      return `/images/${iconName}${suffix}.png`;
    },
    loadDefaultList() {
      const app = getApp();
      app.getSystemSettings().then(() => {
        const wechatCustomerService = wx.getStorageSync('wechat_customer_service');
        console.log('客服功能开关状态:', wechatCustomerService);
        
        if (wechatCustomerService !== '' && wechatCustomerService == 0) {
          this.setData({
            list: this.data.defaultList
          });
        } else {
          const listWithService = [
            ...this.data.defaultList,
            {
              pagePath: "/pages/customer_service/customer_service",
              text: "客服",
              iconPath: "/images/customer_service.png",
              selectedIconPath: "/images/customer_service-active.png"
            }
          ];
          this.setData({
            list: listWithService
          });
        }
      }).catch(() => {
        this.setData({
          list: this.data.defaultList
        });
      });
    },
    switchTab(e) {
      const data = e.currentTarget.dataset
      const url = data.path
      wx.switchTab({url})
      this.setData({
        selected: data.index
      })
    }
  }
})