/* Invoice "Print / save as PDF" button. External file so the Content-Security-Policy can forbid inline scripts. */
document.querySelector('[data-print]')?.addEventListener('click', () => window.print());
