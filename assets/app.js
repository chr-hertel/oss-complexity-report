/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.scss';

/*
 * Page transitions, previously swup. Turbo Drive comes from the same family as Stimulus, and importing
 * it is all there is to it: from here on every link and every form of the site is routed through it, so
 * there is nothing to wire up per page anymore. The fade is the browser's own view transition, enabled
 * by the meta tag in base.html.twig.
 */
import '@hotwired/turbo';

// start the Stimulus application
import './stimulus_bootstrap.js';
