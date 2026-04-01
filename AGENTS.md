# Hinweise für KI-Coding-Agenten

Diese Datei richtet sich an automatisierte Assistenten (z. B. Cursor Agents), die in diesem Repository arbeiten.

## Überblick

- Das Projekt ist derzeit minimal; die öffentliche Kurzbeschreibung steht in [README.md](README.md).
- Sobald Code, Paketmanager oder CI hinzukommen, sollten die folgenden Abschnitte entsprechend ergänzt werden.

## Arbeitsweise

- Änderungen **fokussiert und klein** halten; kein Refactoring „nebenbei“, wenn es nicht ausdrücklich gewünscht ist.
- Vor größeren Eingriffen die vorhandene Struktur und Namensgebung lesen und **übernehmen** (Imports, Formatierung, Stil).
- Keine sensiblen Daten committen (Secrets, Tokens, lokale Pfade mit persönlichen Informationen).

## Qualitätssicherung (wenn vorhanden)

- Nach dem Hinzufügen von Build- oder Test-Setup: vor dem Abschluss **Lint** und **Tests** ausführen, sofern im Repository dokumentiert oder in `package.json` / Makefile / CI-Konfiguration erkennbar.
- Fehlschlagende Checks nach Möglichkeit beheben; andernfalls kurz dokumentieren, was blockiert.

## Git

- Auf dem vorgegebenen Feature-Branch arbeiten; nicht ohne Anweisung auf andere Branches pushen.
- Aussagekräftige Commit-Messages; logisch getrennte Änderungen ggf. in mehrere Commits aufteilen.

## Wenn diese Datei angepasst wird

- Neue Befehle zum Bauen, Testen oder Starten hier oder in der README festhalten, damit nachfolgende Agenten dieselben Schritte zuverlässig finden.
