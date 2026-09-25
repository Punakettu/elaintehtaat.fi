/**
 * @file
 * Opens and closes the primary menu panel on narrow screens.
 */

((Drupal, once) => {
  Drupal.behaviors.elaintehtaatMasthead = {
    attach(context) {
      once('masthead-toggle', '.masthead__toggle', context).forEach((toggle) => {
        const masthead = toggle.closest('.masthead');
        const nav = document.getElementById(toggle.getAttribute('aria-controls'));
        if (!masthead || !nav) {
          return;
        }

        const setOpen = (open) => {
          toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
          masthead.classList.toggle('is-open', open);
        };

        toggle.addEventListener('click', () => {
          setOpen(toggle.getAttribute('aria-expanded') !== 'true');
        });

        document.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && masthead.classList.contains('is-open')) {
            setOpen(false);
            toggle.focus();
          }
        });

        document.addEventListener('click', (event) => {
          if (!masthead.contains(event.target)) {
            setOpen(false);
          }
        });

        // Leaving the narrow layout closes the panel, so it is not open on
        // return.
        window.matchMedia('(min-width: 901px)').addEventListener('change', (event) => {
          if (event.matches) {
            setOpen(false);
          }
        });
      });
    },
  };
})(Drupal, once);
