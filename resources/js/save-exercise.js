/**
 * Save / unsave buttons for exercises.
 *
 *   <button data-save-exercise="{id}" data-saved="true|false" data-save-url="{route}">
 *       <span data-save-label>Save</span>   (optional text that flips to "Saved")
 *   </button>
 *   <span data-saves-count="{id}">3</span>   (optional live save counter)
 *
 * The button flips immediately, then the server is told the state we want (not
 * "toggle"), so double clicks and retries can't undo each other. If the request
 * fails the button flips back and the reason is shown.
 */

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export function toast(message, isError = false) {
    let el = document.getElementById('toast');
    if (!el) {
        el = document.createElement('div');
        el.id = 'toast';
        el.className = 'ds-toast';
        el.setAttribute('role', 'status');
        el.setAttribute('aria-live', 'polite');
        document.body.appendChild(el);
    }
    el.textContent = message;
    el.classList.toggle('is-error', isError);
    el.classList.add('is-visible');
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => el.classList.remove('is-visible'), 2600);
}

function render(button, saved) {
    button.dataset.saved = saved ? 'true' : 'false';
    button.classList.toggle('is-saved', saved);
    button.setAttribute('aria-pressed', saved ? 'true' : 'false');
    button.title = saved ? 'Remove from saved' : 'Save exercise';
    const label = button.querySelector('[data-save-label]');
    if (label) label.textContent = saved ? 'Saved' : 'Save';
}

async function save(button) {
    const id = button.dataset.saveExercise;
    const wanted = button.dataset.saved !== 'true';

    button.dataset.busy = 'true';
    render(button, wanted);

    try {
        const response = await fetch(button.dataset.saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ exercise_id: id, saved: wanted }),
        });
        const data = await response.json().catch(() => null);

        if (!response.ok || !data?.success) {
            throw new Error(data?.message
                ?? (response.status === 419 ? 'Your session expired. Refresh the page and try again.' : 'Could not save right now. Please try again.'));
        }

        render(button, data.isSaved);
        document.querySelectorAll(`[data-saves-count="${id}"]`).forEach(el => { el.textContent = data.savesCount; });
        toast(data.message);
    } catch (error) {
        render(button, !wanted);
        toast(error.message, true);
    } finally {
        delete button.dataset.busy;
    }
}

document.addEventListener('click', event => {
    const button = event.target.closest('[data-save-exercise]');
    if (!button || button.dataset.busy) return;
    event.preventDefault();
    save(button);
});
