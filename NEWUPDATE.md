# Neue Version ausrollen

Seit 0.0.15 gibt es zwei Update-Kanäle:

- `main`: liest nur Testseiten. Testseite ist eine Seite mit dieser Zeile in der
  `wp-config.php`: `define( 'BB_BASIC_UPDATE_KANAL', 'test' );`
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

## Fehler beheben

WordPress spielt nur höhere Versionen ein. Einen Fehler mit einer neuen, höheren
Version beheben, `live` nicht zurücksetzen.

## Test-ZIP für eine einzelne Seite

Der Ordner im ZIP muss genauso heißen wie auf der Seite (`Buero-Blanko-Basic`),
sonst legt WordPress eine zweite Kopie an. Hochladen unter Plugins → Plugin
hochladen → „Aktuelle Version durch hochgeladene Version ersetzen“.
