document.addEventListener('DOMContentLoaded', () => {
  /* ═══════════ MOBILE MENU TOGGLE ═══════════ */
  const navToggle = document.getElementById('navToggle');
  const navLinks = document.getElementById('navLinks');
  const navLinksItems = navLinks.querySelectorAll('a');

  navToggle.addEventListener('click', () => {
    navLinks.classList.toggle('active');
    const icon = navToggle.querySelector('.material-symbols-outlined');
    if (navLinks.classList.contains('active')) {
      icon.textContent = 'close';
    } else {
      icon.textContent = 'menu';
    }
  });

  // Close mobile menu when clicking a link
  navLinksItems.forEach(link => {
    link.addEventListener('click', () => {
      navLinks.classList.remove('active');
      navToggle.querySelector('.material-symbols-outlined').textContent = 'menu';
    });
  });

  /* ═══════════ SMOOTH SCROLLING (Fallback if CSS smooth-scroll is not supported) ═══════════ */
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      const targetId = this.getAttribute('href');
      if (targetId === '#') return;
      
      const targetElement = document.querySelector(targetId);
      if (targetElement) {
        e.preventDefault();
        const headerOffset = 80;
        const elementPosition = targetElement.getBoundingClientRect().top;
        const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
  
        window.scrollTo({
          top: offsetPosition,
          behavior: 'smooth'
        });
      }
    });
  });


});
