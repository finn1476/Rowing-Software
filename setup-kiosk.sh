#!/bin/bash
set -e

export TZ="Europe/Berlin"

LOGFILE="/home/kiosk/Desktop/kiosk-setup.log"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOGFILE"
}

log_separator() {
    echo "==========================================" | tee -a "$LOGFILE"
    echo "== Neuer Durchlauf: $(date '+%Y-%m-%d %H:%M:%S') ==" | tee -a "$LOGFILE"
    echo "==========================================" | tee -a "$LOGFILE"
}
log_separator

UPDATE_URL="http://rowing-regatta-hoya.de/kiosk-setup.sh"
LOCAL_SCRIPT="/home/kiosk/Desktop/kiosk-setup.sh"
TMP_SCRIPT="/home/kiosk/Desktop/kiosk-setup.sh.new"

# 0. Lade neue Version runter
curl -fsSL "$UPDATE_URL" -o "$TMP_SCRIPT" || {
  log "Update-Script konnte nicht heruntergeladen werden, fahre ohne Update fort."
}

# 2. Vergleiche neue Version mit aktueller (wenn lokale Datei existiert)
if [ -f "$LOCAL_SCRIPT" ]; then
    if ! cmp -s "$LOCAL_SCRIPT" "$TMP_SCRIPT"; then
        log "Neue Script-Version gefunden, aktualisiere..."
        sudo mv "$TMP_SCRIPT" "$LOCAL_SCRIPT"
        sudo chmod +x "$LOCAL_SCRIPT"
        log "Starte neue Script-Version..."
        exec sudo "$LOCAL_SCRIPT" "$@"
        exit
    else
        echo "Script ist aktuell."
        log "Script ist aktuell."
        rm "$TMP_SCRIPT"
    fi
else
    log "Lokale Script-Datei nicht gefunden, speichere neue Version..."
    sudo mv "$TMP_SCRIPT" "$LOCAL_SCRIPT"
    sudo chmod +x "$LOCAL_SCRIPT"
    log "System wird jetzt in 10 Sekunden neu gestartet, um das Update anzuwenden..."

    for i in $(seq 10 -1 1); do
        log "Neustart in $i Sekunden..."
        sleep 1
    done

    sudo /sbin/reboot
    exit
fi



# 0.5 Cronjob für automatischen Update-Check alle 5 Minuten hinzufügen (im root-Crontab)

CRON_JOB="*/5 * * * * /home/kiosk/Desktop/kiosk-setup.sh >/dev/null 2>&1"

# Bestehenden Crontab oder leer laden
CURRENT_CRON=$(sudo crontab -l 2>/dev/null || true)

if ! echo "$CURRENT_CRON" | grep -Fq "$CRON_JOB"; then
    (echo "$CURRENT_CRON"; echo "$CRON_JOB") | sudo crontab -
    log "Cronjob für automatisches Update alle 5 Minuten zum root Crontab hinzugefügt."
else
    log "Cronjob bereits vorhanden."
fi

# Zeitzone dauerhaft auf Europe/Berlin setzen
if [ "$(timedatectl show -p TimeZone --value)" != "Europe/Berlin" ]; then
    log "Setze System-Zeitzone auf Europe/Berlin..."
    sudo timedatectl set-timezone Europe/Berlin
    log "Zeitzone gesetzt."
else
    log "Zeitzone ist bereits auf Europe/Berlin eingestellt."
fi

# 1. Kiosk Nutzer anlegen, wenn nicht vorhanden
if ! id kiosk &>/dev/null; then
    sudo useradd -m -s /bin/bash kiosk
    echo "Benutzer 'kiosk' wurde angelegt."
    log "Benutzer 'kiosk' wurde angelegt."
fi

# Passwort setzen
echo "kiosk:kiosk" | sudo chpasswd

# 2. Firefox installieren, falls nicht vorhanden
if ! command -v firefox &>/dev/null; then
    log "Firefox ist nicht installiert. Installation wird durchgeführt..."
    sudo apt update
    sudo apt install -y firefox
fi

# 3. Automatischen Login konfigurieren
sudo mkdir -p /etc/lightdm/lightdm.conf.d
sudo tee /etc/lightdm/lightdm.conf.d/50-autologin.conf > /dev/null << EOF
[Seat:*]
autologin-user=kiosk
autologin-user-timeout=0
autologin-session=xfce
EOF

log "Automatischer Login für 'kiosk' konfiguriert."

