/* Report chart tooltips (hover and keyboard focus). External file so the Content-Security-Policy can forbid inline scripts. */
document.querySelectorAll('.chart-wrap').forEach((wrap) => {
  const tip = wrap.querySelector('.chart-tip');
  if (!tip) return;
  const show = (bar) => {
    const box = bar.querySelector('.hit').getBoundingClientRect();
    const host = wrap.getBoundingClientRect();
    tip.textContent = bar.dataset.tip;
    tip.hidden = false;
    tip.style.left = `${Math.min(Math.max(0, box.left - host.left + box.width / 2 - tip.offsetWidth / 2), host.width - tip.offsetWidth)}px`;
  };
  const hide = () => { tip.hidden = true; };
  wrap.querySelectorAll('.bar').forEach((bar) => {
    bar.addEventListener('mouseenter', () => show(bar));
    bar.addEventListener('focus', () => show(bar));
    bar.addEventListener('mouseleave', hide);
    bar.addEventListener('blur', hide);
  });
});
