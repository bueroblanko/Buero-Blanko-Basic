# Neue Version ausrollen

Wichtig: Der Update-Checker liest die Datei `code-sync-plugin.php` direkt auf `main`.
Steht dort im Header eine höhere `Version:` als installiert, bieten alle Kundenseiten
das Update an und spielen es per Auto-Update ein. Ein Push auf `main` ist damit ein
Rollout auf alle Seiten. Releases und Tags spielen für die Verteilung keine Rolle.

1. Änderungen auf einem eigenen Branch machen, nie direkt auf `main`.
2. `Version:` im Header von `code-sync-plugin.php` erhöhen.
3. Jede geänderte PHP-Datei mit `php -l` prüfen (Snippets laufen per `eval()`).
4. ZIP aus dem Branch bauen. Der Ordner im ZIP muss genauso heißen wie auf der
   Testseite (`Buero-Blanko-Basic`), sonst legt WordPress eine zweite Kopie an.
5. Auf der Testseite dev.bueroblanko.de unter Plugins → Plugin hochladen →
   „Aktuelle Version ersetzen“ testen.
6. Erst danach den Pull Request nach `main` mergen.
7. Optional: Tag mit der Versionsnummer setzen, nur zur Nachvollziehbarkeit.
