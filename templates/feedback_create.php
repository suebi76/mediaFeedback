<div class="card p-8" style="max-width: 640px; margin: 3rem auto;">
    <div class="stack">
        <div style="align-self: flex-start;">
            <div class="badge badge-brand mb-2">Neues Feedback</div>
        </div>
        <h1 class="mb-2">Feedback anlegen</h1>
        <p class="text-muted mb-6">Du startest mit Titel und Layout. Inhalte, Fragen und Seiten baust du danach im Editor blockbasiert zusammen.</p>
        <form class="stack" action="<?= V2_BASE_URL ?>/feedback/create" method="post">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <div class="field mb-2">
                <label for="title">Titel des Feedbacks</label>
                <input id="title" type="text" name="title" required placeholder="z.B. Workshop Evaluation" class="w-full text-lg p-4">
            </div>
            <div class="field mb-6">
                <label for="layout">Ansichts-Layout</label>
                <select id="layout" name="layout" class="w-full p-4">
                    <option value="one-per-page">Eine Seite pro Schritt (Modern & Interaktiv)</option>
                    <option value="classic">Klassisch (Alle Fragen untereinander)</option>
                </select>
            </div>
            <div class="actions mt-4 border-t" style="border-top: 1px solid var(--border-light); padding-top: 1.5rem;">
                <button class="btn btn-primary" type="submit">Feedback anlegen</button>
                <a class="btn btn-secondary" href="<?= V2_BASE_URL ?>/dashboard">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
