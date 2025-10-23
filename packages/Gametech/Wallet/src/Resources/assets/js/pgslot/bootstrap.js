window._ = require('lodash');

/**
 * We'll load jQuery and the Bootstrap jQuery plugin which provides support
 * for JavaScript based Bootstrap features such as modals and tabs. This
 * code may be modified to fit the specific needs of your application.
 */

try {

    window.Popper = require('popper.js').default;
    window.$ = window.jQuery = require('jquery');
    require('bootstrap');
    // require('waypoints/lib/noframework.waypoints.js');
    // require('tippy.js/dist/tippy-bundle.umd.js');
    // require('clipboard');
    // require('event-source-polyfill');
    // window.Popper = require('popper.js').default;
    // window.$ = window.jQuery = require('jquery');
    // require('./runtime.1ba6bf05.js');
    // require('./0.e84cf97a.js');
    // require('./1.9a969cca.js');
    // require('./app.629ea432');

} catch (e) {}

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */


window.axios = require('axios');

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

