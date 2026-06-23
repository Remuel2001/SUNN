<?php
require_once __DIR__ . '/config/config.php';
redirectIfNotLoggedIn();
$page_title = 'Chat';
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$db = getDB();

$stmt = $db->prepare("SELECT id, first_name, last_name, profile_image FROM users WHERE role='admin' AND status='active' LIMIT 1");
$stmt->execute();
$admin_user = $stmt->fetch();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<div class="container-fluid py-4">
    <div class="page-header fade-in mb-3 d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="bi bi-chat-dots-fill me-2 text-primary"></i>Chat</h4>
        <a href="<?= BASE_URL ?>/auth/profile.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-person-circle me-1"></i>Edit Profile</a>
    </div>

    <div class="row g-0 chat-container">
        <div class="col-4 col-md-3 chat-sidebar">
            <div class="chat-sidebar-header">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-transparent border-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control form-control-sm border-0 bg-transparent" id="searchContact" placeholder="Search contacts..." style="box-shadow:none">
                </div>
            </div>
            <?php if ($user_role !== 'admin' && $admin_user): ?>
            <div class="chat-quick-contact" data-id="<?= $admin_user['id'] ?>">
                <?php if ($admin_user['profile_image']): ?>
                    <img src="<?= BASE_URL ?>/uploads/profiles/<?= $admin_user['profile_image'] ?>" class="chat-qc-avatar" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <?php endif; ?>
                <div class="chat-qc-avatar" style="<?= $admin_user['profile_image'] ? 'display:none' : '' ?>"><?= strtoupper($admin_user['first_name'][0] . $admin_user['last_name'][0]) ?></div>
                <div class="flex-grow-1">
                    <strong class="small"><?= htmlspecialchars($admin_user['first_name'] . ' ' . $admin_user['last_name']) ?></strong>
                    <span class="badge bg-primary ms-1" style="font-size:.55rem">ADMIN</span>
                    <small class="text-muted d-block" style="font-size:.65rem">Click to chat</small>
                </div>
                <i class="bi bi-chat-dots text-primary"></i>
            </div>
            <?php endif; ?>
            <div class="chat-sidebar-body" id="conversationList"></div>
            <div class="chat-sidebar-footer">
                <button class="btn btn-primary btn-sm w-100" onclick="showNewChat()"><i class="bi bi-plus-lg me-1"></i>New Chat</button>
            </div>
        </div>

        <div class="col-8 col-md-9 chat-main" id="chatMain">
            <div class="chat-main-placeholder" id="chatPlaceholder">
                <div class="text-center text-muted" style="max-width:360px">
                    <div class="mb-3 position-relative">
                        <input type="text" class="form-control form-control-sm" id="searchHistoryInput" placeholder="Search all messages..." style="border:2px solid var(--gray-200);border-radius:20px;padding:.4rem 1rem .4rem 2.2rem">
                        <i class="bi bi-search position-absolute text-muted" style="left:12px;top:50%;transform:translateY(-50%);font-size:.8rem"></i>
                        <div id="searchHistoryResults" class="text-start small bg-white border rounded-3 shadow-sm mt-1" style="position:absolute;width:100%;z-index:10;max-height:300px;overflow-y:auto;display:none"></div>
                    </div>
                    <i class="bi bi-chat-dots" style="font-size:4rem;opacity:.3"></i>
                    <h5 class="mt-3 text-muted">Select a conversation</h5>
                    <p class="small">Choose someone from the left or search history above</p>
                </div>
            </div>

            <div class="chat-main-content" id="chatContent" style="display:none">
                <div class="chat-header" id="chatHeader">
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-light back-to-list d-none" onclick="closeChatMobile()" style="display:none"><i class="bi bi-arrow-left"></i></button>
                        <div class="position-relative">
                            <div class="chat-avatar" id="chatAvatar" style="display:none"></div>
                            <img id="chatAvatarImg" class="chat-avatar-img" style="display:none">
                            <span class="online-dot" id="chatOnlineDot" style="display:none"></span>
                        </div>
                        <div class="flex-grow-1">
                            <strong id="chatName" class="small"></strong><br>
                            <small class="text-muted" id="chatStatus" style="font-size:.65rem"></small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <span id="typingIndicator" class="typing-dots" style="display:none"><span></span><span></span><span></span></span>
                        <button class="btn btn-sm btn-outline-primary px-2" id="btnAudioCall" title="Voice call" style="display:none"><i class="bi bi-telephone-fill"></i></button>
                        <button class="btn btn-sm btn-outline-primary px-2" id="btnVideoCall" title="Video call" style="display:none"><i class="bi bi-camera-video-fill"></i></button>
                        <button class="btn btn-sm btn-outline-secondary px-2" onclick="openSearchHistory()" title="Search history"><i class="bi bi-search"></i></button>
                    </div>
                </div>
                <div class="chat-messages" id="chatMessages"></div>
                <div class="chat-input-area">
                    <form id="chatForm" class="d-flex gap-2 align-items-end">
                        <input type="hidden" name="receiver_id" id="chatReceiverId">
                        <textarea class="form-control form-control-sm" id="chatInput" rows="1" placeholder="Message..." style="resize:none" required></textarea>
                        <button type="submit" class="btn btn-primary send-btn"><i class="bi bi-send-fill"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="searchHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-clock-history me-2 text-primary"></i>Chat History</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="historySearchInput" placeholder="Search all messages..." oninput="loadSearchResults(this.value)">
                </div>
                <div id="historyResults" style="max-height:450px;overflow-y:auto">
                    <div class="text-center text-muted py-4 small">Type at least 2 characters to search</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="newChatModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title"><i class="bi bi-person-plus me-2 text-primary"></i>Start New Chat</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-0" id="contactList" style="max-height:400px;overflow-y:auto">
                <div class="text-center text-muted py-4 small">Loading contacts...</div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="incomingCallModal" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content text-center p-4">
            <div class="mb-3">
                <div class="spinner-grow text-primary mb-2" role="status" style="width:3rem;height:3rem">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
            <h5 id="incomingCallName" class="fw-bold">---</h5>
            <small class="text-muted" id="incomingCallType">Incoming audio call...</small>
            <div class="d-flex justify-content-center gap-3 mt-4">
                <button class="btn btn-danger btn-lg rounded-circle px-3 py-2" onclick="rejectCall()"><i class="bi bi-telephone-x-fill"></i></button>
                <button class="btn btn-success btn-lg rounded-circle px-3 py-2" onclick="acceptCall()"><i class="bi bi-telephone-fill"></i></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="activeCallModal" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-fullscreen-md-down modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark text-white">
            <div class="modal-body p-0 position-relative" style="min-height:400px">
                <video id="remoteVideo" autoplay playsinline class="w-100 h-100" style="min-height:400px;object-fit:cover;background:#111"></video>
                <video id="localVideo" autoplay playsinline muted class="position-absolute" style="width:140px;height:105px;object-fit:cover;border-radius:8px;bottom:80px;right:12px;background:#222;border:2px solid rgba(255,255,255,.2)"></video>
                <div class="position-absolute top-0 start-0 w-100 p-3" style="background:linear-gradient(180deg,rgba(0,0,0,.6),transparent)">
                    <strong id="activeCallName" class="text-white">---</strong>
                    <small class="d-block text-white-50" id="activeCallStatus">Connecting...</small>
                </div>
                <div class="position-absolute bottom-0 start-0 w-100 p-3 text-center" style="background:linear-gradient(0deg,rgba(0,0,0,.6),transparent)">
                    <div class="d-flex justify-content-center gap-3">
                        <button class="btn btn-light rounded-circle px-3 py-2" id="btnMute" onclick="toggleMute()"><i class="bi bi-mic-fill"></i></button>
                        <button class="btn btn-light rounded-circle px-3 py-2" id="btnSpeaker" onclick="toggleSpeaker()"><i class="bi bi-volume-up-fill"></i></button>
                        <button class="btn btn-danger rounded-circle px-3 py-2" onclick="endCall()"><i class="bi bi-telephone-x-fill"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



