#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
  echo "Uruchom skrypt przez sudo." >&2
  exit 1
fi

operator_user="${SUDO_USER:-ubuntu}"

apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y \
  docker.io docker-compose-v2 fail2ban ufw

install -d -m 0755 /etc/docker
cat > /etc/docker/daemon.json <<'JSON'
{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "10m",
    "max-file": "3"
  },
  "live-restore": true
}
JSON

systemctl enable --now docker
systemctl restart docker
usermod -aG docker "${operator_user}"

if ! swapon --show=NAME --noheadings | grep -q .; then
  fallocate -l 2G /swapfile
  chmod 600 /swapfile
  mkswap /swapfile
  swapon /swapfile
  grep -q '^/swapfile ' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

rm -f /etc/ssh/sshd_config.d/99-grzywniak-hardening.conf
cat > /etc/ssh/sshd_config.d/00-grzywniak-hardening.conf <<'SSH'
PubkeyAuthentication yes
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin no
X11Forwarding no
AllowTcpForwarding no
MaxAuthTries 4
LoginGraceTime 30
SSH

sshd -t
systemctl reload ssh

cat > /etc/fail2ban/jail.d/sshd.local <<'FAIL2BAN'
[sshd]
enabled = true
backend = systemd
bantime = 1h
findtime = 10m
maxretry = 5
FAIL2BAN

systemctl enable --now fail2ban
systemctl restart fail2ban

ufw default deny incoming
ufw default allow outgoing
ufw limit OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

echo "Konfiguracja hosta zakończona. Otwórz nową sesję SSH przed zamknięciem bieżącej."