# 4. Kiosk Benutzerrechte einschränken
# Entferne den Nutzer aus allen Gruppen außer den nötigsten (z.B. "kiosk" und "video" falls nötig)
sudo usermod -G "" kiosk

# Optional weitere Einschränkungen (z.B. shell beschränken oder Home dir Rechte setzen)
sudo chmod 700 /home/kiosk
sudo chown kiosk:kiosk /home/kiosk

log "Rechte für 'kiosk' eingeschränkt."

# 5. Autostart Ordner und Firefox Kiosk Autostart anlegen mit Überwachung

sudo -u kiosk mkdir -p /home/kiosk/.config/autostart

sudo tee /home/kiosk/.config/autostart/firefox-kiosk.desktop > /dev/null << EOF
[Desktop Entry]
Type=Application
Exec=/home/kiosk/start-firefox.sh
Hidden=false
NoDisplay=false
X-GNOME-Autostart-enabled=true
Name=Firefox Kiosk Mode
EOF

# 6. Startscript anlegen, das Firefox startet und bei Beenden neu bootet

sudo tee /home/kiosk/start-firefox.sh > /dev/null << 'EOF'
#!/bin/bash
# Firefox im Kiosk Modus starten, wenn beendet -> reboot

firefox --kiosk http://rowing-regatta-hoya.de

# Firefox ist beendet, jetzt System neu starten
sudo /sbin/reboot
EOF

sudo chmod +x /home/kiosk/start-firefox.sh
sudo chown kiosk:kiosk /home/kiosk/start-firefox.sh

# 7. Energiesparmodus und Bildschirmabschaltung deaktivieren für den Benutzer 'kiosk'

sudo -u kiosk mkdir -p /home/kiosk/.config/xfce4/xfconf/xfce-perchannel-xml

sudo tee /home/kiosk/.config/xfce4/xfconf/xfce-perchannel-xml/xscreensaver.xml > /dev/null << EOF
<?xml version="1.0" encoding="UTF-8"?>
<channel name="xscreensaver" version="1.0">
  <property name="mode" type="string" value="off"/>
</channel>
EOF

sudo tee /home/kiosk/.xsessionrc > /dev/null << 'EOF'
#!/bin/bash
# Bildschirm-Energiesparen deaktivieren
xset s off          # Screensaver aus
xset -dpms          # DPMS (Energiesparmodus) aus
xset s noblank      # Kein Bildschirm-Blanking

# Session starten
exec startxfce4
EOF

sudo chmod +x /home/kiosk/.xsessionrc
sudo chown kiosk:kiosk /home/kiosk/.xsessionrc

log "Energiesparoptionen deaktiviert."

# 8. Sudo ohne Passwort für reboot erlauben (für kiosk)
echo "kiosk ALL=(ALL) NOPASSWD: /sbin/reboot" | sudo tee /etc/sudoers.d/kiosk-reboot

log "Setup fertig! Bitte System neu starten, um den Kiosk-Modus zu testen."


MONITOR_SCRIPT="/home/kiosk/Desktop/kiosk-monitor.sh"
TMP_SCRIPT="/tmp/kiosk-monitor.sh.tmp"

mkdir -p "$(dirname "$MONITOR_SCRIPT")"

# Neue Version temporär schreiben
cat << 'EOF' > "$TMP_SCRIPT"
#!/bin/bash

export DISPLAY=:0
export XAUTHORITY=/home/kiosk/.Xauthority

LOGFILE="/home/kiosk/Desktop/kiosk-monitor.log"
MINIMIZED_FLAG="/home/kiosk/Desktop/firefox-minimized.flag"

log() {
    echo "$(TZ=Europe/Berlin date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOGFILE"
}

# Stelle sicher, dass wmctrl installiert ist
if ! command -v wmctrl &>/dev/null; then
    log "wmctrl nicht gefunden, versuche Installation..."
    sudo apt update && sudo apt install -y wmctrl
fi

# Firefox-Fenster finden (als Benutzer kiosk)
FIREFOX_STATE=$(sudo -u kiosk DISPLAY=$DISPLAY XAUTHORITY=$XAUTHORITY wmctrl -lG | grep -i firefox || true)

