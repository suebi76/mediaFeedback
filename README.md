<div align="center">
  <h1>mediaFeedback</h1>
  <p><strong>Die hyper-minimalistische, baustein-basierte Feedback-Engine.</strong></p>
</div>

<br>

**mediaFeedback** ist eine datenschutzfreundliche, ablenkungsfreie Feedback-Plattform, entwickelt für Creator, Pädagogen und Teams, die absoluten Fokus schätzen. Aufgebaut auf einer modernen Baustein-Architektur (inspiriert von Notion), blendet sie den Lärm traditioneller Umfrage-Tools aus und liefert ein immersives Premium-Erlebnis.

## ✨ Kernfunktionen

- **Zen-Modus Interface:** Ein extrem sauberes, einspaltiges Layout, das 100% Fokus der Teilnehmer garantiert. Keine Sidebars, kein störendes Marketing, keine nervigen Fortschritts-Hürden – einfach nur der reine Inhalt.
- **Baustein-Architektur:** Alles ist ein Baustein. Von einfachen Single/Multiple-Choice Fragen über interaktive Sterne-Bewertungen bis hin zu Medien-Blöcken. Das System ist unendlich skalierbar: Die interne `ActivityRegistry` entdeckt und registriert neue Bausteine vollautomatisch.
- **Natives Audio- & Video-Recording:** Teilnehmer können Sprach- (Audio) und Videofeedbacks direkt nativ im Browser aufzeichnen, ganz ohne umständliche Drittanbieter-Plugins.
- **Fließendes Auto-Saving:** Der Editor und die öffentliche Feedback-Ansicht arbeiten komplett asynchron. Alle Änderungen und Antworten werden nahtlos im Hintergrund gespeichert, um den Workflow niemals zu unterbrechen.
- **Absoluter Datenschutz (100% DSGVO-konform):** mediaFeedback macht genau das, was du erwartest – und nicht mehr. Es speichert **null** IP-Adressen, **null** Browser- oder Gerätedaten (User-Agent) und hält alle Informationen lokal in einer eingebetteten SQLite-Datenbank. Deine Daten verlassen niemals deinen Server.
- **Echtzeit-Analyse-Dashboard:** Ein integriertes, ultra-schnelles Analytics-Dashboard liefert direkte, visuelle Erkenntnisse über Teilnehmerantworten und Medien-Uploads.

## 🛠️ Tech Stack & Architektur

- **Backend:** PHP 8+ (Streng typisiert, OOP)
- **Datenbank:** SQLite (Zero-Configuration, lokal eingebetteter Storage mit maximaler Performance)
- **Frontend:** Vanilla JS & reines CSS (Keine Node-Build-Schritte nötig, keine überladenen Frontend-Frameworks wie React/Vue)
- **Medien-Verarbeitung:** Nativer HTML5 `MediaRecorder` API-Standard.

## 🚀 Installation

1. **Repository klonen:**
   ```bash
   git clone https://github.com/suebi76/mediaFeedback.git
   cd mediaFeedback
   ```
2. **Datenbank initialisieren:**
   Stelle sicher, dass der Ordner `data/` durch deinen Webserver beschreibbar ist. Das System generiert die SQLite-Datenbank beim ersten Start vollautomatisch.
3. **Umgebung konfigurieren:**
   Kopiere die Datei `data/config.example.php` zu `data/config.php` und passe die Basis-URL sowie die Security-Salts an.
4. **Applikation starten:**
   Leite deinen Apache/Nginx-Server auf das Hauptverzeichnis weiter, oder starte den integrierten Server lokal:
   ```bash
   php -S localhost:8080
   ```

## 🔐 Privacy by Design

Wir sind der festen Überzeugung, dass echtes Feedback ehrlich und vor allem sicher sein muss. 
Da `mediaFeedback` über Audio und Video hochsensible biometrische Daten sammelt, verzichtet die Datenbankstruktur physisch vollständig auf die Erfassung von verdeckten Tracking-Metriken (wie IP-Adressen). Das System wurde von Grund auf so designt, dass es den strengen Vorgaben zur Datenminimierung ("Privacy-by-Design") der europäischen DSGVO/GDPR hundertprozentig entspricht.

## 🤝 Mitwirken

Dieses Projekt lebt von seiner modularen Erweiterbarkeit. Wenn du einen neuen Frage- oder Inhaltstyp hinzufügen möchtest, reicht es, eine neue `Activity`-Klasse im Ordner `app/Activities/` anzulegen, die von `ActivityBase` erbt. Das System liest die Struktur aus und integriert den Baustein ohne jede Datenbankmigration nahtlos in den Editor!

---
*Gebaut für Klarheit. Designt für Fokus.*
