document.addEventListener('DOMContentLoaded', () => {
    const button = document.querySelector('[data-instagram-caption-copy]');
    const caption = document.querySelector('[data-instagram-caption]');

    if (button === null || caption === null) {
        return;
    }

    const originalLabel = button.innerHTML;

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

    button.addEventListener('click', async () => {
        try {
            await copyToClipboard(caption.textContent);
            button.innerHTML = '<i class="fa fa-check"></i> Kopiert';
        } catch (error) {
            button.innerHTML = '<i class="fa fa-xmark"></i> Kopieren nicht möglich';
        }

        window.setTimeout(() => {
            button.innerHTML = originalLabel;
        }, 2000);
    });
});