<script>
if (typeof IMG_URL === 'undefined') { var IMG_URL = BASE_URL + '/uploads/profiles/'; }
const USER_ID = <?= $user_id ?>;
const USER_ROLE = '<?= $user_role ?>';
const ADMIN_ID = <?= ($admin_user ? $admin_user['id'] : 'null') ?>;
let activeUserId = null;
let activeUserInfo = null;
let pollTimer = null;
let typingTimer = null;
const contactCache = {};
let callState = { active: false, callId: null, type: 'audio', peer: null, localStream: null, remoteStream: null, signalLastId: 0, ringing: false };
const STUN = { iceServers: [
    { urls: 'stun:stun.l.google.com:19302' },
    { urls: 'stun:stun1.l.google.com:19302' },
    { urls: 'stun:stun2.l.google.com:19302' }
] };

$(function() {
    $.get(BASE_URL + '/api/chat.php?action=contacts', function(res) {
        if (res.success) {
            res.data.forEach(function(c) {
                contactCache[c.id] = {
                    name: c.first_name + ' ' + c.last_name,
                    role: ucfirst((c.role||'').replace('_', ' ')),
                    img: c.profile_image ? IMG_URL + c.profile_image : null,
                    initials: (c.first_name||'?')[0] + (c.last_name||'')[0],
                    online: c.online
                };
            });
        }
    });
});

// Call buttons
$('#btnAudioCall').on('click', function() { startCall('audio'); });
$('#btnVideoCall').on('click', function() { startCall('video'); });

$('.chat-quick-contact').on('click', function() {
    const $el = $(this);
    const id = $el.data('id');
    if (activeUserId != id) { activeUserId = null; openChat(id, '<?= addslashes($admin_user['first_name'] ?? 'Admin') ?>', '<?= addslashes($admin_user['last_name'] ?? '') ?>', 'admin', '<?= addslashes($admin_user['profile_image'] ?? '') ?>'); }
});

function setChatHeader(id, first, last, role, img) {
    activeUserInfo = { id, first, last, role, img };
    $('#chatReceiverId').val(id);
    $('#chatName').text(first + ' ' + last);
    $('#chatStatus').text(ucfirst((role||'').replace('_', ' ')));
    $('#btnAudioCall, #btnVideoCall').show();
    if (img) {
        $('#chatAvatarImg').attr('src', IMG_URL + img).show();
        $('#chatAvatar').hide();
    } else {
        $('#chatAvatar').text((first||'?')[0] + (last||'')[0]).show();
        $('#chatAvatarImg').hide();
    }
}

function loadConversations() {
    $.get(BASE_URL + '/api/chat.php?action=conversations', function(res) {
        if (!res.success) return;
        const list = $('#conversationList');
        if (res.data.length === 0) {
            list.html('<div class="text-center text-muted py-5"><i class="bi bi-inbox" style="font-size:2rem;opacity:.3"></i><p class="mb-0 small mt-2">No conversations yet</p></div>');
            return;
        }
        let html = '';
        res.data.forEach(function(c) {
            const active = c.id == activeUserId ? ' active' : '';
            const unread = parseInt(c.unread) > 0 ? ' <span class="badge bg-danger chat-unread">' + c.unread + '</span>' : '';
            const lastMsg = c.last_message ? (c.last_message.length > 50 ? c.last_message.substr(0,50)+'...' : c.last_message) : 'No messages yet';
            const avatar = c.profile_image
                ? '<img src="' + IMG_URL + c.profile_image + '" class="chat-conv-img" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><div class="chat-conv-avatar" style="display:none">' + (c.first_name||'?')[0] + (c.last_name||'')[0] + '</div>'
                : '<div class="chat-conv-avatar">' + (c.first_name||'?')[0] + (c.last_name||'')[0] + '</div>';
            const onlineDot = c.online ? ' <span class="online-dot-sm"></span>' : '';
            const adminBadge = c.role === 'admin' ? ' <span class="badge bg-primary ms-1" style="font-size:.55rem">ADMIN</span>' : '';
            html += '<div class="chat-conversation' + active + '" data-id="' + c.id + '" data-first="' + escAttr(c.first_name) + '" data-last="' + escAttr(c.last_name) + '" data-role="' + c.role + '" data-img="' + (c.profile_image||'') + '">' +
                avatar +
                '<div class="chat-conv-info">' +
                '<div class="d-flex justify-content-between"><strong class="small">' + escHtml(c.first_name) + ' ' + escHtml(c.last_name) + '</strong>' + adminBadge +
                (c.last_time ? '<small class="text-muted" style="font-size:.6rem">' + timeAgo(c.last_time) + '</small>' : '') +
                '</div>' +
                '<div class="d-flex justify-content-between align-items-center">' +
                '<small class="text-muted d-block text-truncate conv-text" style="font-size:.72rem;max-width:80%">' + escHtml(lastMsg) + '</small>' + unread +
                '</div></div>' + onlineDot +
                '</div>';
        });
        list.html(html);
        list.find('.chat-conversation').on('click', function() {
            const $el = $(this);
            openChat(Number($el.data('id')), $el.data('first'), $el.data('last'), $el.data('role'), $el.data('img'));
        });
        updateBadge();
    });
}

