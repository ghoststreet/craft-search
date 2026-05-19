(function () {
    'use strict';
    var ATTR_TARGET = 'data-craftsearch-target';
    var ATTR_CONTROL = 'data-craftsearch-control';
    var ATTR_ACTION = 'data-craftsearch-action';

    function selector(attr, name) {
        return '[' + attr + '="' + name + '"]';
    }

    window.SmartSearch.core.DOM = {
        targetAttr: ATTR_TARGET,
        controlAttr: ATTR_CONTROL,
        actionAttr: ATTR_ACTION,

        find: function (name, root) {
            return (root || document).querySelector(selector(ATTR_TARGET, name));
        },
        findAll: function (name, root) {
            return Array.prototype.slice.call(
                (root || document).querySelectorAll(selector(ATTR_TARGET, name))
            );
        },
        findControl: function (name, root) {
            return (root || document).querySelector(selector(ATTR_CONTROL, name));
        },
        findAction: function (name, root) {
            return (root || document).querySelector(selector(ATTR_ACTION, name));
        },
        findAllByAttr: function (attr, value, root) {
            return Array.prototype.slice.call(
                (root || document).querySelectorAll('[' + attr + '="' + value + '"]')
            );
        },
        delegate: function (actionName, eventType, handler, root) {
            (root || document).addEventListener(eventType, function (event) {
                var target = event.target.closest(selector(ATTR_ACTION, actionName));
                if (target) handler.call(target, event, target);
            });
        },
        ready: function (fn) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', fn);
            } else {
                fn();
            }
        }
    };
})();
