#!/bin/bash
# =============================================================
# EduFlix Agora - Weekly Security Audit Script
# Description: Generates a full security report and sends it
#              by email every Monday at 08:00 via cron.
# Cron: 0 8 * * 1 /opt/eduflix/auditoria.sh
# Required env vars: RESTIC_PASS, ADMIN_EMAIL
# =============================================================

FECHA=$(date '+%Y-%m-%d %H:%M:%S')
INFORME="/tmp/auditoria_$(date '+%Y%m%d').txt"

echo "=====================================================" > $INFORME
echo "  SECURITY AUDIT - EduFlix Agora" >> $INFORME
echo "  Date: $FECHA" >> $INFORME
echo "=====================================================" >> $INFORME

echo "--- FIREWALL STATUS ---" >> $INFORME
ufw status verbose >> $INFORME

echo "--- ACTIVE FAIL2BAN JAILS ---" >> $INFORME
fail2ban-client status >> $INFORME

echo "--- BANNED IPs ---" >> $INFORME
fail2ban-client status sshd | grep "Banned IP" >> $INFORME
fail2ban-client status jellyfin | grep "Banned IP" >> $INFORME

echo "--- DOCKER CONTAINERS ---" >> $INFORME
docker ps --format "table {{.Names}}	{{.Status}}	{{.Ports}}" >> $INFORME

echo "--- DISK USAGE /mnt/datos ---" >> $INFORME
df -h /mnt/datos >> $INFORME

echo "--- LAST 5 RESTIC SNAPSHOTS ---" >> $INFORME
RESTIC_PASSWORD="${RESTIC_PASS}" restic -r /mnt/datos/backups/restic-repo snapshots --last 5 2>/dev/null >> $INFORME

echo "--- LAST 10 SSH LOGINS ---" >> $INFORME
journalctl -u ssh --no-pager -n 10 >> $INFORME

echo "--- SYSTEM USERS ---" >> $INFORME
awk -F: '$3 >= 1000 {print $1, $3}' /etc/passwd >> $INFORME

echo "=====================================================" >> $INFORME
echo "  End of audit report" >> $INFORME
echo "=====================================================" >> $INFORME

mail -s "EduFlix Agora - Security Audit $(date '+%Y-%m-%d')" "${ADMIN_EMAIL}" < $INFORME
echo "Audit completed. Report sent to ${ADMIN_EMAIL}"