function openChat(otherId, firstName, lastName, role, profileImg) {
    activeUserId = otherId;
    if (window.innerWidth <= 768) { $('.chat-sidebar').addClass('mobile-hidden'); $('#chatMain').addClass('mobile-show'); }
    loadConversations();

    $('#chatOnlineDot').hide();
    $('#chatStatus').text('');

    function checkOnline(uid, rl) {
        $.get(BASE_URL + '/api/chat.php?action=online_status&user_id=' + uid, function(res) {
            if (res.success && res.online) {
                $('#chatOnlineDot').show();
                $('#chatStatus').text('Active Now');
            } else {
                $('#chatOnlineDot').hide();
                $('#chatStatus').text(rl ? ucfirst(rl.replace('_', ' ')) : 'Offline');
            }
        }).fail(function() { $('#chatOnlineDot').hide(); $('#chatStatus').text('Offline'); });
    }

    function findOtherInfo(msgs) {
        for (var i = 0; i < msgs.length; i++) {
            if (msgs[i].sender_id != USER_ID) {
                return msgs[i];
            }
        }
        return msgs.length > 0 ? msgs[msgs.length - 1] : null;
    }

    if (firstName) {
        setChatHeader(otherId, firstName, lastName, role, profileImg);
        $('#chatPlaceholder').hide();
        $('#chatContent').show();
        checkOnline(otherId, role);
    }

    $.get(BASE_URL + '/api/chat.php?action=messages&user_id=' + otherId, function(res) {
        if (!res.success) return;
        $('#chatPlaceholder').hide();
        $('#chatContent').show();

        if (!firstName) {
            var other = findOtherInfo(res.data);
            if (other) {
                role = other.role;
                setChatHeader(otherId, other.first_name, other.last_name, other.role, other.profile_image);
            } else {
                role = 'user';
                setChatHeader(otherId, 'User', '', 'user', '');
            }
            checkOnline(otherId, role);
        }

        renderMessages(res.data);
        updateBadge();
        startPolling(otherId);
    }).fail(function() {
        if (!firstName) { setChatHeader(otherId, 'User', '', 'user', ''); checkOnline(otherId, 'user'); }
    });
}

function renderMessages(msgs) {
    const c = $('#chatMessages');
    let html = '';
    let lastDate = '';
    msgs.forEach(function(m) {
        const isMine = m.sender_id == USER_ID;
        const msgDate = new Date(m.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        if (msgDate !== lastDate) {
            html += '<div class="text-center my-3"><span class="chat-date-separator">' + msgDate + '</span></div>';
            lastDate = msgDate;
        }
        const avatar = m.profile_image
            ? '<img src="' + IMG_URL + m.profile_image + '" class="chat-bubble-avatar" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><div class="chat-bubble-avatar-initial" style="display:none">' + (m.first_name||'?')[0] + (m.last_name||'')[0] + '</div>'
            : '<div class="chat-bubble-avatar-initial">' + (m.first_name||'?')[0] + (m.last_name||'')[0] + '</div>';

        // Read receipt status
        let statusIcon = '';
        if (isMine) {
            if (m.seen_at) {
                statusIcon = '<span class="msg-status seen" title="Seen"><i class="bi bi-check2-all"></i></span>';
            } else if (m.is_read) {
                statusIcon = '<span class="msg-status delivered" title="Delivered"><i class="bi bi-check2-all"></i></span>';
            } else {
                statusIcon = '<span class="msg-status sent" title="Sent"><i class="bi bi-check2"></i></span>';
            }
        }

        html += '<div class="d-flex ' + (isMine ? 'justify-content-end' : 'align-items-end gap-1') + ' mb-1 msg-row">' +
            (!isMine ? avatar : '') +
            '<div><div class="chat-bubble ' + (isMine ? 'chat-bubble-mine' : 'chat-bubble-other') + '">' +
            '<div class="msg-text">' + escHtml(m.message) + '</div>' +
            '<div class="msg-meta">' +
            '<span class="msg-time">' + timeAgoShort(m.created_at) + '</span>' +
            (isMine ? statusIcon : '') +
            '</div></div>' +
            '</div>' +
            '</div>';
    });
    if (msgs.length === 0) {
        html = '<div class="text-center text-muted py-5"><i class="bi bi-hand-wave" style="font-size:2rem;opacity:.3"></i><p class="mb-0 small mt-2">No messages yet. Say hello!</p></div>';
    }
    c.html(html);
    c.scrollTop(c[0].scrollHeight);
}

var lastMsgState = '';
function startPolling(otherId) {
    if (pollTimer) clearInterval(pollTimer);
    lastMsgState = '';
    pollTimer = setInterval(function() {
        if (activeUserId != otherId) { clearInterval(pollTimer); return; }
        if (document.hidden) return;
        $.get(BASE_URL + '/api/chat.php?action=messages&user_id=' + otherId, function(res) {
            if (res.success && res.data.length > 0) {
                const last = res.data[res.data.length - 1];
                const state = res.data.length + '|' + (last.seen_at || '') + '|' + last.id;
                if (state !== lastMsgState) {
                    renderMessages(res.data);
                    lastMsgState = state;
                }
                loadConversations();
            }
        });
        // Check typing
        $.get(BASE_URL + '/api/chat.php?action=typing&user_id=' + otherId, function(res) {
            if (res.success && res.is_typing) {
                $('#typingIndicator').show();
                $('#chatStatus').text('typing');
            } else {
                $('#typingIndicator').hide();
                $.get(BASE_URL + '/api/chat.php?action=online_status&user_id=' + otherId, function(or) {
                    $('#chatStatus').text(or.success && or.online ? 'Active Now' : (activeUserInfo ? ucfirst((activeUserInfo.role||'').replace('_',' ')) : ''));
                });
            }
        });
    }, 1500);
}

// Optimistic send
$('#chatInput').on('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        $('#chatForm').submit();
    }
});

