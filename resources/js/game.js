// Jeu du jour : une tentative par jeu et par jour, retenue sur l'appareil avec la série de chaque jeu.
// Les réponses viennent du serveur au moment de jouer : la page ne les contient pas.

const STORAGE_KEY = 'detour:jeux';
const LETTERS = ['A', 'B', 'C', 'D'];
const euros3 = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
const decimal1 = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 });
const integer = new Intl.NumberFormat('fr-FR');

const page = document.querySelector('[data-game]');
const today = page?.dataset.date;

function load() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}');
    } catch {
        return {};
    }
}

function save(game, result) {
    const all = load();
    all[today] = { ...(all[today] ?? {}), [game]: result };
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(all));
    } catch {
        // stockage indisponible (navigation privée) : on joue sans mémoire
    }
}

/** Jours d'affilée joués à ce jeu, jusqu'à aujourd'hui (ou hier si on n'a pas encore joué) */
function streak(game) {
    const all = load();
    const day = new Date(`${today}T12:00:00Z`);
    if (!all[today]?.[game]) day.setUTCDate(day.getUTCDate() - 1);
    let count = 0;
    while (all[day.toISOString().slice(0, 10)]?.[game]) {
        count += 1;
        day.setUTCDate(day.getUTCDate() - 1);
    }
    return count;
}

async function post(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message ?? 'Une erreur est survenue, réessayez.');
    return data;
}

function paragraph(text, className) {
    const p = document.createElement('p');
    if (className) p.className = className;
    p.textContent = text;
    return p;
}

function showStreaks() {
    for (const badge of page.querySelectorAll('[data-streak]')) {
        const count = streak(badge.dataset.streak);
        badge.hidden = count === 0;
        badge.textContent = `🔥 ${count} jour${count > 1 ? 's' : ''}`;
    }
}

/* ---------- Le juste prix ---------- */
const priceCard = page?.querySelector('[data-game-price]');

function renderPrice(result) {
    const box = priceCard.querySelector('[data-result]');
    const { grade } = result;
    const direction = grade.direction === 'exact' ? 'pile le bon prix !' : `${grade.direction}`;
    box.replaceChildren(
        paragraph(`${grade.emoji} ${grade.label}`, 'grade'),
        paragraph(`Écart de ${decimal1.format(grade.diffCents)} centime${grade.diffCents >= 2 ? 's' : ''} : ${direction}`),
        paragraph(`Moyenne du jour : ${euros3.format(result.answer)} €/L · votre prix : ${euros3.format(result.guess)} €/L`),
        paragraph(`Sur ${integer.format(result.count)} stations, de ${euros3.format(result.min)} à ${euros3.format(result.max)} €/L. Revenez demain !`, 'muted small'),
    );
    box.hidden = false;
    priceCard.querySelector('[data-form]').hidden = true;
}

function setupPrice() {
    const form = priceCard.querySelector('[data-form]');
    const range = form.querySelector('[data-range]');
    const output = form.querySelector('[data-output]');
    const show = () => {
        output.textContent = euros3.format(Number(range.value));
    };
    range.addEventListener('input', show);
    for (const button of form.querySelectorAll('[data-nudge]')) {
        button.addEventListener('click', () => {
            const value = Math.min(Number(range.max), Math.max(Number(range.min), Number(range.value) + Number(button.dataset.nudge)));
            range.value = value.toFixed(3);
            show();
        });
    }
    show();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = form.querySelector('button:not([type=button])');
        submit.disabled = true;
        try {
            const result = await post('/jeu/juste-prix', { guess: Number(range.value) });
            save('prix', result);
            renderPrice(result);
            showStreaks();
            showShare();
        } catch (error) {
            const box = priceCard.querySelector('[data-result]');
            box.replaceChildren(paragraph(error.message, 'error'));
            box.hidden = false;
            submit.disabled = false;
        }
    });
}

/* ---------- Quiz auto ---------- */
const quizCard = page?.querySelector('[data-game-quiz]');

function renderQuiz(result) {
    for (const button of quizCard.querySelectorAll('[data-choice]')) {
        const i = Number(button.dataset.choice);
        button.disabled = true;
        button.classList.toggle('right', i === result.answer);
        button.classList.toggle('wrong', i === result.choice && !result.correct);
        button.classList.toggle('dim', i !== result.answer && i !== result.choice);
    }
    const box = quizCard.querySelector('[data-result]');
    box.replaceChildren(
        paragraph(result.correct ? '✅ Bonne réponse !' : `❌ Raté : c'était la réponse ${LETTERS[result.answer]}.`, 'grade'),
        paragraph(result.explain),
    );
    box.hidden = false;
}

function setupQuiz() {
    for (const button of quizCard.querySelectorAll('[data-choice]')) {
        button.addEventListener('click', async () => {
            quizCard.querySelectorAll('[data-choice]').forEach((b) => { b.disabled = true; });
            try {
                const result = await post('/jeu/quiz', { choice: Number(button.dataset.choice) });
                save('quiz', result);
                renderQuiz(result);
                showStreaks();
                showShare();
            } catch (error) {
                quizCard.querySelectorAll('[data-choice]').forEach((b) => { b.disabled = false; });
                const box = quizCard.querySelector('[data-result]');
                box.replaceChildren(paragraph(error.message, 'error'));
                box.hidden = false;
            }
        });
    }
}

/* ---------- Partage ---------- */
function shareText() {
    const day = load()[today] ?? {};
    const date = today.split('-').reverse().slice(0, 2).join('/');
    const lines = [`Détour · jeu du ${date}`];
    if (day.prix) lines.push(`⛽ Juste prix : ${day.prix.grade.emoji} ${decimal1.format(day.prix.grade.diffCents)} ct d'écart`);
    if (day.quiz) lines.push(`🚗 Quiz auto : ${day.quiz.correct ? '✅' : '❌'}`);
    lines.push(`${location.origin}/jeu`);
    return lines.join('\n');
}

function showShare() {
    const box = page.querySelector('[data-share]');
    const day = load()[today] ?? {};
    if (!day.prix && !day.quiz) return;
    box.querySelector('[data-share-text]').textContent = shareText().split('\n').slice(1, -1).join(' · ');
    box.hidden = false;
}

function setupShare() {
    const button = page.querySelector('[data-share-button]');
    button.addEventListener('click', async () => {
        const text = shareText();
        try {
            if (navigator.share) {
                await navigator.share({ text });
                return;
            }
            await navigator.clipboard.writeText(text);
            button.textContent = 'Copié ✓';
        } catch {
            // partage annulé
        }
    });
}

if (page) {
    const day = load()[today] ?? {};
    setupPrice();
    setupQuiz();
    setupShare();
    if (day.prix) renderPrice(day.prix);
    if (day.quiz) renderQuiz(day.quiz);
    showStreaks();
    showShare();
}