if [ -n "$FIREFOX_STATE" ]; then
    WIN_ID=$(echo "$FIREFOX_STATE" | awk '{print $1}')
    WIN_ATTR=$(xprop -id "$WIN_ID" _NET_WM_STATE 2>/dev/null || true)

    if echo "$WIN_ATTR" | grep -q "_NET_WM_STATE_HIDDEN"; then
        log "Firefox ist minimiert."

        if [ -f "$MINIMIZED_FLAG" ]; then
            source "$MINIMIZED_FLAG"
            if [ "$DISABLE_REBOOT" = "1" ]; then
                log "Neustart unterdrückt durch DISABLE_REBOOT=1 in der Flag-Datei."
                exit
            else
                log "Firefox war bereits beim letzten Check minimiert. System wird neu gestartet."
                sudo rm -f "$MINIMIZED_FLAG"
                sudo /sbin/reboot
                exit
            fi
        else
            log "Minimiert erkannt – Flag-Datei wird erstellt. Ändere DISABLE_REBOOT auf 1, um Neustart zu verhindern."
            cat << EOT > "$MINIMIZED_FLAG"
# Firefox wurde minimiert
# Setze DISABLE_REBOOT=1, um Neustart zu verhindern
DISABLE_REBOOT=0
EOT
            chown kiosk:kiosk "$MINIMIZED_FLAG"
        fi
    else
        log "Firefox ist aktiv."
        rm -f "$MINIMIZED_FLAG" 2>/dev/null || true
    fi
else
    log "Kein Firefox-Fenster gefunden."
    rm -f "$MINIMIZED_FLAG" 2>/dev/null || true
fi
EOF

# Hashwerte berechnen
if [ -f "$MONITOR_SCRIPT" ]; then
    OLD_HASH=$(sha256sum "$MONITOR_SCRIPT" | awk '{print $1}')
else
    OLD_HASH=""
fi
NEW_HASH=$(sha256sum "$TMP_SCRIPT" | awk '{print $1}')

# Vergleichen und ggf. aktualisieren
if [ "$OLD_HASH" != "$NEW_HASH" ]; then
    mv "$TMP_SCRIPT" "$MONITOR_SCRIPT"
    chmod +x "$MONITOR_SCRIPT"
    chown kiosk:kiosk "$MONITOR_SCRIPT"
    log "Script $MONITOR_SCRIPT wurde aktualisiert und ist ausführbar."
else
    rm -f "$TMP_SCRIPT"
    log "Script $MONITOR_SCRIPT ist bereits aktuell."
fi

# Cronjob im root-Crontab eintragen (alle 1 Minute), wenn noch nicht vorhanden
CRON_MONITOR="*/1 * * * * /home/kiosk/Desktop/kiosk-monitor.sh >/dev/null 2>&1"
CURRENT_ROOT_CRON=$(sudo crontab -l 2>/dev/null || true)

if ! echo "$CURRENT_ROOT_CRON" | grep -Fq "$CRON_MONITOR"; then
    (echo "$CURRENT_ROOT_CRON"; echo "$CRON_MONITOR") | sudo crontab -
    log "Cronjob für kiosk-monitor.sh wurde zum root-Crontab hinzugefügt."
else
    log "Cronjob für kiosk-monitor.sh ist bereits vorhanden."
fi

log "Setze Eigentümer und Rechte für $SETUP_SCRIPT..."

sudo chown root:root "$SETUP_SCRIPT"
sudo chmod 755 "$SETUP_SCRIPT"

OWNER=$(stat -c '%U:%G' "$SETUP_SCRIPT")
PERMS=$(stat -c '%a' "$SETUP_SCRIPT")

if [ "$OWNER" = "root:root" ] && [ "$PERMS" = "755" ]; then
    log "Rechte und Eigentümer korrekt gesetzt: $OWNER, Berechtigungen: $PERMS"
else
    log "Warnung: Rechte oder Eigentümer sind nicht korrekt! Aktuell: $OWNER, Berechtigungen: $PERMS"
fi


log "Setze Eigentümer und Rechte für $MONITOR_SCRIPT..."

sudo chown root:root "$MONITOR_SCRIPT"
sudo chmod 755 "$MONITOR_SCRIPT"

OWNER=$(stat -c '%U:%G' "$MONITOR_SCRIPT")
PERMS=$(stat -c '%a' "$MONITOR_SCRIPT")

if [ "$OWNER" = "root:root" ] && [ "$PERMS" = "755" ]; then
    log "Rechte und Eigentümer korrekt gesetzt: $OWNER, Berechtigungen: $PERMS"
else
    log "Warnung: Rechte oder Eigentümer sind nicht korrekt! Aktuell: $OWNER, Berechtigungen: $PERMS"
fi
