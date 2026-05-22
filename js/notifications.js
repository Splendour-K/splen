// notifications.js - connects to SSE stream and updates unread badge and notification list
(function(){
    const badge = document.getElementById('global-unread-badge');
    if (!window.APP_URL) return;

    const path = window.location.pathname || '';
    const allowed = /(?:^|\/)(?:admin|brand|creator)?\/?dashboard\.php$|(?:^|\/)notifications\.php$/i.test(path);
    if (!allowed) return;

    function setBadge(n) {
        if (!badge) return;
        badge.textContent = n;
        badge.style.display = n && n > 0 ? 'flex' : 'none';
    }

    // One-shot badge fetch keeps the page light. Avoid holding a PHP worker open with SSE.
    refreshUnreadCount();

    if (window.location.pathname.endsWith('/notifications.php')) {
        refreshNotificationsList();
    }

    function refreshUnreadCount(){
        fetch(window.APP_URL + 'api/notifications.php?action=unread_count', { credentials: 'include' })
            .then(r => r.json()).then(j => setBadge(j.unread || 0)).catch(()=>{});
    }

    window.refreshNotificationsList = function(){
        // reload notifications list if an element exists
        const container = document.getElementById('notifications-list');
        if (!container) return;
        fetch(window.APP_URL + 'api/notifications.php?action=list&limit=50', { credentials: 'include' })
            .then(r => r.json()).then(j => {
                if (!j.ok) return;
                container.innerHTML = '';
                j.notifications.forEach(n => {
                    const item = document.createElement('div');
                    item.className = 'p-6 bg-white dark:bg-gray-900 rounded-3xl border mb-4';
                    const link = document.createElement('a');
                    link.href = n.target_url || (window.APP_URL + 'notifications.php');
                    link.innerHTML = '<h4 class="text-lg font-black text-gray-900 dark:text-white">'+escapeHtml(n.title)+'</h4>'+
                        '<p class="text-sm text-gray-600 dark:text-gray-400 font-medium mt-1">'+escapeHtml(n.message)+'</p>'+
                        '<p class="text-[10px] text-gray-400 font-bold uppercase mt-4">'+n.created_at+'</p>';
                    item.appendChild(link);
                    // actions
                    const actions = document.createElement('div');
                    actions.className = 'mt-3 flex gap-2';
                    const markBtn = document.createElement('button');
                    markBtn.className = 'px-3 py-2 bg-gray-50 rounded';
                    markBtn.textContent = n.is_read ? 'Read' : 'Mark as read';
                    markBtn.onclick = function(e){ e.preventDefault(); markRead(n.id); };
                    const delBtn = document.createElement('button');
                    delBtn.className = 'px-3 py-2 bg-red-50 text-red-600 rounded';
                    delBtn.textContent = 'Delete';
                    delBtn.onclick = function(e){ e.preventDefault(); deleteNotif(n.id); };
                    actions.appendChild(markBtn); actions.appendChild(delBtn);
                    item.appendChild(actions);
                    container.appendChild(item);
                });
            }).catch(()=>{});
    };

    function markRead(id){
        fetch(window.APP_URL + 'api/notifications.php?action=mark_read', { method: 'POST', credentials: 'include', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'id='+encodeURIComponent(id) })
            .then(r=>r.json()).then(j=>{ if (j.ok) { refreshNotificationsList(); refreshUnreadCount(); } });
    }

    function deleteNotif(id){
        fetch(window.APP_URL + 'api/notifications.php?action=delete', { method: 'POST', credentials: 'include', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'id='+encodeURIComponent(id) })
            .then(r=>r.json()).then(j=>{ if (j.ok) { refreshNotificationsList(); refreshUnreadCount(); } });
    }

    // Expose to global so inline onclick handlers can call them
    window.markRead = markRead;
    window.deleteNotif = deleteNotif;

    function escapeHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

})();
