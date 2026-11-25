/**
 * SPK Health Poskestren Al-Bahjah Bogor
 * Frontend App — Fase 1
 */

// ─── DOM Ready ────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {

  // Smooth scroll for anchor links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  // Navbar scroll behavior (landing page)
  const navbar = document.querySelector('.navbar');
  if (navbar) {
    window.addEventListener('scroll', () => {
      if (window.scrollY > 20) {
        navbar.style.boxShadow = '0 4px 20px rgba(0,0,0,0.15)';
      } else {
        navbar.style.boxShadow = 'none';
      }
    });
  }

  // Auto-hide alerts after 5 seconds (if any)
  document.querySelectorAll('[role="alert"]:not(.hidden)').forEach(alert => {
    setTimeout(() => {
      if (!alert.classList.contains('hidden')) {
        alert.style.opacity = '0';
        alert.style.transition = 'opacity 0.4s';
        setTimeout(() => {
          alert.classList.add('hidden');
          alert.style.opacity = '';
        }, 400);
      }
    }, 5000);
  });

});