let typingDebounce = null;
$('#chatInput').on('input', function() {
    if (!activeUserId) return;
    if (typingDebounce) clearTimeout(typingDebounce);
    $.post(BASE_URL + '/api/chat.php', { action: 'typing', user_id: activeUserId });
    typingDebounce = setTimeout(function() {
        $.post(BASE_URL + '/api/chat.php', { action: 'stop_typing', user_id: activeUserId });
    }, 3000);
});

$('#chatForm').on('submit', function(e) {
    e.preventDefault();
    const input = $('#chatInput');
    const msg = input.val().trim();
    const receiverId = $('#chatReceiverId').val();
    if (!msg || !receiverId) return;

    const msgText = msg;
    input.val('');

    // Optimistic: add message instantly
    const now = new Date();
    const fakeMsg = {
        id: 'temp_' + Date.now(),
        sender_id: USER_ID,
        receiver_id: receiverId,
        message: msgText,
        created_at: now.toISOString().slice(0,19).replace('T',' '),
        is_read: 0,
        seen_at: null,
        first_name: activeUserInfo ? activeUserInfo.first : '',
        last_name: activeUserInfo ? activeUserInfo.last : '',
        profile_image: activeUserInfo ? activeUserInfo.img ? activeUserInfo.img.replace(IMG_URL, '') : null : null
    };

    const c = $('#chatMessages');
    const emptyState = c.find('.text-center.py-5');
    if (emptyState.length) emptyState.remove();

    const msgDate = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    const lastSep = c.find('.chat-date-separator').last();
    if (!lastSep.length || lastSep.text() !== msgDate) {
        c.append('<div class="text-center my-3"><span class="chat-date-separator">' + msgDate + '</span></div>');
    }

    c.append(
        '<div class="d-flex justify-content-end mb-1 msg-row">' +
        '<div><div class="chat-bubble chat-bubble-mine">' +
        '<div class="msg-text">' + escHtml(msgText) + '</div>' +
        '<div class="msg-meta">' +
        '<span class="msg-time">' + timeAgoShort(fakeMsg.created_at) + '</span>' +
        '<span class="msg-status sending" title="Sending"><i class="bi bi-clock"></i></span>' +
        '</div></div></div></div>'
    );
    c.scrollTop(c[0].scrollHeight);

    // Send to server
    $.ajax({
        url: BASE_URL + '/api/chat.php',
        method: 'POST',
        data: { action: 'send', receiver_id: receiverId, message: msgText },
        dataType: 'json',
        success: function(res) {
            if (res.success && activeUserId == receiverId) {
                lastMsgState = '';
                $.get(BASE_URL + '/api/chat.php?action=messages&user_id=' + receiverId, function(r) {
                    if (r.success && activeUserId == receiverId) renderMessages(r.data);
                });
            }
        }
    });
});

function showNewChat() {
    $.get(BASE_URL + '/api/chat.php?action=contacts', function(res) {
        if (!res.success) return;
        const list = $('#contactList');
        if (res.data.length === 0) {
            list.html('<div class="text-center text-muted py-4 small">No other users available</div>');
            new bootstrap.Modal(document.getElementById('newChatModal')).show();
            return;
        }
        let html = '';
        res.data.forEach(function(c) {
            const avatar = c.profile_image
                ? '<img src="' + IMG_URL + c.profile_image + '" class="chat-avatar-sm" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><div class="chat-avatar-sm-init" style="display:none">' + (c.first_name||'?')[0] + (c.last_name||'')[0] + '</div>'
                : '<div class="chat-avatar-sm-init">' + (c.first_name||'?')[0] + (c.last_name||'')[0] + '</div>';
            const onlineDot = c.online ? ' <span class="online-dot-xs"></span>' : '';
            const adminBadge = c.role === 'admin' ? ' <span class="badge bg-primary ms-1" style="font-size:.55rem">ADMIN</span>' : '';
            html += '<div class="contact-item px-3 py-2 border-bottom" data-id="' + c.id + '" data-first="' + escAttr(c.first_name) + '" data-last="' + escAttr(c.last_name) + '" data-role="' + c.role + '" data-img="' + (c.profile_image||'') + '">' +
                '<div class="d-flex align-items-center gap-2">' +
                '<div class="position-relative">' + avatar + onlineDot + '</div>' +
                '<div><strong class="small">' + escHtml(c.first_name) + ' ' + escHtml(c.last_name) + '</strong>' + adminBadge + '<br><small class="text-muted" style="font-size:.65rem">' + ucfirst(c.role.replace('_',' ')) + '</small></div>' +
                '</div></div>';
        });
        list.html(html);
        list.find('.contact-item').on('click', function() {
            const $el = $(this);
            const id = $el.data('id');
            bootstrap.Modal.getInstance(document.getElementById('newChatModal')).hide();
            if (activeUserId != id) { activeUserId = null; openChat(id, $el.data('first'), $el.data('last'), $el.data('role'), $el.data('img')); }
        });
        new bootstrap.Modal(document.getElementById('newChatModal')).show();
    });
}

