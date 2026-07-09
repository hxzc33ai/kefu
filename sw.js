// Service Worker for PWA Push Notifications

// 安装时立即激活
self.addEventListener('install', function(event) {
  self.skipWaiting()
})

// 激活时接管所有页面
self.addEventListener('activate', function(event) {
  event.waitUntil(clients.claim())
})

// 从主页面获取免打扰列表
function getMutedSessions() {
  return clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
    if (clientList.length === 0) return []
    // 向第一个可用的客户端窗口请求免打扰列表
    return new Promise(function(resolve) {
      var messageChannel = new MessageChannel()
      messageChannel.port1.onmessage = function(event) {
        resolve(event.data || [])
      }
      // 超时兜底，1秒内没收到回复就认为没有免打扰
      setTimeout(function() { resolve([]) }, 1000)
      clientList[0].postMessage({ type: 'getMutedSessions' }, [messageChannel.port2])
    })
  }).catch(function() {
    return []
  })
}

// 接收推送消息并显示系统通知
self.addEventListener('push', function(event) {
  var data = { title: '来消息了', body: '您有一条新消息', url: '/' }

  if (event.data) {
    try {
      data = event.data.json()
    } catch (e) {
      data.body = event.data.text()
    }
  }

  event.waitUntil(
    getMutedSessions().then(function(mutedList) {
      // 如果该会话在免打扰列表中，不显示通知
      if (data.sessionKey && mutedList.indexOf(data.sessionKey) !== -1) {
        console.log('免打扰会话，跳过推送通知:', data.sessionKey)
        return
      }

      var options = {
        body: data.body,
        icon: '/图标.png',
        badge: '/图标.png',
        vibrate: [200, 100, 200],
        tag: data.tag || 'new-message',
        renotify: true,
        data: { url: data.url || '/' }
      }

      return self.registration.showNotification(data.title, options)
    })
  )
})

// 点击通知时打开或聚焦应用
self.addEventListener('notificationclick', function(event) {
  event.notification.close()

  var urlToOpen = event.notification.data && event.notification.data.url
    ? new URL(event.notification.data.url, self.location.origin).href
    : self.location.origin + '/'

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
      // 如果有已打开的窗口，聚焦并导航到目标页面
      for (var i = 0; i < clientList.length; i++) {
        var client = clientList[i]
        if (client.url.indexOf(self.location.origin) !== -1 && 'focus' in client) {
          client.focus()
          if ('navigate' in client) {
            return client.navigate(urlToOpen)
          }
          return
        }
      }
      // 否则打开新窗口
      return clients.openWindow(urlToOpen)
    })
  )
})
