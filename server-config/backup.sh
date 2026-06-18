#!/bin/bash
# =============================================================
# EduFlix Agora - Automated Backup Script
# Description: Encrypted incremental backups using Restic.
#              Backs up Docker config, NGINX, Jellyfin config
#              and PostgreSQL dump.
# Cron: 0 2 * * * /opt/eduflix/backup.sh
# Required env vars: RESTIC_PASS, ADMIN_EMAIL
# Retention policy: 7 daily, 4 weekly, 3 monthly
# =============================================================

REPO="/mnt/datos/backups/restic-repo"
FECHA=$(date +%Y-%m-%d_%H-%M)
LOG="/mnt/datos/backups/backup.log"

export RESTIC_PASSWORD="${RESTIC_PASS}"
export RESTIC_REPOSITORY=$REPO

echo "[$FECHA] Starting backup..." >> $LOG

# Backup Docker configuration
restic backup /opt/eduflix >> $LOG 2>&1

# Backup NGINX configuration
restic backup /mnt/datos/nginx >> $LOG 2>&1

# Backup Jellyfin configuration
restic backup /mnt/datos/jellyfin/config >> $LOG 2>&1

# Backup PostgreSQL dump
docker exec postgresql pg_dumpall -U eduflix > /mnt/datos/backups/postgres_dump.sql
restic backup /mnt/datos/backups/postgres_dump.sql >> $LOG 2>&1
rm /mnt/datos/backups/postgres_dump.sql

# Apply retention policy
restic forget --keep-daily 7 --keep-weekly 4 --keep-monthly 3 --prune >> $LOG 2>&1

# Send email notification
if [ $? -eq 0 ]; then
    echo "Backup completed successfully on $FECHA" | mail -s "EduFlix Agora - Backup OK" "${ADMIN_EMAIL}"
else
    echo "ERROR in backup on $FECHA. Check logs at $LOG" | mail -s "EduFlix Agora - Backup ERROR" "${ADMIN_EMAIL}"
fi

echo "[$FECHA] Backup finished." >> $LOG
