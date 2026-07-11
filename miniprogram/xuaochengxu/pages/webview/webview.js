Page({
  data: {
    url: '',
    webviewLoaded: false
  },
  
  onLoad: function(options) {
    if (options.url) {
      this.setData({
        url: decodeURIComponent(options.url)
      });
    }
  },
  
  // webview加载完成
  webviewLoadSuccess: function() {
    this.setData({
      webviewLoaded: true
    });
  }
})