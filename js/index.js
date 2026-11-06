window.PAGES = window.PAGES || {};
PAGES.index = {
  show: function () {
    document.querySelectorAll('[data-preset-price]').forEach(function (el) {
      var p = PRESETS.find(function (x) { return x.id === el.dataset.presetPrice; });
      if (p) el.textContent = 'от ' + fmt(buildTotal(p.items));
    });
    document.querySelectorAll('[data-preset-specs]').forEach(function (el) {
      var p = PRESETS.find(function (x) { return x.id === el.dataset.presetSpecs; });
      if (p) el.textContent = presetSpecs(p);
    });
  }
};
