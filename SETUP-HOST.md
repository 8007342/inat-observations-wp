# Host Setup (One-Time) - Fedora Silverblue

Before using `./inat.sh`, set up Docker on the **host** (outside toolbox):

## 1. Install Docker on Host

```bash
# On the HOST (not in toolbox)
sudo rpm-ostree install docker docker-compose

# Reboot to apply (Silverblue is immutable)
sudo systemctl reboot
```

## 2. Enable Docker Service

```bash
# After reboot, on the HOST
sudo systemctl enable --now docker

# Verify it's running
sudo systemctl status docker
```

## 3. Add Your User to Docker Group

```bash
# On the HOST
sudo usermod -aG docker $USER

# Apply group membership without logout
newgrp docker

# Verify you can run docker without sudo
docker ps
```

## 4. Now Use inat.sh

```bash
# From the project directory
cd ~/src/inat-observations-wp
./inat.sh
```

The script will:
- Create toolbox "inat-observations"
- Install Docker CLI tools inside toolbox
- Use the host's Docker daemon (via `/var/run/docker.sock`)
- Start WordPress + MySQL

---

## Troubleshooting

### "Cannot connect to Docker daemon"

**Cause**: Docker not running on host or permission issue

**Fix**:
```bash
# Exit toolbox
exit

# On HOST, check Docker status
sudo systemctl status docker

# If not running, start it
sudo systemctl start docker

# Re-add to group if needed
sudo usermod -aG docker $USER
newgrp docker

# Re-enter toolbox
toolbox enter inat-observations
```

---

### "Permission denied while trying to connect to Docker"

**Cause**: Not in docker group

**Fix**:
```bash
# Exit toolbox
exit

# On HOST
sudo usermod -aG docker $USER
newgrp docker

# Log out and back in (or reboot)
```

---

### Alternative: Docker on Host, No Toolbox

If you prefer to run Docker directly on the host without toolbox:

```bash
# On HOST (skip inat.sh)
cd ~/src/inat-observations-wp
docker-compose up
```

This works but mixes dependencies with your host system (not recommended on Silverblue).

---

**Summary**: Install Docker on host → Add user to docker group → Run `./inat.sh`
