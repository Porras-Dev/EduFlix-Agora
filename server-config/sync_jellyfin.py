#!/usr/bin/env python3
"""
EduFlix Agora - Jellyfin to PostgreSQL Sync Script
Syncs users, content and playback history every 5 minutes via cron.
Required environment variables: JELLYFIN_API_KEY, DB_PASS
"""

import requests
import psycopg2
import pytz
import os
from datetime import datetime

# Jellyfin configuration
JELLYFIN_URL = "http://localhost:8096"
API_KEY      = os.environ.get("JELLYFIN_API_KEY")

# PostgreSQL configuration
DB_HOST = "172.20.0.10"
DB_PORT = 5432
DB_NAME = "jellyfin_bd"
DB_USER = "jellyfin_usuario"
DB_PASS = os.environ.get("DB_PASS")

TZ_LOCAL = pytz.timezone("Europe/Madrid")

def jellyfin_get(endpoint):
    sep = "&" if "?" in endpoint else "?"
    url = f"{JELLYFIN_URL}{endpoint}{sep}api_key={API_KEY}"
    r = requests.get(url, timeout=10)
    if r.status_code == 200:
        return r.json()
    return None

def connect_db():
    return psycopg2.connect(
        host=DB_HOST, port=DB_PORT, dbname=DB_NAME,
        user=DB_USER, password=DB_PASS, application_name="sync_jellyfin"
    )

def sync_users(conn):
    jellyfin_users = jellyfin_get("/Users")
    if not jellyfin_users:
        print("[!] Could not fetch users from Jellyfin")
        return
    cur = conn.cursor()
    cur.execute("SELECT nombre FROM usuarios")
    db_names = {row[0] for row in cur.fetchall()}
    jellyfin_names = {}
    for u in jellyfin_users:
        name = u.get("Name", "")
        is_admin = u.get("Policy", {}).get("IsAdministrator", False)
        jellyfin_names[name] = "admin" if is_admin else "alumno"
    for name, role in jellyfin_names.items():
        if name not in db_names:
            cur.execute(
                "INSERT INTO usuarios (nombre, rol, fecha_registro) VALUES (%s, %s, CURRENT_DATE) ON CONFLICT DO NOTHING",
                (name, role)
            )
    for name in db_names:
        if name not in jellyfin_names:
            cur.execute("DELETE FROM usuarios WHERE nombre = %s", (name,))
    conn.commit()
    cur.close()

def sync_content(conn):
    items = jellyfin_get("/Items?IncludeItemTypes=Video&Recursive=true&Fields=Path,RunTimeTicks")
    if not items or "Items" not in items:
        return
    cur = conn.cursor()
    cur.execute("SELECT titulo FROM contenido")
    db_titles = {row[0] for row in cur.fetchall()}
    subjects = {
        "Informatica":    "Informatica",
        "Matematicas":    "Matematicas",
        "Fisica-Quimica": "Fisica y Quimica",
        "Historia":       "Historia",
        "Ingles":         "Ingles",
    }
    jellyfin_titles = {}
    for item in items["Items"]:
        title = item.get("Name", "")
        ticks = item.get("RunTimeTicks", 0)
        duration_min = round(ticks / 600000000) if ticks else 0
        path = item.get("Path", "")
        subject = "General"
        for folder, subject_name in subjects.items():
            if folder in path:
                subject = subject_name
                break
        jellyfin_titles[title] = {"subject": subject, "duration_min": duration_min}
    for title, data in jellyfin_titles.items():
        if title not in db_titles:
            cur.execute(
                "INSERT INTO contenido (titulo, materia, duracion_min, idioma) VALUES (%s, %s, %s, %s) ON CONFLICT DO NOTHING",
                (title, data["subject"], data["duration_min"], "Ingles")
            )
    for title in db_titles:
        if title not in jellyfin_titles:
            cur.execute("DELETE FROM contenido WHERE titulo = %s", (title,))
    conn.commit()
    cur.close()

def sync_playback(conn):
    jellyfin_users = jellyfin_get("/Users")
    if not jellyfin_users:
        return
    cur = conn.cursor()
    for user in jellyfin_users:
        name = user.get("Name", "")
        user_id = user.get("Id", "")
        cur.execute("SELECT id FROM usuarios WHERE nombre = %s", (name,))
        row = cur.fetchone()
        if not row:
            continue
        db_user_id = row[0]
        history = jellyfin_get(
            f"/Users/{user_id}/Items?Recursive=true&IncludeItemTypes=Video&Fields=UserData"
        )
        if not history or "Items" not in history:
            continue
        for item in history["Items"]:
            title = item.get("Name", "")
            user_data = item.get("UserData", {})
            play_count = user_data.get("PlayCount", 0)
            last_played = user_data.get("LastPlayedDate", None)
            played_pct = user_data.get("PlayedPercentage", None)
            if play_count == 0 or not last_played:
                continue
            cur.execute("SELECT id FROM contenido WHERE titulo = %s", (title,))
            row = cur.fetchone()
            if not row:
                continue
            db_content_id = row[0]
            try:
                date_utc = datetime.fromisoformat(last_played.replace("Z", "+00:00"))
                date = date_utc.astimezone(TZ_LOCAL)
            except Exception:
                date = datetime.now(TZ_LOCAL)
            percentage = 100 if played_pct is None else min(100, round(played_pct))
            cur.execute("""
                INSERT INTO reproducciones (id_usuario, id_contenido, fecha_reproduccion, porcentaje_completado)
                VALUES (%s, %s, %s, %s)
                ON CONFLICT (id_usuario, id_contenido) DO UPDATE SET
                    fecha_reproduccion = EXCLUDED.fecha_reproduccion,
                    porcentaje_completado = EXCLUDED.porcentaje_completado
            """, (db_user_id, db_content_id, date, percentage))
    conn.commit()
    cur.close()

def main():
    print(f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] Starting Jellyfin sync")
    try:
        conn = connect_db()
        sync_users(conn)
        sync_content(conn)
        sync_playback(conn)
        conn.close()
        print(f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] Sync completed successfully")
    except Exception as e:
        print(f"[ERROR] {e}")

if __name__ == "__main__":
    main()
