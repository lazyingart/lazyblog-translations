(function () {
  'use strict';

  var keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content'];
  var source = new URL(window.location.href);
  var attribution = {};

  keys.forEach(function (key) {
    var value = source.searchParams.get(key) || '';
    if (/^[A-Za-z0-9._/-]{1,80}$/.test(value)) {
      attribution[key] = value;
    }
  });

  if (!Object.keys(attribution).length) {
    return;
  }

  document.querySelectorAll('a[href]').forEach(function (link) {
    var target;
    try {
      target = new URL(link.href, source);
    } catch (_error) {
      return;
    }
    if (
      target.protocol !== 'https:' ||
      target.hostname !== 'lazying.art' ||
      !/^\/lkt\/(?:fit-check|sample-report)\/?$/.test(target.pathname)
    ) {
      return;
    }
    Object.keys(attribution).forEach(function (key) {
      target.searchParams.set(key, attribution[key]);
    });
    link.href = target.toString();
  });
})();
