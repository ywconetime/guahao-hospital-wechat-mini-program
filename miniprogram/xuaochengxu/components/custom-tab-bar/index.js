// custom-tab-bar/index.js
Component({
  data: {
    selected: 0,
    color: "#999",
    selectedColor: "#00ccff",
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
  },
  methods: {
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