// Search history etc.
let searchHistoryModal = null;
let searchDebounce = null;
function openSearchHistory() {
    if (!searchHistoryModal) searchHistoryModal = new bootstrap.Modal(document.getElementById('searchHistoryModal'));
    loadSearchResults('');
    searchHistoryModal.show();
}
function loadSearchResults(q) {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(function() {
        q = (q || '').trim();
        const target = $('#historyResults');
        if (q.length < 2) { target.html('<div class="text-center text-muted py-4 small">Type at least 2 characters to search</div>'); return; }
        target.html('<div class="text-center py-3"><span class="spinner-border spinner-border-sm text-primary"></span> Searching...</div>');
        $.get(BASE_URL + '/api/chat.php?action=search&q=' + encodeURIComponent(q), function(res) {
            if (!res.success || !res.data.length) { target.html('<div class="text-center text-muted py-4 small"><i class="bi bi-search d-block mb-1" style="font-size:1.5rem;opacity:.3"></i>No messages found</div>'); return; }
            let html = '';
            res.data.forEach(function(m) {
                const isMine = m.sender_id == USER_ID;
                const date = new Date(m.created_at).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric', hour:'2-digit', minute:'2-digit'});
                const excerpt = m.message.length > 120 ? m.message.substr(0,120)+'...' : m.message;
                html += '<div class="border-bottom px-2 py-2 history-item" data-other="' + m.other_id + '" style="cursor:pointer">' +
                    '<div class="d-flex justify-content-between"><small class="fw-semibold">' + escHtml(m.other_name) + '</small><small class="text-muted" style="font-size:.65rem">' + date + '</small></div>' +
                    '<small class="d-block text-truncate" style="font-size:.78rem">' + (isMine ? '<span class="text-muted">You: </span>' : '') + escHtml(excerpt) + '</small></div>';
            });
            target.html(html);
            target.find('.history-item').on('click', function() {
                if (searchHistoryModal) searchHistoryModal.hide();
                const otherId = $(this).data('other');
                if (activeUserId != otherId) { activeUserId = null; openChat(otherId); }
            });
        });
    }, 300);
}
$('#searchHistoryInput').on('input', function() {
    const q = $(this).val().trim();
    if (q.length < 2) { $('#searchHistoryResults').hide(); return; }
    $.get(BASE_URL + '/api/chat.php?action=search&q=' + encodeURIComponent(q), function(res) {
        const el = $('#searchHistoryResults');
        if (!res.success || !res.data.length) { el.hide(); return; }
        let html = '';
        res.data.slice(0, 8).forEach(function(m) {
            const isMine = m.sender_id == USER_ID;
            const excerpt = m.message.length > 80 ? m.message.substr(0,80)+'...' : m.message;
            html += '<div class="border-bottom px-2 py-1 history-item" data-other="' + m.other_id + '" style="cursor:pointer">' +
                '<div class="d-flex justify-content-between"><small class="fw-semibold" style="font-size:.7rem">' + escHtml(m.other_name) + '</small><small class="text-muted" style="font-size:.6rem">' + timeAgoShort(m.created_at) + '</small></div>' +
                '<small style="font-size:.7rem">' + (isMine ? '<span class="text-muted">You: </span>' : '') + escHtml(excerpt) + '</small></div>';
        });
        if (res.data.length > 8) html += '<div class="text-center small text-muted py-1">+' + (res.data.length-8) + ' more</div>';
        el.html(html).show();
        el.find('.history-item').on('click', function() {
            $('#searchHistoryInput').val(''); $('#searchHistoryResults').hide();
            const otherId = $(this).data('other');
            if (activeUserId != otherId) { activeUserId = null; openChat(otherId); }
        });
    });
});
$(document).on('click', function(e) {
    if (!$(e.target).closest('#searchHistoryInput, #searchHistoryResults').length) $('#searchHistoryResults').hide();
});

$('#searchContact').on('input', function() {
    const q = $(this).val().toLowerCase();
    $('#conversationList .chat-conversation').each(function() {
        $(this).toggle($(this).text().toLowerCase().indexOf(q) > -1);
    });
});

function updateBadge() {
    $.get(BASE_URL + '/api/chat.php?action=unread_total', function(res) {
        if (res.success) {
            const total = res.count;
            document.title = total > 0 ? '(' + total + ') Chat - SUNN' : 'Chat - SUNN';
        }
    });
}

function closeChatMobile() {
    $('.chat-sidebar').removeClass('mobile-hidden');
    $('#chatMain').removeClass('mobile-show');
    $('#btnAudioCall, #btnVideoCall').hide();
}

// Tab visibility: stop polling when hidden
document.addEventListener('visibilitychange', function() {
    if (!document.hidden && activeUserId) {
        loadConversations();
        $.get(BASE_URL + '/api/chat.php?action=messages&user_id=' + activeUserId, function(res) {
            if (res.success) renderMessages(res.data);
        });
    }
});

