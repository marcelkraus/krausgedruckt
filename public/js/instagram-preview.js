document.addEventListener('DOMContentLoaded', () => {
    const triggers = document.querySelectorAll('[data-instagram-copy-trigger]');

    if (triggers.length === 0) {
        return;
    }

    // The asynchronous clipboard API only exists in a secure context, so a
    // hidden selection serves as the fallback over plain HTTP.
    const copyToClipboard = async (text) => {
        if (window.isSecureContext && navigator.clipboard) {
            await navigator.clipboard.writeText(text);

            return;
        }

        const helper = document.createElement('textarea');
        helper.value = text;
        helper.setAttribute('readonly', '');
        helper.style.position = 'fixed';
        helper.style.top = '-9999px';
        document.body.appendChild(helper);
        helper.select();

        const succeeded = document.execCommand('copy');
        document.body.removeChild(helper);

        if (succeeded === false) {
            throw new Error('The browser rejected the copy command.');
        }
    };

    triggers.forEach((trigger) => {
        const name = trigger.dataset.instagramCopyTrigger;
        const source = document.querySelector(`[data-instagram-copy-source="${name}"]`);

        if (source === null) {
            return;
        }

        const originalLabel = trigger.innerHTML;

        trigger.addEventListener('click', async () => {
            try {
                await copyToClipboard(source.textContent);
                trigger.innerHTML = '<i class="fa fa-check"></i> Kopiert';
            } catch (error) {
                trigger.innerHTML = '<i class="fa fa-xmark"></i> Kopieren nicht möglich';
            }

            window.setTimeout(() => {
                trigger.innerHTML = originalLabel;
            }, 2000);
        });
    });
});
