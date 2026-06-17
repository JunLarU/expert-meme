export default function initNavbar() {
  const navbar = document.querySelector('.navbar');
  const btn = document.querySelector('.navbar__burger__btn-menu');
  const links = document.querySelector('.navbar__links');
  const body = document.body;
  const mqTablet = window.matchMedia('(min-width: 1024px)');

  if (!navbar) return;

  let ticking = false;
  let savedScrollY = 0;
  let locked = false;

  const lockScroll = () => {
    if (locked) return;
    savedScrollY = window.scrollY;
    body.style.top = `-${savedScrollY}px`;   // 👈 CLAVE para que no “suba”
    body.classList.add('scroll-lock');
    locked = true;
  };

  const unlockScroll = () => {
    if (!locked) return;
    body.classList.remove('scroll-lock');
    body.style.top = '';
    window.scrollTo(0, savedScrollY);        // 👈 vuelve al punto exacto
    locked = false;
  };

  // 1) Burger toggle (solo si clickeas el botón)
  document.addEventListener('click', (e) => {
    const clickedBtn = e.target.closest('.navbar__burger__btn-menu');
    if (!clickedBtn) return;

    btn?.classList.toggle('menu-open');
    links?.classList.toggle('active');
    navbar.classList.toggle('active');

    const menuOpen = links?.classList.contains('active');

    // Solo lock en mobile
    if (!mqTablet.matches && menuOpen) lockScroll();
    else unlockScroll();
  });

  // 2) Sticky simple por breakpoint (tablet+ 50vh, mobile 100vh)
  const thresholdPx = () => window.innerHeight * 0.25;

  const updateSticky = () => {
    ticking = false;

    // Si el menú está abierto en mobile, NO recalcules sticky (evita brincos)
    if (!mqTablet.matches && links?.classList.contains('active')) return;

    navbar.classList.toggle('sticky', window.scrollY > thresholdPx());
  };

  const onScroll = () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(updateSticky);
  };

  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', () => requestAnimationFrame(updateSticky), { passive: true });
  mqTablet.addEventListener?.('change', () => {
    // si cambias a tablet, asegúrate de desbloquear
    if (mqTablet.matches) unlockScroll();
    requestAnimationFrame(updateSticky);
  });

  updateSticky();
}