function escRegex(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
function escHtml(s) { if (!s) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
function escAttr(s) { if (!s) return ''; return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function timeAgo(d) { const diff = (Date.now()-new Date(d).getTime())/1000; if(diff<60) return 'now'; if(diff<3600) return Math.floor(diff/60)+'m'; if(diff<86400) return Math.floor(diff/3600)+'h'; var dt=new Date(d); return (dt.getMonth()+1)+'/'+dt.getDate(); }
function timeAgoShort(d) { return new Date(d).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'}); }
function ucfirst(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

// ─── RINGTONE ───
var ringCtx = null;
var ringGain = null;
var ringOsc = null;
var ringTimer = null;

function playRingtone() {
    stopRingtone();
    try {
        ringCtx = new (window.AudioContext || window.webkitAudioContext)();
        ringGain = ringCtx.createGain();
        ringGain.gain.value = 0.3;
        ringGain.connect(ringCtx.destination);
        ringOsc = ringCtx.createOscillator();
        ringOsc.type = 'sine';
        ringOsc.frequency.value = 440;
        ringOsc.connect(ringGain);
        ringOsc.start();
        var on = true;
        ringTimer = setInterval(function() {
            on = !on;
            if (ringGain) ringGain.gain.value = on ? 0.3 : 0;
        }, 500);
    } catch(e) {}
}

function stopRingtone() {
    if (ringTimer) { clearInterval(ringTimer); ringTimer = null; }
    if (ringOsc) { try { ringOsc.stop(); } catch(e) {} ringOsc = null; }
    if (ringCtx) { try { ringCtx.close(); } catch(e) {} ringCtx = null; }
    ringGain = null;
}

// ─── CALL FUNCTIONS ───
var callReceiverId = null;

function startCall(type) {
    if (callState.active) return;
    const calleeId = $('#chatReceiverId').val();
    if (!calleeId) return;
    callState.type = type;
    callReceiverId = calleeId;
    $.post(BASE_URL + '/api/chat.php', { action: 'start_call', callee_id: calleeId, type: type }, function(res) {
        if (!res.success) { alert('Failed to start call'); return; }
        callState.active = true;
        callState.callId = res.call_id;
        callState.signalLastId = 0;
        $('#activeCallName').text($('#chatName').text());
        $('#activeCallStatus').text('Calling...');
        new bootstrap.Modal(document.getElementById('activeCallModal')).show();
        setupWebRTC(type === 'video', function() {
            callState.peer.createOffer().then(function(offer) {
                return callState.peer.setLocalDescription(offer);
            }).then(function() {
                $.post(BASE_URL + '/api/chat.php', { action: 'send_signal', call_id: callState.callId, receiver_id: callReceiverId, signal_type: 'offer', signal_data: JSON.stringify(callState.peer.localDescription) });
                playRingtone(); // ringback for caller
                startSignalPoll();
            }).catch(function(err) { console.error('Offer creation failed:', err); endCall(); });
        });
    });
}

function setupWebRTC(videoRemote, onReady) {
    if (callState.peer) callState.peer.close();
    callState.peer = new RTCPeerConnection(STUN);
    callState.peer.onicecandidate = function(e) {
        if (e.candidate && callReceiverId) {
            $.post(BASE_URL + '/api/chat.php', { action: 'send_signal', call_id: callState.callId, receiver_id: callReceiverId, signal_type: 'ice_candidate', signal_data: JSON.stringify(e.candidate) });
        }
    };
    callState.peer.oniceconnectionstatechange = function() {
        var state = callState.peer.iceConnectionState;
        console.log('ICE state:', state);
        if (state === 'connected' || state === 'completed') {
            $('#activeCallStatus').text('Connected');
        } else if (state === 'disconnected' || state === 'failed') {
            endCall();
        }
    };
    callState.peer.ontrack = function(e) {
        callState.remoteStream = e.streams[0];
        var rv = document.getElementById('remoteVideo');
        if (rv) {
            rv.srcObject = e.streams[0];
            rv.onloadedmetadata = function() { rv.play().catch(function(){}); };
            rv.play().catch(function(){});
        }
    };
    navigator.mediaDevices.getUserMedia({ audio: true, video: true }).then(function(stream) {
        callState.localStream = stream;
        stream.getTracks().forEach(function(t) { if (callState.peer) callState.peer.addTrack(t, stream); });
        var lv = document.getElementById('localVideo');
        if (lv) {
            lv.srcObject = stream;
            lv.onloadedmetadata = function() { lv.play().catch(function(){}); };
            lv.play().catch(function(){});
        }
        if (onReady) onReady();
    }).catch(function(err) {
        console.error('getUserMedia failed:', err);
        var msg = 'Unable to access camera/microphone. ';
        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') msg += 'Please allow camera/microphone permissions in your browser.';
        else if (err.name === 'NotFoundError') msg += 'No camera or microphone found.';
        else if (err.name === 'NotReadableError') msg += 'Camera/microphone is busy or locked by another app.';
        else msg += 'Error: ' + err.message;
        if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: msg, toast: false, confirmButtonText: 'OK' });
        else alert(msg);
        endCall();
    });
}

function startSignalPoll() {
    if (!callState.active || !callState.callId) return;
    $.get(BASE_URL + '/api/chat.php?action=get_signals&call_id=' + callState.callId + '&last_id=' + callState.signalLastId, function(res) {
        if (!res.success || !res.signals) { if (callState.active) setTimeout(startSignalPoll, 800); return; }
        // Process signals in order: SDP first (offer/answer), then ICE candidates
        var sdp = null;
        var ices = [];
        for (var i = 0; i < res.signals.length; i++) {
            var s = res.signals[i];
            callState.signalLastId = s.id;
            if (!callState.peer) break;
            if (s.signal_type === 'offer' || s.signal_type === 'answer') {
                sdp = s;
            } else if (s.signal_type === 'ice_candidate') {
                ices.push(s);
            }
        }
        if (sdp) {
            (function(sig) {
                var isOffer = sig.signal_type === 'offer';
                callState.peer.setRemoteDescription(new RTCSessionDescription(JSON.parse(sig.signal_data))).then(function() {
                    if (isOffer) {
                        return callState.peer.createAnswer().then(function(answer) {
                            return callState.peer.setLocalDescription(answer).then(function() {
                                $.post(BASE_URL + '/api/chat.php', { action: 'send_signal', call_id: callState.callId, receiver_id: callReceiverId, signal_type: 'answer', signal_data: JSON.stringify(callState.peer.localDescription) });
                            });
                        });
                    }
                }).then(function() {
                    // Now add queued ICE candidates
                    ices.forEach(function(ic) {
                        try { callState.peer.addIceCandidate(new RTCIceCandidate(JSON.parse(ic.signal_data))).catch(function(){}); } catch(e){}
                    });
                }).catch(function(err) { console.error('SDP processing failed:', err); });
            })(sdp);
        } else {
            ices.forEach(function(ic) {
                try { callState.peer.addIceCandidate(new RTCIceCandidate(JSON.parse(ic.signal_data))).catch(function(){}); } catch(e){}
            });
        }
        if (callState.active) setTimeout(startSignalPoll, 800);
    });
}

function acceptCall() {
    stopRingtone();
    var call = callState.ringing;
    if (!call) return;
    callState.ringing = false;
    if (missTimer) { clearTimeout(missTimer); missTimer = null; }
    bootstrap.Modal.getInstance(document.getElementById('incomingCallModal')).hide();
    callState.active = true;
    callState.callId = call.id;
    callState.type = call.type;
    callState.signalLastId = 0;
    callReceiverId = call.caller_id;
    $.post(BASE_URL + '/api/chat.php', { action: 'update_call', call_id: call.id, status: 'connected' });
    $('#activeCallName').text(call.first_name + ' ' + call.last_name);
    $('#activeCallStatus').text('Connecting...');
    new bootstrap.Modal(document.getElementById('activeCallModal')).show();
    setupWebRTC(call.type === 'video', function() {
        startSignalPoll();
    });
}

function rejectCall() {
    stopRingtone();
    if (callState.ringing) {
        $.post(BASE_URL + '/api/chat.php', { action: 'update_call', call_id: callState.ringing.id, status: 'rejected' });
        callState.ringing = false;
    }
    bootstrap.Modal.getInstance(document.getElementById('incomingCallModal'))?.hide();
}

function endCall() {
    stopRingtone();
    if (missTimer) { clearTimeout(missTimer); missTimer = null; }
    if (callState.callId) {
        $.post(BASE_URL + '/api/chat.php', { action: 'update_call', call_id: callState.callId, status: 'ended' });
    }
    if (callState.localStream) callState.localStream.getTracks().forEach(function(t) { t.stop(); });
    if (callState.peer) callState.peer.close();
    var rv = document.getElementById('remoteVideo');
    if (rv) { rv.srcObject = null; }
    var lv = document.getElementById('localVideo');
    if (lv) { lv.srcObject = null; }
    callState.active = false;
    callState.callId = null;
    callState.peer = null;
    callState.localStream = null;
    callState.remoteStream = null;
    callState.ringing = false;
    try { bootstrap.Modal.getInstance(document.getElementById('activeCallModal'))?.hide(); } catch(e){}
    try { bootstrap.Modal.getInstance(document.getElementById('incomingCallModal'))?.hide(); } catch(e){}
}

function toggleMute() {
    if (callState.localStream) {
        var enabled = callState.localStream.getAudioTracks()[0].enabled;
        callState.localStream.getAudioTracks()[0].enabled = !enabled;
        $('#btnMute i').toggleClass('bi-mic-fill bi-mic-mute-fill');
    }
}

function toggleSpeaker() {
    try {
        var rv = document.getElementById('remoteVideo');
        if (rv && 'sinkId' in rv) {
            rv.sinkId = rv.sinkId ? '' : 'default';
        }
    } catch(e) { console.log('Speaker toggle not supported'); }
    $('#btnSpeaker i').toggleClass('bi-volume-up-fill bi-volume-mute-fill');
}

// Call checking (integrated into polling)
var missTimer = null;

function autoMissCall() {
    if (callState.ringing) {
        var call = callState.ringing;
        callState.ringing = false;
        stopRingtone();
        $.post(BASE_URL + '/api/chat.php', { action: 'update_call', call_id: call.id, status: 'missed' });
        bootstrap.Modal.getInstance(document.getElementById('incomingCallModal'))?.hide();
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'info', title: 'Missed call from ' + call.first_name + ' ' + call.last_name, toast: true, position: 'top-end', showConfirmButton: false, timer: 4000 });
        }
    }
}

