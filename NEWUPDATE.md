# Neue Version ausrollen

Seit 0.0.15 gibt es zwei Update-Kanäle:

- `main`: liest nur Testseiten. Testseite ist eine Seite mit dieser Zeile in der
  `wp-config.php`: `define( 'BB_BASIC_UPDATE_KANAL', 'test' );`
  Ab 0.0.17.7 geht es auch ohne `wp-config.php`: Zeile `kanal=test` (und optional
  `zweig=name-des-branches`) in der Schalter-Datei `wp-content/bb-developer-modus.php`.
- `live`: lesen alle anderen Seiten.

Der Update-Checker liest `code-sync-plugin.php` auf dem jeweiligen Branch. Steht dort
eine höhere `Version:` als installiert, spielt die Seite das Update per Auto-Update ein.
Releases und Tags spielen für die Verteilung keine Rolle.

## Ablauf

1. Änderung auf eigenem Branch, `Version:` im Header von `code-sync-plugin.php` erhöhen.
2. Pull Request nach `main`. Die Prüfung „PHP-Syntax“ muss grün sein.
3. PR mergen. Jetzt bekommen nur die Testseiten die neue Version.
   Sofort statt nach bis zu 12 h: Dashboard → Aktualisierungen → „Erneut prüfen“.
4. Auf dev.bueroblanko.de prüfen.
5. Freigeben für alle: github.com → Pull requests → New pull request →
   base `live`, compare `main` → Create pull request → Merge.

## Testseite auf einem Entwicklungs-Branch

Eine Testseite kann statt `main` einem Entwicklungs-Branch folgen. Dafür zusätzlich in
die `wp-config.php`: `define( 'BB_BASIC_TEST_ZWEIG', 'name-des-branches' );`
Ohne FTP: Test-ZIP mit einer Datei `zweig.txt` (Inhalt: Branch-Name) im Plugin-Ordner
im Backend hochladen (oder per FTP in den Plugin-Ordner legen). Die Seite merkt sich den
Branch, und diese Wahl geht vor die Konstante in der `wp-config.php`.
Testseiten sehen alle 10 Minuten nach und spielen neue Versionen sofort selbst ein.
Jeder Push auf den Branch braucht eine höhere `Version:` (z. B. 0.0.16.1, 0.0.16.2),
sonst sieht die Seite ihn nicht. Vor dem PR nach `main` die Version auf die nächste
dreistellige Nummer setzen, die höher ist als alle Zwischenstände.

## Fehler beheben

WordPress spielt nur höhere Versionen ein. Einen Fehler mit einer neuen, höheren
Version beheben, `live` nicht zurücksetzen.

## Test-ZIP für eine einzelne Seite

Der Ordner im ZIP muss genauso heißen wie auf der Seite (`Buero-Blanko-Basic`),
sonst legt WordPress eine zweite Kopie an. Hochladen unter Plugins → Plugin
hochladen → „Aktuelle Version durch hochgeladene Version ersetzen“.
