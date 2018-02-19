# Linda Notenchecker

Loggt sich automatisch in Linda ein und ruft den aktuellen Notenspiegel ab. Gleicht diese Liste mit der vorherigen ab - bei einer Abweichung (neue Noten online) wird eine Nachricht in Slack versendet.

# Anleitung
PHP-Skript mittels `$config` konfigurieren und automatisiert verarbeiten lassen, bspw. mittels Cronjob und PHP-CLI. Wichtig: PHP muss in das Verzeichnis schreiben dürfen, um die jeweils aktuelle Notenliste zwischenspeichern zu können.

```sudo crontab -e```

```* * * * * php /var/www/html/linda/linda.php```

Ruft minütlich das Skript im angegebenen Verzeichnis auf.

Zu Debuggingzwecken gibt das Skript standardmäßig die Notenliste als HTML-Ausgabe, wie sie vom Server kommt, aus.