function checkIncomingCall() {
    if (callState.active) return;
    $.get(BASE_URL + '/api/chat.php?action=check_call', function(res) {
        if (!res.success) return;
        if (!res.call) {
            if (missTimer) { clearTimeout(missTimer); missTimer = null; }
            return;
        }
        var call = res.call;
        if (call.status === 'ringing' && call.callee_id == USER_ID) {
            if (callState.ringing && callState.ringing.id == call.id) return;
            callState.ringing = call;
            callState.callId = call.id;
            $('#incomingCallName').text(call.first_name + ' ' + call.last_name);
            $('#incomingCallType').text(call.type === 'video' ? 'Incoming video call...' : 'Incoming audio call...');
            playRingtone();
            try { new bootstrap.Modal(document.getElementById('incomingCallModal')).show(); } catch(e){}
            if (missTimer) clearTimeout(missTimer);
            missTimer = setTimeout(autoMissCall, 25000);
        } else if (call.status === 'connected' && call.caller_id == USER_ID && callState.active) {
            stopRingtone();
            if (missTimer) { clearTimeout(missTimer); missTimer = null; }
            $('#activeCallStatus').text('Connected');
        } else if ((call.status === 'rejected' || call.status === 'missed' || call.status === 'ended') && callState.active && !call._notified) {
            call._notified = true;
            if (missTimer) { clearTimeout(missTimer); missTimer = null; }
            var msg = call.status === 'rejected' ? 'Call declined' : (call.status === 'missed' ? 'Call missed' : 'Call ended');
            stopRingtone();
            endCall();
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: call.status === 'rejected' ? 'error' : 'info', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
            }
        }
    });
}

// Modal dismiss → auto-miss or end call
$('#incomingCallModal').on('hidden.bs.modal', function() {
    if (callState.ringing) {
        autoMissCall();
    }
});
$('#activeCallModal').on('hidden.bs.modal', function() {
    if (callState.active) {
        endCall();
    }
});

// Missed call badge on conversations
function loadMissedCalls() {
    $.get(BASE_URL + '/api/chat.php?action=check_call&missed=1', function(res) {
        if (!res.success || !res.calls) return;
        res.calls.forEach(function(c) {
            var el = $('#conversationList').find('[data-id="' + c.caller_id + '"] .missed-badge');
            if (!el.length) {
                var conv = $('#conversationList').find('[data-id="' + c.caller_id + '"]');
                if (conv.length) conv.find('.conv-text').append('<small class="text-danger missed-badge"><i class="bi bi-telephone-x"></i> Missed call</small>');
            }
            // Show toast if not already seen
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'info', title: 'Missed call from ' + c.first_name + ' ' + c.last_name, toast: true, position: 'top-end', showConfirmButton: false, timer: 5000 });
            }
        });
    });
}

// Start call polling independently (not tied to conversation)
if (!window._callPoll) {
    window._callPoll = setInterval(checkIncomingCall, 2000);
}

loadConversations();
loadMissedCalls();
var params = new URLSearchParams(location.search);
var receiverId = params.get('receiver_id');
if (receiverId) { openChat(parseInt(receiverId)); }
setInterval(function() { loadConversations(); updateBadge(); }, 3000);
setInterval(updateBadge, 15000);
</script>

