/**
 * @file
 * Keyboard navigation and attribution copying for the media page.
 */

((Drupal, once) => {
  /**
   * Links the keyboard shortcuts lead to.
   *
   * @type {Object<string, string>}
   */
  const shortcuts = {
    Escape: '[data-media-page-close]',
    ArrowLeft: '[data-media-page-prev]',
    ArrowRight: '[data-media-page-next]',
  };

  Drupal.behaviors.elaintehtaatMediaPage = {
    attach(context) {
      once('media-page', '[data-media-page]', context).forEach((root) => {
        document.addEventListener('keydown', (event) => {
          if (
            event.defaultPrevented ||
            event.altKey ||
            event.ctrlKey ||
            event.metaKey
          ) {
            return;
          }
          const selector = shortcuts[event.key];
          if (!selector) {
            return;
          }
          const link = root.querySelector(selector);
          if (!link) {
            return;
          }
          // Escape also means "stop loading" in Firefox, which aborts the
          // navigation started below unless the key is handled here.
          event.preventDefault();
          window.location.assign(link.href);
        });

        root.querySelectorAll('[data-media-page-copy]').forEach((button) => {
          const label = button.querySelector('.media-page__copy-label');
          const original = label ? label.textContent : '';
          button.addEventListener('click', async () => {
            if (!navigator.clipboard) {
              return;
            }
            try {
              await navigator.clipboard.writeText(button.dataset.attribution);
            } catch (e) {
              return;
            }
            if (label) {
              label.textContent = button.dataset.copiedLabel;
              button.classList.add('is-copied');
              window.setTimeout(() => {
                label.textContent = original;
                button.classList.remove('is-copied');
              }, 1500);
            }
          });
        });
      });
    },
  };
})(Drupal, once);
