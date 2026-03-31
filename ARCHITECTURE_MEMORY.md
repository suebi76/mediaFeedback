# 🧠 mediaFeedback v3: AI & Developer Memory

**WICHTIG FÜR ALLE ZUKÜNFTIGEN ENTWICKLER ODER KI-ASSISTENTEN:**
Lies diese Datei sorgfältig, bevor du Änderungen an `mediaFeedback v3` vornimmst. Sie enthält die fundamentalen Architektur-Regeln, DSGVO-Prinzipien und die "Zen"-Designphilosophie des Systems.

---

## 1. Die Kern-Philosophie
- **Hyper-Minimalismus ("Zen-Modus"):** Keine verschachtelten Menüs, keine überladenen Sidebars (seit Phase 21/22 entfernt!), keine störenden "Save"-Buttons. Die UI ist "Flat", zentriert (max-width: 840px) und fokussiert sich zu 100% auf den direkten Content.
- **Privacy-by-Design (DSGVO):** Das System speichert absichtlich **keine** IP-Adressen und **keine** Browser-Metadaten (User-Agents). Die gesamte Datenbank (`responses`, `answers`) sammelt strikt nur die notwendigen Antworten.
- **Asynchrone Speicherung:** Jede Änderung am Formular oder den Block-Einstellungen wird sofort über Vanilla-JS-Listener im Hintergrund autogespeichert (keine manuellen Speicher-Buttons).

## 2. Tech Stack & Projektstruktur
- **Backend:** Pures, strikt typisiertes PHP 8.2+. Keine riesigen Frameworks.
- **Datenbank:** Eine einzige SQLite Datei in `/data/mediafeedback-v2.sqlite`. Keine externen DB-Server.
- **Frontend:** Vanilla JavaScript und Vanilla CSS. **VERBOTEN:** Die Einführung von schweren Build-Pipelines (Node.js, Webpack, NPM, Tailwind) für reguläres CSS. Alles wird nativ ausgeliefert, um die höchste Performance zu garantieren.
- **Router:** `index.php` routet per `$_SERVER['SCRIPT_NAME']` (dynamische `V2_BASE_URL` - funktioniert nativ in Unterordnern ohne `config.php` Hacks!).

## 3. Die Baustein-Architektur (ActivitySystem)
Alles im Feedback-Modus ist ein "Block" (wie bei Notion).
- Wenn du einen neuen Fragetyp programmieren willst, modifiziere **niemals** den Core-Code hartkodiert.
- **Neuer Baustein:** Erstelle einfach eine neue Klasse im Ordner `app/Activities/` (z.B. `ConsentActivity.php`), die von `ActivityBase` erbt.
- Die `ActivityRegistry.php` durchsucht das Verzeichnis vollautomatisch beim Start und registriert den Block in der UI. Keine Datenbankmigrationen für neue Blöcke nötig!
- JSON-Daten: Block-Einstellungen (`settings_json`) und komplexe Arrays als Antworten (z.B. bei *MultipleChoice*) werden strikt als validiertes JSON (Tabelle `answers.value_json` oder `feedbacks.settings_json`) gespeichert. Das Auslesen erfolgt über den `ResultController.php`.

## 4. Native Media Aufzeichnung
- Teilnehmer können ohne Plugins mit dem HTML5 `MediaRecorder` API Audio und Video direkt im Browser aufnehmen.
- Dateien werden automatisch über AJAX als Blöcke hochgeladen und sicher in `/data/uploads/` (beschützt durch `.gitignore`) gespeichert. Der Verweis zur Tabelle lautet `media_path` in der `answers` Tabelle.

## 5. Offene Roadmap (Stand Letzter Checkpoint)
Das Projekt wartet auf die Abarbeitung von **Phase 24 (DSGVO & EU AI Act)**:
1. Programmierung eines nativen `ConsentActivity.php` Bausteins als Checkbox.
2. Einbau eines "Transparenz-Modus" für die spätere (potenzielle) KI-Integration.

---
*Dieser Memory-Block stellt sicher, dass jede zukünftige KI-Sitzung exakt weiß, wo wir aufgehört haben und welche hochspezialisierten "Vanilla" und "Privacy"-Regeln wir hier eingeführt haben.*