<style>
.chat-container { height: calc(100vh - 140px); border:1px solid var(--gray-200); border-radius:12px; overflow:hidden; background:#fff; }
.chat-sidebar { border-right:1px solid var(--gray-200); display:flex; flex-direction:column; background:var(--gray-50); }
.chat-sidebar-header { padding:10px 12px; border-bottom:1px solid var(--gray-200); }
.chat-sidebar-body { flex:1; overflow-y:auto; }
.chat-sidebar-footer { padding:8px 12px; border-top:1px solid var(--gray-200); }
.chat-quick-contact { display:flex; align-items:center; gap:10px; padding:10px 12px; cursor:pointer; background:linear-gradient(135deg,#eef2ff,#e0e7ff); border-bottom:1px solid var(--gray-200); transition:background .15s; }
.chat-quick-contact:hover { background:linear-gradient(135deg,#e0e7ff,#c7d2fe); }
.chat-qc-avatar { width:36px; height:36px; border-radius:50%; background:var(--primary); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.75rem; flex-shrink:0; overflow:hidden; }
img.chat-qc-avatar { object-fit:cover; }
.chat-conversation { display:flex; align-items:center; gap:10px; padding:10px 12px; cursor:pointer; border-bottom:1px solid var(--gray-200); transition:background .15s; position:relative; }
.chat-conversation:hover { background:var(--gray-100); }
.chat-conversation.active { background:var(--primary-bg); border-left:3px solid var(--primary); }
.chat-conv-avatar { width:40px; height:40px; border-radius:50%; background:var(--primary); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.8rem; flex-shrink:0; }
.chat-conv-img { width:40px; height:40px; border-radius:50%; object-fit:cover; flex-shrink:0; }
.chat-conv-info { flex:1; min-width:0; }
.chat-unread { font-size:.6rem; padding:.15em .5em; }
.chat-main { display:flex; flex-direction:column; }
.chat-main-placeholder { flex:1; display:flex; align-items:center; justify-content:center; }
.chat-main-content { flex:1; display:flex; flex-direction:column; height:100%; }
.chat-header { padding:10px 16px; border-bottom:1px solid var(--gray-200); background:#fff; display:flex; align-items:center; justify-content:space-between; }
.chat-avatar { width:36px; height:36px; border-radius:50%; background:var(--primary); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.8rem; flex-shrink:0; }
.chat-avatar-img { width:36px; height:36px; border-radius:50%; object-fit:cover; flex-shrink:0; }
.chat-avatar-sm { width:32px; height:32px; border-radius:50%; object-fit:cover; flex-shrink:0; }
.chat-avatar-sm-init { width:32px; height:32px; border-radius:50%; background:var(--primary); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:600; font-size:.7rem; flex-shrink:0; }
.chat-messages { flex:1; overflow-y:auto; padding:12px 20px; background:#f0f2f5; }
.chat-bubble { max-width:70%; padding:6px 12px; border-radius:18px; word-wrap:break-word; }
.chat-bubble-mine { background:var(--primary); color:#fff; border-bottom-right-radius:4px; }
.chat-bubble-other { background:#fff; color:inherit; border-bottom-left-radius:4px; box-shadow:0 1px 2px rgba(0,0,0,.06); }
.chat-bubble-avatar { width:28px; height:28px; border-radius:50%; object-fit:cover; flex-shrink:0; margin-bottom:4px; }
.chat-bubble-avatar-initial { width:28px; height:28px; border-radius:50%; background:#cbd5e1; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:600; font-size:.6rem; flex-shrink:0; margin-bottom:4px; }
.chat-input-area { padding:8px 16px; border-top:1px solid var(--gray-200); background:#fff; }
.contact-item { cursor:pointer; transition:background .15s; }
.contact-item:hover { background:var(--gray-50); }

/* Date separators */
.chat-date-separator { background:rgba(0,0,0,.06); color:#65676b; font-size:.65rem; padding:4px 10px; border-radius:8px; }

/* Message meta */
.msg-text { font-size:.85rem; line-height:1.35; }
.msg-meta { display:flex; align-items:center; justify-content:flex-end; gap:3px; margin-top:1px; }
.msg-time { font-size:.58rem; opacity:.7; }
.msg-status { font-size:.58rem; }
.msg-status.sent { color:rgba(255,255,255,.6); }
.msg-status.delivered { color:rgba(255,255,255,.8); }
.msg-status.seen { color:#53bdeb; }
.msg-status.sending { color:rgba(255,255,255,.5); }
.chat-bubble-other .msg-meta { justify-content:flex-start; }
.chat-bubble-other .msg-time { color:#65676b; }
.chat-bubble-other .msg-status { display:none; }

/* Online dots */
.online-dot { width:10px; height:10px; border-radius:50%; background:#31a24c; border:2px solid #fff; position:absolute; bottom:0; right:0; }
.online-dot-sm { width:8px; height:8px; border-radius:50%; background:#31a24c; border:2px solid var(--gray-50); position:absolute; bottom:2px; right:2px; }
.chat-conversation { position:relative; }
.online-dot-sm { position:absolute; bottom:8px; left:42px; }
.online-dot-xs { width:7px; height:7px; border-radius:50%; background:#31a24c; display:inline-block; margin-left:1px; vertical-align:middle; }

/* Typing dots */
.typing-dots { display:inline-flex; align-items:center; gap:3px; padding:4px 8px; background:var(--gray-100); border-radius:20px; margin-right:8px; }
.typing-dots span { width:6px; height:6px; border-radius:50%; background:#8a8d91; animation:typingBounce 1.4s infinite ease-in-out both; }
.typing-dots span:nth-child(1) { animation-delay:-0.32s; }
.typing-dots span:nth-child(2) { animation-delay:-0.16s; }
@keyframes typingBounce { 0%,80%,100%{transform:scale(0)} 40%{transform:scale(1)} }

/* Send button */
.send-btn { border-radius:50% !important; width:36px; height:36px; display:flex; align-items:center; justify-content:center; padding:0 !important; }

/* Scrollbar */
.chat-messages::-webkit-scrollbar { width:5px; }
.chat-messages::-webkit-scrollbar-track { background:transparent; }
.chat-messages::-webkit-scrollbar-thumb { background:#c4c4c4; border-radius:10px; }

/* Missed call badge */
.missed-badge { display:block; font-size:.6rem; margin-top:1px; }

/* Mobile */
@media(max-width:768px){ .chat-sidebar { width:100%; } .chat-main { display:none; } .chat-sidebar.mobile-hidden { display:none; } .chat-main.mobile-show { display:flex; } .chat-bubble { max-width:85%; } .back-to-list { display:inline-flex !important; } }
</style>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
