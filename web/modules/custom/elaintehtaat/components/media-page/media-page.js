/**
 * @file
 * Keyboard navigation and attribution copying for the media page.
 */

((Drupal, once) => {
  /**
   * Follows the link matched by a selector inside the page, if present.
   *
   * @param {HTMLElement} root
   *   The media page element.
   * @param {string} selector
   *   Selector of the link to follow.
   */
  const follow = (root, selector) => {
    const link = root.querySelector(selector);
    if (link) {
      window.location.assign(link.href);
    }
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
          if (event.key === 'Escape') {
            follow(root, '[data-media-page-close]');
          } else if (event.key === 'ArrowLeft') {
            follow(root, '[data-media-page-prev]');
          } else if (event.key === 'ArrowRight') {
            follow(root, '[data-media-page-next]');
          }
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
