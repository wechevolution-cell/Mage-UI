/* Atelier: cursor-follow category preview (desktop only, decorative). */
(() => {
  const peek = document.querySelector('.peek'); if (!peek || !matchMedia('(hover: hover)').matches) return;
  const img = peek.querySelector('img');
  document.addEventListener('mousemove', e => { peek.style.left = e.clientX + 'px'; peek.style.top = e.clientY + 'px'; });
  document.querySelectorAll('[data-peek]').forEach(a => {
    a.addEventListener('mouseenter', () => { img.src = a.dataset.peek; peek.classList.add('on'); });
    a.addEventListener('mouseleave', () => peek.classList.remove('on'));
  });
})();
