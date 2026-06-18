#!/bin/bash
# =============================================================
# EduFlix Agora - Fail2Ban + NGINX Log Path Updater
# Description: Updates Fail2Ban jail config with the current
#              NGINX container log path after each reboot.
#              Called automatically on system startup.
# Usage: ./update_fail2ban_nginx.sh
# =============================================================

# Get current NGINX container log path
NGINX_LOG=$(docker inspect nginx --format='{{.LogPath}}')

# Update Fail2Ban jail config with the new log path
sed -i "s|logpath = /var/lib/docker/containers/.*json.log|logpath = $NGINX_LOG|" /etc/fail2ban/jail.local

# Restart Fail2Ban to apply changes
systemctl restart fail2ban

# Regenerate syslog from journald
journalctl --no-pager -n 100 > /var/log/syslog
chmod 644 /var/log/syslog
chmod 644 /var/log/fail2ban.log 2>/dev/null || true
