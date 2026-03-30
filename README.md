<div align="center">
  <h1>mediaFeedback</h1>
  <p><strong>The Hyper-Minimalist, Block-Based Feedback Engine.</strong></p>
</div>

<br>

**mediaFeedback** is a privacy-first, zero-distraction feedback platform engineered for creators, educators, and teams who value absolute focus. Built with a modern block-based architecture (inspired by Notion), it strips away the noise of traditional survey tools to deliver a premium, fluid experience for both creators and participants.

## ✨ Key Features

- **Zen Mode Interface:** A meticulously crafted, single-column layout that guarantees 100% participant focus. No sidebars, no marketing fluff, no annoying progress walls—just pure content.
- **Block-Based Architecture:** Everything is a block. From standard Single/Multiple Choice questions to advanced Interactive Ratings and Media blocks. The core scales infinitely: the internal `ActivityRegistry` auto-discovers and registers new block modules without touching the core schema.
- **Native Inline Media Recording:** Participants can record Voice (Audio) and Video feedback directly within the browser natively, bypassing the need for clunky third-party plugins.
- **Fluid Auto-Saving:** The editor and the public interface operate entirely asynchronously. Changes and responses are saved seamlessly in the background to prevent workflow interruptions.
- **Absolute Privacy (DSGVO/GDPR Compliant):** mediaFeedback does exactly what you want it to—and nothing more. It logs **zero** IP addresses, **zero** user-agent tracking, and stores everything in a local embedded SQLite database. Your data never leaves your server.
- **Real-Time Analytics Dashboard:** An integrated, ultra-fast analytics engine providing instant visual insights into participant responses, answer distributions, and media uploads.

## 🛠️ Tech Stack & Architecture

- **Backend:** PHP 8+ (Strictly Typed, OOP)
- **Database:** SQLite (Zero-configuration, hyper-fast local embedded storage)
- **Frontend:** Vanilla JS & CSS (Zero modern build steps required, no heavy frontend frameworks like React/Vue slowing down the render pipeline)
- **Media Engine:** Native HTML5 `MediaRecorder` API standard.

## 🚀 Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/suebi76/mediaFeedback.git
   cd mediaFeedback
   ```
2. **Setup the Database:**
   Ensure the `data/` directory is writable by your web server. The system will automatically provision the SQLite database on first run.
3. **Configure Environment:**
   Copy the `data/config.example.php` to `data/config.php` and update the base URL and security salts.
4. **Serve Application:**
   Point your Apache/Nginx web server to the root directory, or run locally:
   ```bash
   php -S localhost:8080
   ```

## 🔐 Privacy by Design

We strongly believe that feedback should be honest and safe. 
Because `mediaFeedback` collects highly sensitive biometric data via Audio and Video recordings, the database schema physically lacks the columns to store tracking metrics (like IP-Addresses). It is built from the ground up to comply mathematically with the strict data minimization mandates of the EU DSGVO/GDPR.

## 🤝 Contributing

This project is built around modular extensions. If you want to add a new question type, simply create a new `Activity` class extending `ActivityBase` inside `app/Activities/`. The application will instantly inject it into the editor UI and the rendering pipeline without any schema migrations.

---
*Architected for clarity. Designed for focus.*
