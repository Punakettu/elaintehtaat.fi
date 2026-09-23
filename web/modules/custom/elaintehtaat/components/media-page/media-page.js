/**
 * @file
 * Keyboard and swipe navigation and attribution copying for the media page.
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

  /**
   * Session storage key telling the next media page which way it was swiped.
   *
   * @type {string}
   */
  const swipeKey = 'elaintehtaat.mediaPageSwipe';

  /**
   * Share of the stage width a swipe must travel to change the image.
   *
   * @type {number}
   */
  const swipeThreshold = 0.2;

  /**
   * Speed in px/ms that changes the image even when the swipe is short.
   *
   * @type {number}
   */
  const flickVelocity = 0.5;

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  /**
   * Remembers the swipe direction across the page load.
   *
   * @param {string|null} direction
   *   'next', 'prev', or null to read and clear the stored value.
   *
   * @return {string|null}
   *   The stored direction when reading.
   */
  const swipeDirection = (direction = null) => {
    try {
      if (direction) {
        window.sessionStorage.setItem(swipeKey, direction);
        return direction;
      }
      const stored = window.sessionStorage.getItem(swipeKey);
      window.sessionStorage.removeItem(swipeKey);
      return stored;
    } catch (e) {
      return null;
    }
  };

  /**
   * Moves between images by swiping the stage on touch screens.
   *
   * The frame follows the finger, flies off when released past the threshold
   * and the next page slides its image in from the opposite side. Without a
   * link in the swiped direction the frame resists and springs back.
   *
   * @param {HTMLElement} root
   *   The media page element.
   */
  const attachSwipe = (root) => {
    const stage = root.querySelector('.media-page__stage');
    const frame = root.querySelector('.media-page__frame');
    if (!stage || !frame) {
      return;
    }

    const entered = swipeDirection();
    if (entered && !reducedMotion.matches) {
      frame.classList.add(`is-entering-${entered}`);
      frame.addEventListener(
        'animationend',
        () => frame.classList.remove(`is-entering-${entered}`),
        { once: true },
      );
    }

    let gesture = null;
    let leaving = false;

    const reset = () => {
      frame.classList.remove('is-dragging');
      frame.style.transform = '';
      frame.style.opacity = '';
    };

    // Returning with the back button restores the page as it was left, with
    // the frame swiped off the screen.
    window.addEventListener('pageshow', (event) => {
      if (event.persisted) {
        leaving = false;
        reset();
      }
    });

    stage.addEventListener('pointerdown', (event) => {
      if (
        leaving ||
        gesture ||
        event.pointerType !== 'touch' ||
        !event.isPrimary ||
        event.target.closest('a, button') ||
        (window.visualViewport && window.visualViewport.scale > 1)
      ) {
        return;
      }
      gesture = {
        id: event.pointerId,
        x: event.clientX,
        time: event.timeStamp,
        dx: 0,
      };
      frame.classList.add('is-dragging');
    });

    stage.addEventListener('pointermove', (event) => {
      if (!gesture || event.pointerId !== gesture.id) {
        return;
      }
      gesture.dx = event.clientX - gesture.x;
      const link = root.querySelector(
        gesture.dx < 0 ? '[data-media-page-next]' : '[data-media-page-prev]',
      );
      // Rubber band when there is nothing to move to.
      const offset = link ? gesture.dx : gesture.dx / 4;
      const progress = Math.min(Math.abs(offset) / stage.clientWidth, 1);
      frame.style.transform = `translateX(${offset}px) rotate(${offset / 60}deg)`;
      frame.style.opacity = String(1 - progress * 0.5);
    });

    const end = (event) => {
      if (!gesture || event.pointerId !== gesture.id) {
        return;
      }
      const { dx, time } = gesture;
      gesture = null;
      frame.classList.remove('is-dragging');

      const direction = dx < 0 ? 'next' : 'prev';
      const link = root.querySelector(`[data-media-page-${direction}]`);
      const velocity = Math.abs(dx) / Math.max(event.timeStamp - time, 1);
      const swiped =
        event.type === 'pointerup' &&
        link &&
        (Math.abs(dx) > stage.clientWidth * swipeThreshold ||
          (Math.abs(dx) > 30 && velocity > flickVelocity));

      if (!swiped) {
        reset();
        return;
      }

      leaving = true;
      swipeDirection(direction);
      if (reducedMotion.matches) {
        window.location.assign(link.href);
        return;
      }
      const sign = dx < 0 ? -1 : 1;
      frame.style.transform = `translateX(${sign * stage.clientWidth}px) rotate(${sign * 8}deg)`;
      frame.style.opacity = '0';
      // Navigate without waiting for the fly-out to finish, the next page
      // takes a moment to arrive anyway.
      window.setTimeout(() => window.location.assign(link.href), 120);
    };

    stage.addEventListener('pointerup', end);
    stage.addEventListener('pointercancel', end);
  };

  Drupal.behaviors.elaintehtaatMediaPage = {
    attach(context) {
      once('media-page', '[data-media-page]', context).forEach((root) => {
        attachSwipe(root);

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
