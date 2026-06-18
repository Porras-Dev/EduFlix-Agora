#!/bin/bash
# =============================================================
# EduFlix Agora - Telegram Alert Script
# Description: Sends a message to a Telegram chat via Bot API.
#              Called by Fail2Ban on every ban/unban event.
# Usage: ./telegram_alert.sh "Your message here"
# Required env vars: TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID
# =============================================================

BOT_TOKEN="${TELEGRAM_BOT_TOKEN}"
CHAT_ID="${TELEGRAM_CHAT_ID}"
MESSAGE="$1"

curl -s "https://api.telegram.org/bot${BOT_TOKEN}/sendMessage"     --data-urlencode "chat_id=${CHAT_ID}"     --data-urlencode "text=${MESSAGE}"     --data-urlencode "parse_mode=HTML" > /dev/null
