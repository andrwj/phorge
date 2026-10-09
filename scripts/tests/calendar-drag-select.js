/* Run with Node, or call testCalendarDragSelect(source) in a JavaScript VM. */
function testCalendarDragSelect(source) {
  var checks = 0;
  function check(value, message) {
    checks++;
    if (!value) { throw new Error(message); }
  }
  function setup(step, enabled, firstHour) {
    var listeners = [];
    var workflows = [];
    var wrapper = {style: {}};
    var elements = [];
    var behavior;
    var JX = {
      behavior: function(name, fn) { behavior = fn; },
      $: function() { return wrapper; },
      $N: function(tag, attrs, children) {
        var n = Object.assign({tag: tag, style: {}, children: children}, attrs);
        elements.push(n);
        if (tag === 'table') {
          children.forEach(function(row, i) { row.y = 100 + i * 60; });
          n.y = 100;
        }
        return n;
      },
      $V: function(e) { return {y: e.y}; },
      Vector: {
        getPos: function(n) { return {y: n.y || 0}; },
        getDim: function() { return {y: 60}; }
      },
      DOM: {
        listen: function(node, type, sigil, fn) {
          listeners.push({type: type, sigil: sigil, fn: fn});
        },
        setContent: function(n, content) { n.content = content; },
        appendContent: function() {},
        alterClass: function() {}
      },
      Stratcom: {listen: function(type, sigil, fn) {
        listeners.push({type: type, sigil: sigil, fn: fn});
      }},
      Workflow: function(uri, data) {
        this.start = function() { workflows.push({uri: uri, data: data}); };
      }
    };
    new Function('JX', source)(JX);
    var epoch = 1800000000;
    var hours = [];
    for (var hour = firstHour; hour < 24; hour++) {
      var slots = [];
      for (var m = 0; m <= 60; m += step) {
        slots.push({epoch: epoch + (hour - firstHour) * 3600 + m * 60,
          label: hour + ':' + m});
      }
      hours.push({hour: hour, displayTime: hour + ':00', slots: slots});
    }
    behavior({year: 2026, month: 10, day: 9, hours: hours,
      firstEventHour: firstHour, firstEventHourEpoch: epoch,
      todayEvents: [], allDayEvents: [], tableID: 'test',
      dragSelect: enabled, dragStepMinutes: step});
    function fire(type, sigil, y, opts) {
      var e = Object.assign({y: y, kill: function() {},
        isNormalMouseEvent: function() { return true; },
        isNormalClick: function() { return true; },
        getSpecialKey: function() { return 'esc'; },
        getNode: function() { return null; },
        getNodeData: function() { return {time: firstHour + ':00'}; }
      }, opts);
      listeners.filter(function(l) { return l.type === type && l.sigil === sigil; })
        .forEach(function(l) { l.fn(e); });
    }
    return {fire: fire, workflows: workflows, epoch: epoch, elements: elements};
  }
  var h = setup(5, true, 8);
  h.fire('mousedown', 'phui-calendar-day-event-cell', 116);
  h.fire('mousemove', null, 203);
  h.fire('mouseup', null, 203);
  check(h.workflows.length === 1, 'One popup per drag');
  check(h.workflows[0].data.start === h.epoch + 15 * 60, 'Start at 08:15');
  check(h.workflows[0].data.end === h.epoch + 105 * 60, 'End at 09:45');
  h.fire('click', 'phui-calendar-day-event-cell', 203);
  check(h.workflows.length === 1, 'Release click does not open a second popup');
  h.fire('mousedown', 'phui-calendar-day-event-cell', 203);
  h.fire('mouseup', null, 116);
  check(h.workflows[1].data.start === h.epoch + 15 * 60, 'Reverse drag start');
  check(h.workflows[1].data.end === h.epoch + 105 * 60, 'Reverse drag end');
  h.fire('mousedown', 'phui-calendar-day-event-cell', 116);
  h.fire('mouseup', null, 116);
  check(h.workflows[2].data.start_t === '8:00', 'Plain click retains hourly start');
  check(h.workflows[2].data.end_t === '8:00 +1 hour', 'Plain click retains duration');
  h.fire('mousedown', 'phui-calendar-day-event-cell', 110);
  h.fire('keydown', null, 0);
  h.fire('mouseup', null, 130);
  check(h.workflows.length === 3, 'Escape cancels selection');
  h.fire('mousedown', 'phui-calendar-day-event-cell', 110,
    {isNormalMouseEvent: function() { return false; }});
  h.fire('mouseup', null, 130);
  check(h.workflows.length === 3, 'Ignore non-primary mouse');
  h.fire('mousedown', 'phui-calendar-day-event-cell', 110,
    {getNode: function() { return {}; }});
  h.fire('mouseup', null, 130);
  check(h.workflows.length === 3, 'Existing event does not create an event');
  [1, 5, 15, 60].forEach(function(step) {
    var t = setup(step, true, 23);
    t.fire('mousedown', 'phui-calendar-day-event-cell', 159.5);
    t.fire('mouseup', null, 300);
    var data = t.workflows[0].data;
    check(data.end === t.epoch + 3600, 'End at next midnight, step ' + step);
    check(data.end - data.start === step * 60, 'Minimum interval ' + step);
    var cell = t.elements.filter(function(n) {
      return n.className === 'phui-calendar-day-events';
    })[0];
    check(cell.style.backgroundSize === '100% ' + (step / 60 * 100) + '%',
      'Grid interval ' + step);
  });
  var disabled = setup(5, false, 8);
  disabled.fire('mousedown', 'phui-calendar-day-event-cell', 110);
  disabled.fire('mouseup', null, 140);
  check(disabled.workflows.length === 0, 'Disabled drag leaves existing behavior');
  disabled.fire('click', 'phui-calendar-day-event-cell', 110);
  check(disabled.workflows.length === 1, 'Disabled feature preserves click popup');
  return checks;
}
if (typeof module !== 'undefined') {
  var fs = require('fs');
  console.log('PASS: ' + testCalendarDragSelect(fs.readFileSync(
    'webroot/rsrc/js/application/calendar/behavior-day-view.js', 'utf8')) + ' checks');
}
