// Main JavaScript for back-office
(function() {
  'use strict';

  // Sidebar toggle
  const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
  const sidebar = document.querySelector('.sidebar-nav-wrapper');
  const overlay = document.querySelector('.overlay');

  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('show');
    });
  }

  if (overlay) {
    overlay.addEventListener('click', () => {
      sidebar?.classList.remove('open');
      overlay.classList.remove('show');
    });
  }

  // Collapse/Expand navigation items
  const navItems = document.querySelectorAll('.nav-item-has-children > a');
  navItems.forEach(item => {
    item.addEventListener('click', (e) => {
      // Let the dedicated collapse handler manage these links.
      if (item.getAttribute('data-bs-toggle') === 'collapse') {
        return;
      }

      e.preventDefault();
      const dropdown = item.nextElementSibling;
      if (dropdown && dropdown.classList.contains('dropdown-nav')) {
        dropdown.classList.toggle('show');
      }
    });
  });

  // Initialize Bootstrap collapse elements
  const collapseElements = document.querySelectorAll('[data-bs-toggle="collapse"]');
  collapseElements.forEach(element => {
    element.addEventListener('click', (e) => {
      e.preventDefault();
      const target = document.querySelector(element.getAttribute('data-bs-target'));
      if (target) {
        target.classList.toggle('show');
      }
    });
  });

  // Auto-hide alerts after 5 seconds
  const alerts = document.querySelectorAll('.alert:not(.keep-open)');
  alerts.forEach(alert => {
    setTimeout(() => {
      alert.style.transition = 'opacity 0.3s ease';
      alert.style.opacity = '0';
      setTimeout(() => {
        alert.style.display = 'none';
      }, 300);
    }, 5000);
  });

  // Form validation
  const forms = document.querySelectorAll('form[data-validate]');
  forms.forEach(form => {
    form.addEventListener('submit', (e) => {
      if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
      }
      form.classList.add('was-validated');
    });
  });

  // Delete confirmation
  const deleteButtons = document.querySelectorAll('button[data-confirm-delete]');
  deleteButtons.forEach(button => {
    button.addEventListener('click', (e) => {
      const message = button.getAttribute('data-confirm-message') || 'Are you sure you want to delete this item?';
      if (!confirm(message)) {
        e.preventDefault();
      }
    });
  });

  // Page transitions
  const mainWrapper = document.querySelector('.main-wrapper');
  if (mainWrapper) {
    // Fade in on page load
    window.addEventListener('load', () => {
      mainWrapper.classList.add('visible');
    });

    // Fade out on navigation
    document.addEventListener('click', (e) => {
      const link = e.target.closest('a');
      if (link && !link.href.includes('#') && !link.target && link.href.startsWith(window.location.origin)) {
        // Exclude logout and delete links
        if (!link.classList.contains('no-fade')) {
          e.preventDefault();
          mainWrapper.classList.remove('visible');
          setTimeout(() => {
            window.location.href = link.href;
          }, 300);
        }
      }
    }, true);
  }

  // Dropdown menu
  let nbDdOpen = false;
  const nbUserPill = document.getElementById('nbUserPill');
  const nbDropdown = document.getElementById('nbDropdown');

  if (nbUserPill && nbDropdown) {
    window.nbToggleDropdown = function() {
      nbDdOpen = !nbDdOpen;
      nbDropdown.classList.toggle('open', nbDdOpen);
      nbUserPill.classList.toggle('open', nbDdOpen);
    };

    document.addEventListener('click', (e) => {
      if (nbUserPill && !nbUserPill.contains(e.target)) {
        nbDdOpen = false;
        nbDropdown.classList.remove('open');
        nbUserPill.classList.remove('open');
      }
    });
  }

  // Search focus
  const searchInput = document.querySelector('.nb-search input');
  if (searchInput) {
    searchInput.addEventListener('focus', () => {
      searchInput.parentElement.style.boxShadow = '0 0 0 3px rgba(255, 190, 51, 0.2)';
    });
    searchInput.addEventListener('blur', () => {
      searchInput.parentElement.style.boxShadow = 'none';
    });
  }

  // Table sorting
  const sortableHeaders = document.querySelectorAll('th[data-sortable]');
  sortableHeaders.forEach(header => {
    header.style.cursor = 'pointer';
    header.addEventListener('click', () => {
      const table = header.closest('table');
      const tbody = table.querySelector('tbody');
      const rows = Array.from(tbody.querySelectorAll('tr'));
      const index = Array.from(header.parentElement.children).indexOf(header);
      const isAsc = !header.classList.contains('asc');

      rows.sort((a, b) => {
        const aVal = a.children[index].textContent.trim();
        const bVal = b.children[index].textContent.trim();
        if (!isNaN(aVal) && !isNaN(bVal)) {
          return isAsc ? aVal - bVal : bVal - aVal;
        }
        return isAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
      });

      // Update sorting indicators
      document.querySelectorAll('th[data-sortable]').forEach(th => {
        th.classList.remove('asc', 'desc');
      });
      header.classList.toggle('asc', isAsc);
      header.classList.toggle('desc', !isAsc);

      // Re-append sorted rows
      rows.forEach(row => tbody.appendChild(row));
    });
  });

  // Tooltip initialization
  if (typeof bootstrap !== 'undefined') {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
  }

  console.log('⚡ BizHub Admin - Initialized');
})();
