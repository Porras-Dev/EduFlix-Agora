# EduFlix Ágora — Self-Hosted Educational Streaming Platform

A fully self-hosted educational streaming platform designed and deployed for **IES Ágora (Cáceres, Spain)** as a Final Degree Project for the Higher Degree in Network Systems Administration (ASIR), academic year 2025/2026.

The platform allows students and teachers to access educational video content from any device — with the same experience as a modern streaming service, but running entirely on school infrastructure, with no third-party dependencies, zero licensing costs, and full data sovereignty.

---

## Table of Contents

1. [Architecture](#1-architecture)
2. [Tech stack](#2-tech-stack)
3. [Cybersecurity](#3-cybersecurity)
4. [Repository contents](#4-repository-contents)
5. [Deployment](#5-deployment)
6. [What's not included](#6-whats-not-included)
7. [Sustainability analysis](#7-sustainability-analysis)
8. [Future improvements](#8-future-improvements)
9. [Author](#9-author)

---

## 1. Architecture

The platform runs on a single repurposed desktop (Intel i5-10400, 16 GB RAM) running **Proxmox VE** as the hypervisor, with three LXC containers over Ubuntu Server 24.04.

```
 ┌─────────────────────────────────────────────────────────────────────┐
 │                       PROXMOX VE HOST                               │
 │             LAN 192.168.1.20 · ZeroTier 10.204.191.210              │
 │                  Intel i5-10400 · 16 GB RAM                         │
 └──────────────────────────┬──────────────────────────────────────────┘
                            │  LXC containers (Ubuntu Server 24.04)
             ┌──────────────┼──────────────┐
             │              │              │
             ▼              ▼              ▼
 ┌────────────────┐ ┌──────────────┐ ┌──────────────────────┐
 │   LXC 100      │ │   LXC 101    │ │   LXC 102            │
 │  servidor-     │ │  cibersegu-  │ │  servicios-red       │
 │  principal     │ │  ridad       │ │                      │
 │                │ │              │ │                      │
 │ LAN 192.168.   │ │ LAN 192.168. │ │ LAN 192.168.1.22     │
 │ 1.21           │ │ 1.23         │ │ ZeroTier             │
 │ ZeroTier       │ │ ZeroTier     │ │ 10.204.191.148       │
 │ 10.204.191.231 │ │ 10.204.191.  │ │                      │
 │                │ │ 184          │ │ · BIND9 (DNS)        │
 │ · Jellyfin     │ │              │ │ · Chrony (NTP)       │
 │ · PostgreSQL   │ │ · Wazuh SIEM │ │ · vsftpd (FTP/TLS)   │
 │ · NGINX        │ │ · Suricata   │ │ · Postfix (SMTP)     │
 │ · pgAdmin      │ │   IDS        │ │ · Prometheus         │
 │ · PHP Portal   │ │              │ │ · Grafana            │
 │ · Fail2Ban     │ │              │ │                      │
 │ · CrowdSec     │ │              │ │                      │
 └────────────────┘ └──────────────┘ └──────────────────────┘
             │              │              │
             └──────────ZeroTier VPN───────┘
                 Network ID: 3b19b3a7161ea5af
                 Private virtual overlay across all nodes
```

### Data flow

```
  Student / Teacher
        │
        ▼
  ZeroTier VPN  ──or──  LAN (192.168.1.x)
        │
        ▼
  NGINX (reverse proxy + HTTPS + security headers)
        │
        ├──► Jellyfin  ──────────────► media files (LXC 100)
        │
        └──► PHP Portal ──► PostgreSQL (auth + metrics + logs)
                   │
                   └──► Node.js WebSocket (real-time log streaming)

  Prometheus (LXC 102) ──scrapes──► all 3 nodes
        │
        ▼
  Grafana dashboards + 5 alert rules

  Wazuh agents (all nodes) ──► Wazuh Manager (LXC 101)
  Suricata ──────────────────► IDS alerts (LXC 101)
  Fail2Ban ──────────────────► IP ban + Telegram alert (LXC 100)
```

---

## 2. Tech stack

### Virtualisation & containers

| Technology | Version | Role |
|---|---|---|
| Proxmox VE | 9.1 | Hypervisor — manages all 3 LXC containers |
| LXC | — | Lightweight OS containers over Ubuntu 24.04 |
| Docker + Docker Compose | — | Service orchestration on LXC 100 |

### Media & database

| Technology | Role |
|---|---|
| Jellyfin | Self-hosted media server with custom IES Ágora CSS theme and logo |
| PostgreSQL 16 | Relational database tracking users, content and access logs |
| pgAdmin | PostgreSQL web administration interface |
| Python sync script | Real-time sync between Jellyfin API and PostgreSQL |
| Whisper AI | Automatic subtitle generation for educational videos |

### Web & proxy

| Technology | Role |
|---|---|
| NGINX | Reverse proxy, HTTPS termination, security headers |
| HAProxy | Load balancer |
| PHP portal | Custom admin portal — authentication, metrics, WebSocket integration |
| Node.js WebSocket | Real-time log streaming to the PHP portal |

### Cybersecurity

| Technology | Role |
|---|---|
| Wazuh SIEM | Centralised security event monitoring — agents on all 3 nodes |
| Suricata IDS | Real-time network traffic inspection and intrusion detection |
| Fail2Ban | Automated IP banning on brute-force detection + Telegram alerts |
| CrowdSec | Collaborative threat intelligence — second security layer |
| UFW | Host firewall on all nodes |

### Monitoring & backups

| Technology | Role |
|---|---|
| Prometheus | Metrics collection from all nodes |
| Grafana | Dashboards + 5 configured alert rules |
| Restic | Encrypted, incremental automated backups with retention policy |

### Network services (LXC 102)

| Technology | Role |
|---|---|
| BIND9 | Internal DNS server — `agora.local` zone |
| Chrony | NTP time synchronisation across all nodes |
| vsftpd | FTP server with TLS for professor content uploads |
| Postfix | Internal SMTP mail server |

### Remote access & client

| Technology | Role |
|---|---|
| ZeroTier | Private virtual overlay network across all nodes |
| Java desktop client | Jellyfin monitoring client — LAN and ZeroTier versions |

---

## 3. Cybersecurity

The platform implements a **5-layer security model** where each layer catches what the previous one misses:

```
  Incoming traffic
        │
        ▼
  ┌─────────────┐
  │    UFW      │  Layer 1 — host firewall: blocks all ports except
  │  Firewall   │  explicitly allowed ones on every node
  └──────┬──────┘
         │
         ▼
  ┌─────────────┐
  │  Fail2Ban   │  Layer 2 — brute force detection: monitors SSH,
  │             │  NGINX and Jellyfin logs; bans offending IPs
  │             │  automatically and sends instant Telegram alerts
  └──────┬──────┘
         │
         ▼
  ┌─────────────┐
  │  CrowdSec   │  Layer 3 — collaborative threat intelligence:
  │             │  blocks IPs flagged by the global CrowdSec
  │             │  community before they even connect
  └──────┬──────┘
         │
         ▼
  ┌─────────────┐
  │ Wazuh SIEM  │  Layer 4 — centralised event correlation:
  │             │  agents on all 3 nodes; generates Level 10
  │             │  alerts on brute-force; monitors file integrity,
  │             │  system calls and log anomalies in real time
  └──────┬──────┘
         │
         ▼
  ┌─────────────┐
  │  Suricata   │  Layer 5 — deep packet inspection: analyses
  │    IDS      │  every network packet in real time; detects
  │             │  intrusion patterns, port scans and exploits
  └─────────────┘
```

### Additional hardening

- SSH on custom port, key-only authentication, `AllowUsers` whitelist across all nodes
- NGINX with strict security headers (HSTS, X-Frame-Options, CSP)
- Restic backups encrypted at rest with automated scheduling and retention policy
- Weekly automated security audit script (`server-config/auditoria.sh`)
- Live brute-force demo tested during project presentation: triggers Wazuh Level 10 alert + Fail2Ban ban + Telegram notification in real time

---

## 4. Repository contents

| Path | Description |
|---|---|
| `EduFlix-Jellyfin-Client.jar` | Java desktop client — LAN version |
| `EduFlix-Jellyfin-Client-ZeroTier.jar` | Java desktop client — ZeroTier remote version |
| `EduFlix-Agora-Network-Diagram.pkt` | Cisco Packet Tracer network diagram |
| `EduFlix-Agora-Network-Diagram.pkz` | Cisco Packet Tracer network diagram (packaged) |
| `EduFlix-Agora-Project-Documentation.pdf` | Full project documentation (137 pages) |
| `EduFlix-Agora-Technical-Annex.pdf` | Technical annex with step-by-step installation (257 pages) |
| `portal/` | PHP administration portal source code |
| `portal/index.php` | Main portal entry point |
| `portal/login.php` | Authentication system |
| `portal/metricas.php` | Metrics and usage dashboard |
| `portal/logs.php` | Real-time log viewer (WebSocket) |
| `portal/jellyfin.php` | Jellyfin integration and content management |
| `portal/basedatos.php` | Database administration interface |
| `portal/backups.php` | Backup status and management |
| `portal/servicios.php` | Services status overview |
| `portal/includes/` | Shared PHP components (auth, layout, functions) |
| `server-config/docker-compose.yml` | Docker Compose — Jellyfin, PostgreSQL, NGINX, pgAdmin |
| `server-config/auditoria.sh` | Weekly automated security audit script |
| `server-config/backup.sh` | Restic backup automation script |
| `server-config/telegram_alert.sh` | Fail2Ban → Telegram notification script |
| `server-config/sync_jellyfin.py` | Jellyfin API to PostgreSQL real-time sync |
| `server-config/update_fail2ban_nginx.sh` | Fail2Ban log path updater (runs on reboot) |
| `server-config/websocket/server.js` | Node.js WebSocket server for real-time log streaming |
| `.env.example` | Environment variable template |

---

## 5. Deployment

> Full step-by-step installation instructions are documented in `EduFlix-Agora-Technical-Annex.pdf` (257 pages). This section provides a high-level overview of the deployment order.

### Hardware requirements

| Component | Minimum | Used in this project |
|---|---|---|
| CPU | 4 cores | Intel i5-10400 (6c/12t) |
| RAM | 8 GB | 16 GB DDR4 |
| Storage | 256 GB SSD + HDD | 256 GB NVMe + 1 TB HDD |
| Network | 1 Gbps | Integrated |

### Deployment order

**1. Proxmox VE**

- Install Proxmox VE 9.1 on the host machine
- Create 3 LXC containers with Ubuntu Server 24.04

**2. LXC 102 — Network services (deploy first)**

```bash
# Install: BIND9, Chrony, vsftpd, Postfix, Prometheus, Grafana
# Configure the agora.local DNS zone
# Set Chrony as NTP source for all nodes
```

**3. LXC 100 — Main server**

```bash
# Install Docker + Docker Compose
# Copy server-config/docker-compose.yml to the server
cp .env.example .env
# Fill in .env with real values
docker compose up -d
# Install and configure: Fail2Ban, CrowdSec, NGINX, SSH hardening
```

**4. LXC 101 — Cybersecurity**

```bash
# Deploy Wazuh Manager
# Install Wazuh agents on all 3 nodes
# Deploy Suricata IDS
```

**5. ZeroTier**

- Create a ZeroTier network at [my.zerotier.com](https://my.zerotier.com)
- Join all 3 nodes and the Proxmox host to the network
- Assign static IPs within ZeroTier

### Environment variables

Copy `.env.example` to `.env` and fill in the required values:

| Variable | Description |
|---|---|
| `JELLYFIN_API_KEY` | Jellyfin API key (Dashboard → API Keys) |
| `POSTGRES_PASSWORD` | PostgreSQL admin password |
| `TELEGRAM_BOT_TOKEN` | Telegram bot token for Fail2Ban alerts |
| `TELEGRAM_CHAT_ID` | Telegram chat ID to receive alerts |
| `ZEROTIER_NETWORK_ID` | ZeroTier network ID |

---

## 6. What's not included

The repository contains source code, scripts and documentation only. The following are excluded for security or privacy reasons:

| Excluded | Reason |
|---|---|
| `.env` | Contains passwords, API keys and tokens |
| SSL/TLS certificates | Site-specific — must be generated per deployment |
| Jellyfin media library | Copyrighted educational content |
| PostgreSQL database dumps | Contains real student and teacher data (GDPR) |
| Wazuh agent keys | Node-specific security credentials |
| SSH private keys | Private authentication material |
| ZeroTier auth token | Network-specific secret |

When deploying from this repository, all credentials must be created fresh. See `EduFlix-Agora-Technical-Annex.pdf` for the complete setup procedure.

---

## 7. Sustainability analysis

The entire infrastructure runs on a **reused 5-year-old desktop**, extending its useful life and avoiding electronic waste. All software is 100% free and open source — zero licensing costs.

| | EduFlix Ágora (local) | Cloud equivalent (AWS) |
|---|---|---|
| Hardware cost | 0 € (reused equipment) | Included in monthly cost |
| Software licences | 0 € (100% open source) | Included in monthly cost |
| Annual energy cost | ~103 € | Included in monthly cost |
| **Total annual cost** | **~103 €** | **~4,440 €** |
| **Annual saving** | **~4,337 €** | — |

---

## 8. Future improvements

- VLAN segmentation (802.1Q) to isolate production, cybersecurity and management networks
- Let's Encrypt TLS via internal Step CA
- Per-user Jellyfin library permissions based on enrolled subjects
- Migration from FTP to SFTP for professor content uploads
- High availability with a second Proxmox node
- Unified service dashboard (Heimdall or Homepage)
- Full DNS resolution for all services including ZeroTier IPs

---

## 9. Author

**Sergio Porras Martín**
IES Ágora, Cáceres — ASIR 2025/2026

[![GitHub](https://img.shields.io/badge/GitHub-Porras--Dev-black?logo=github)](https://github.com/Porras-Dev)

---

## Licence

This project is licensed under the MIT Licence — see the [LICENSE](LICENSE) file for details.
