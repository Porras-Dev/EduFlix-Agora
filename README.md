# EduFlix Ágora 🎬

Private educational streaming platform designed and deployed for **IES Ágora (Cáceres, Spain)** as a Final Degree Project for the Higher Degree in Network Systems Administration (ASIR), academic year 2025/2026.

> *"Why should a school depend on third-party platforms when it can run its own?"*

The platform allows students and teachers to access educational video content from any device on the school network — with the same experience as a modern streaming service, but fully self-hosted, private, and built entirely with free and open-source software.

---

## 🏗️ Infrastructure

| Node | Role | IP (LAN) | IP (ZeroTier) |
|---|---|---|---|
| Proxmox VE Host | Hypervisor (Intel i5-10400, 16GB RAM) | 192.168.1.20 | 10.204.191.210 |
| LXC 100 — servidor-principal | Jellyfin, PostgreSQL, NGINX, pgAdmin, PHP Portal, Fail2Ban, CrowdSec | 192.168.1.21 | 10.204.191.231 |
| LXC 101 — ciberseguridad | Wazuh SIEM, Suricata IDS | 192.168.1.23 | 10.204.191.184 |
| LXC 102 — servicios-red | BIND9, Chrony, vsftpd, Postfix, Prometheus, Grafana | 192.168.1.22 | 10.204.191.148 |

All services containerized with **Docker + Docker Compose** on LXC 100. Remote access via **ZeroTier** VPN across all nodes.

---

## 🛠️ Full Tech Stack

**Virtualization & Containers**
- Proxmox VE 9.1 as hypervisor — LXC containers over Ubuntu Server 24.04
- Docker + Docker Compose for service orchestration on LXC 100

**Media & Database**
- Jellyfin — media server with custom IES Ágora CSS theme and logo
- PostgreSQL 16 + pgAdmin — relational database tracking users and content
- Python sync script — real-time sync between Jellyfin API and PostgreSQL

**Web & Proxy**
- NGINX — reverse proxy with HTTPS and security headers
- HAProxy — load balancer
- Custom PHP admin portal — authentication, WebSockets, PostgreSQL integration
- Node.js WebSocket server — real-time log streaming

**Cybersecurity**
- Wazuh SIEM — full agent deployment on all nodes, real-time event correlation
- Suricata IDS — network traffic inspection and intrusion detection
- Fail2Ban — brute force detection with real-time **Telegram alerts**
- CrowdSec — collaborative threat intelligence
- SSH hardening — custom port, key-only auth, IP whitelisting
- UFW firewall

**Monitoring & Backups**
- Prometheus + Grafana — metrics collection and dashboards with 5 alert rules
- Restic — encrypted, incremental automated backups

**Network Services**
- BIND9 — internal DNS server (agora.local zone)
- Chrony — NTP time synchronization
- vsftpd — FTP server with TLS for professor content uploads
- Postfix — internal mail server

**Remote Access & AI**
- ZeroTier — private virtual network across all nodes
- Whisper AI — automatic subtitle generation for educational videos

**Desktop Client**
- Java application — Jellyfin monitoring client (two versions: LAN + ZeroTier)

---

## 🔐 Cybersecurity Features

- Multi-layer protection: UFW → Fail2Ban → CrowdSec → Wazuh → Suricata
- Wazuh SIEM monitoring all 3 nodes with agents — generates Level 10 alerts on brute force
- Suricata inspecting every network packet in real time
- Fail2Ban banning IPs automatically with instant Telegram notifications
- SSH hardened across all nodes (custom port, no password auth, AllowUsers whitelist)
- Restic encrypted backups with automated scheduling and retention policy
- Weekly automated security audit script

---

## 💰 Sustainability Analysis

| | Local (EduFlix Ágora) | Cloud equivalent (AWS) |
|---|---|---|
| Hardware cost | 0 € (reused equipment) | Included |
| Software licenses | 0 € (100% open source) | Included |
| Annual energy cost | ~103 € | Included |
| **Total annual cost** | **~103 €** | **~4,440 €** |
| **Annual savings** | **~4,337 €** | — |

The entire infrastructure runs on a **reused 5-year-old desktop**, extending its useful life and avoiding electronic waste. All software is free and open source — zero licensing costs.

---

## 📁 Repository Contents

| File | Description |
|---|---|
| `EduFlix-Jellyfin-Client.jar` | Java desktop client (LAN version) |
| `EduFlix-Jellyfin-Client-ZeroTier.jar` | Java desktop client (ZeroTier remote version) |
| `ARQUITECTURA PROYECTO.pkt` | Cisco Packet Tracer network diagram |
| `ARQUITECTURA PROYECTO.pkz` | Cisco Packet Tracer network diagram (packaged) |
| `Sergio_Porras_Martin_Proyecto_Fin_Grado_EduFlix_Agora.pdf` | Full project documentation (137 pages) |
| `Sergio_Porras_Martin_Anexo_Supremo_EduFlix_Agora.pdf` | Technical annex with step-by-step installation (257 pages) |
| `server-config/` | Server scripts and Docker configuration |
| `server-config/auditoria.sh` | Weekly security audit script |
| `server-config/backup.sh` | Automated Restic backup script |
| `server-config/telegram_alert.sh` | Fail2Ban Telegram notification script |
| `server-config/sync_jellyfin.py` | Jellyfin to PostgreSQL sync script |
| `server-config/update_fail2ban_nginx.sh` | Fail2Ban log path updater on reboot |
| `server-config/docker-compose.yml` | Docker Compose configuration |
| `server-config/portal/config.php` | PHP administration portal configuration |
| `server-config/websocket/server.js` | Node.js WebSocket log streaming server |

---

## 🔮 Future Improvements

- VLAN segmentation (802.1Q) to isolate production, cybersecurity and management networks
- Let's Encrypt TLS certificate via internal Step CA
- Per-user Jellyfin library permissions by enrolled subjects
- Migration from FTP to SFTP for professor uploads
- High availability with a second Proxmox node
- Unified dashboard with Heimdall or Homepage
- Full DNS resolution for all services including ZeroTier IPs

---

## 👨‍💻 Author

**Sergio Porras Martín**
IES Ágora, Cáceres — 2025/2026

[![GitHub](https://img.shields.io/badge/GitHub-Porras--Dev-black?logo=github)](https://github.com/Porras-Dev)
