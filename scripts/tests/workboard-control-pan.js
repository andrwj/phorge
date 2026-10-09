/* Run with: node scripts/tests/workboard-control-pan.js */
function testWorkboardControlPan(source) {
  var count = 0;
  function check(value, message) {
    count++;
    if (!value) {
      throw new Error(message);
    }
  }
  var listeners = {};
  var document = {
    addEventListener: function(type, handler) {
      listeners[type] = handler;
    }
  };
  var definition;
  var JX = {
    install: function(name, spec) { definition = spec; },
    Stratcom: {
      hasSigil: function(node, sigil) { return node.sigil === sigil; }
    }
  };
  new Function('JX', 'document', source)(JX, document);
  var enabled = true;
  var controller = Object.assign({}, definition.members, {
    getControlPanEnabled: function() { return enabled; }
  });
  controller._setupControlPanHandlers();
  var captured = null;
  var board = {
    nodeType: 1,
    sigil: 'workboard-shadow',
    parentNode: document,
    scrollLeft: 100,
    setPointerCapture: function(id) { captured = id; },
    hasPointerCapture: function(id) { return captured === id; },
    releasePointerCapture: function() { captured = null; }
  };
  var card = {nodeType: 1, parentNode: board};
  function fire(type, options) {
    var event = Object.assign({
      target: card, ctrlKey: true, button: 0, pointerType: 'mouse',
      pointerId: 7, clientX: 100, prevented: false, stopped: false,
      preventDefault: function() { this.prevented = true; },
      stopImmediatePropagation: function() { this.stopped = true; }
    }, options || {});
    listeners[type](event);
    return event;
  }
  check(fire('pointerdown').stopped, 'Ctrl drag must capture before card drag');
  check(captured === 7, 'Pointer must be captured');
  fire('pointermove', {clientX: 50, pointerId: 8});
  check(board.scrollLeft === 100, 'Other pointers must not move the board');
  fire('pointermove', {clientX: 60});
  check(board.scrollLeft === 140, 'Horizontal drag must scroll the board');
  fire('lostpointercapture', {pointerId: 8});
  fire('pointermove', {clientX: 50});
  check(board.scrollLeft === 150, 'Other capture events must not cancel drag');
  check(fire('contextmenu').prevented, 'Control context menu must be suppressed');
  fire('pointerup');
  check(captured === null, 'Pointer capture must be released');
  check(fire('click').prevented, 'A drag must not open a card or link');
  check(!fire('click', {ctrlKey: false}).prevented, 'Normal clicks must recover');
  fire('pointerdown', {button: 2});
  fire('pointermove', {clientX: 80});
  check(board.scrollLeft === 170, 'macOS secondary Control-click must pan');
  fire('pointercancel');
  fire('pointermove', {clientX: 0});
  check(board.scrollLeft === 170, 'Cancelled drags must stop scrolling');
  check(!fire('pointerdown', {ctrlKey: false}).prevented,
    'Normal card dragging must be left to existing handlers');
  check(!fire('pointerdown', {pointerType: 'touch'}).prevented,
    'Touch must retain its existing handlers');
  check(!fire('pointerdown', {target: document}).prevented,
    'Other page areas must retain their existing handlers');
  enabled = false;
  check(!fire('pointerdown').prevented, 'Disabled Control pan must not intercept');
  check(!fire('contextmenu').prevented, 'Disabled mode must allow context menus');
  return count;
}

if (typeof require !== 'undefined' && typeof module !== 'undefined' &&
    require.main === module) {
  var fs = require('fs');
  var path = require('path');
  var source = fs.readFileSync(path.resolve(__dirname,
    '../../webroot/rsrc/js/application/projects/WorkboardController.js'), 'utf8');
  console.log('PASS: ' + testWorkboardControlPan(source) + ' assertions');
}
