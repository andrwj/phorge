/**
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-uri
 *           javelin-vector
 *           javelin-workflow
 *           phuix-icon-view
 * @provides javelin-behavior-day-view
 */
JX.behavior('day-view', function(config) {

  function findTodayClusters() {
    var events = today_events.sort(function(x, y){
      return (x.eventStartEpoch - y.eventStartEpoch);
    });

    var clusters = [];

    for (var i=0; i < events.length; i++) {
      var today_event = events[i];

      var destination_cluster_index = null;
      var event_start = today_event.eventStartEpoch - (60);
      var event_end = today_event.eventEndEpoch + (60);

      for (var j=0; j < clusters.length; j++) {
        var cluster = clusters[j];

        for(var k=0; k < cluster.length; k++) {
          var clustered_event = cluster[k];
          var compare_event_start = clustered_event.eventStartEpoch;
          var compare_event_end = clustered_event.eventEndEpoch;

          if (event_start < compare_event_end &&
            event_end > compare_event_start) {
            destination_cluster_index = j;
            break;
          }
        }

        if (destination_cluster_index !== null) {
          break;
        }
      }

      if (destination_cluster_index !== null) {
        clusters[destination_cluster_index].push(today_event);
        destination_cluster_index = null;
      } else {
        var next_cluster = [];
        next_cluster.push(today_event);
        clusters.push(next_cluster);
      }
    }

    return clusters;
  }

  function updateEventsFromCluster(cluster) {
    var cluster_size = cluster.length;
    var n = 0;
    for(var i=0; i < cluster.length; i++) {
      var cluster_member = cluster[i];

      var event_id = cluster_member.eventID;
      var offset = ((n / cluster_size) * 100) + '%';
      var width = ((1 / cluster_size) * 100) + '%';

      for (var j=0; j < today_events.length; j++) {
        if (today_events[j].eventID == event_id) {

          today_events[j]['offset'] = offset;
          today_events[j]['width'] = width;
        }
      }
      n++;
    }

    return today_events;
  }

  function drawEvent(e) {
    var name = e['eventName'];
    var eventID = e['eventID'];
    var viewerIsInvited = e['viewerIsInvited'];
    var offset = e['offset'];
    var width = e['width'];
    var top = e['top'];
    var height = e['height'];
    var uri = e['uri'];

    var sigil = 'phui-calendar-day-event';
    var link_class = 'phui-calendar-day-event-link';

    if (viewerIsInvited) {
      link_class = link_class + ' viewer-invited-day-event';
    }

    var icon = new JX.PHUIXIconView()
      .setIcon(e.displayIcon)
      .setColor(e.displayIconColor)
      .getNode();

    var content = [icon, ' ', name];

    var name_link = JX.$N(
      'a',
      {
        className : link_class,
        href: uri
      },
      content);

    var class_name = 'phui-calendar-day-event';
    if (e.canEdit) {
      class_name = class_name + ' can-drag';
    }

    var div = JX.$N(
      'div',
      {
        className: class_name,
        sigil: sigil,
        meta: {eventID: eventID, record: e, uri: uri},
        style: {
          left: offset,
          width: width,
          top: top,
          height: height
        }
      },
      name_link);

    return div;
  }

  function drawAllDayEvent(e) {
    var class_name = 'day-view-all-day';

    var icon = new JX.PHUIXIconView()
      .setIcon(e.displayIcon)
      .setColor(e.displayIconColor)
      .getNode();
    var content = [icon, ' ', e.name];

    var name = JX.$N(
      'a',
      {
        className: class_name,
        href: e.uri
      },
      content);

    var all_day_label = JX.$N(
      'span',
      {className: 'phui-calendar-all-day-label'},
      'All Day');

    var div_all_day = JX.$N(
      'div',
      {className: 'phui-calendar-day-event all-day'},
      [all_day_label, name]);

    return div_all_day;
  }

  function drawRows() {
    var rows = [];
    var early_hours = [8];
    if (first_event_hour) {
      early_hours.push(first_event_hour);
    }
    var min_early_hour = Math.min.apply(Math, early_hours);


    for(var i=0; i < hours.length; i++) {
      if (hours[i]['hour'] < min_early_hour) {
        continue;
      }
      visible_hours.push(hours[i]);
      var cell_time = JX.$N(
        'td',
        {className: 'phui-calendar-day-hour'},
        hours[i].displayTime);

      var cell_event = JX.$N(
        'td',
        {
          meta: {
            time: hours[i].displayTime
          },
          className: 'phui-calendar-day-events',
          style: config.dragSelect ? {
            backgroundImage: 'linear-gradient(to bottom, transparent 0%, ' +
              'transparent 95%, rgba(55,55,55,.15) 95%)',
            backgroundSize: '100% ' + (config.dragStepMinutes / 60 * 100) + '%'
          } : {},
          sigil: 'phui-calendar-day-event-cell'
        });

      var row = JX.$N(
        'tr',
        {},
        [cell_time, cell_event]);
      rows.push(row);
    }
    return rows;
  }

  function clusterAndDrawEvents() {
    var today_clusters = findTodayClusters();
    for(var i=0; i < today_clusters.length; i++) {
      today_events = updateEventsFromCluster(today_clusters[i]);
    }
    var drawn_hourly_events = [];
    for (i=0; i < today_events.length; i++) {
      drawn_hourly_events.push(drawEvent(today_events[i]));
    }

    JX.DOM.setContent(hourly_events_wrapper, drawn_hourly_events);

  }

  var year = config.year;
  var month = config.month;
  var day = config.day;
  var query = config.query;

  var hours = config.hours;
  var first_event_hour = config.firstEventHour;
  var first_event_hour_epoch = parseInt(config.firstEventHourEpoch, 10);
  var today_events = config.todayEvents;
  var today_all_day_events = config.allDayEvents;
  var table_wrapper = JX.$(config.tableID);
  var visible_hours = [];
  var rows = drawRows();

  var all_day_events = [];
  for(i=0; i < today_all_day_events.length; i++) {
    var all_day_event = today_all_day_events[i];
    all_day_events.push(drawAllDayEvent(all_day_event));
  }

  var table = JX.$N(
    'table',
    {className: 'phui-calendar-day-view'},
    rows);

  var dragging = false;
  var origin = null;

  var offset_top = null;
  var new_top = null;

  var click_time = null;

  JX.DOM.listen(
    table_wrapper,
    'mousedown',
    'phui-calendar-day-event',
    function(e){

    if (!e.isNormalMouseEvent()) {
      return;
    }
    var data = e.getNodeData('phui-calendar-day-event');
    if (!data.record.canEdit) {
      return;
    }
    e.kill();
    dragging = e.getNode('phui-calendar-day-event');
    JX.DOM.alterClass(dragging, 'phui-drag', true);

    click_time = new Date();

    origin = JX.$V(e);

    var outer = JX.Vector.getPos(table);
    var inner = JX.Vector.getPos(dragging);

    offset_top = inner.y - outer.y;
    new_top = offset_top;

    dragging.style.top = offset_top + 'px';
  });
  JX.Stratcom.listen('mousemove', null, function(e){
    if (!dragging) {
      return;
    }
    var cursor = JX.$V(e);

    new_top = cursor.y - origin.y + offset_top;
    new_top = Math.min(new_top, 1320);
    new_top = Math.max(new_top, 0);
    new_top = Math.floor(new_top/config.dragStepMinutes) * config.dragStepMinutes;

    dragging.style.top = new_top + 'px';
  });
  JX.Stratcom.listen('mouseup', null, function(){
    if (!dragging) {
      return;
    }

    var data = JX.Stratcom.getData(dragging);
    var record = data.record;

    if (new_top == offset_top) {
      var now = new Date();
      if (now.getTime() - click_time.getTime() < 250) {
        JX.$U(record.uri).go();
      }

      JX.DOM.alterClass(dragging, 'phui-drag', false);
      dragging = false;
      return;
    }
    var new_time = first_event_hour_epoch + (new_top * 60);
    var id = data.eventID;
    var duration = record.eventEndEpoch - record.eventStartEpoch;
    record.eventStartEpoch = new_time;
    record.eventEndEpoch = new_time + duration;
    record.top = new_top + 'px';

    new JX.Workflow(
      '/calendar/event/drag/' + id + '/',
      {start: new_time})
      .start();

    JX.DOM.alterClass(dragging, 'phui-drag', false);
    dragging = false;

    clusterAndDrawEvents();
  });

  JX.DOM.listen(table_wrapper, 'click', 'phui-calendar-day-event', function(e){
    if (e.isNormalClick()) {
      e.kill();
    }
  });

  JX.DOM.listen(table, 'click', 'phui-calendar-day-event-cell', function(e){
    if (!e.isNormalClick()) {
      return;
    }
    if (config.dragSelect) {
      e.kill();
      return;
    }
    var data = e.getNodeData('phui-calendar-day-event-cell');
    var time = data.time;

    new JX.Workflow(
      '/calendar/event/edit/',
      {
        start_d: year + '-' + month + '-' + day,
        start_t: time,
        end_d: year + '-' + month + '-' + day,
        end_t: time + ' +1 hour'
      })
      .start();
  });

  var hourly_events_wrapper = JX.$N(
    'div',
    {style: {
      position: 'absolute',
      left: '69px',
      right: 0
    }});

  clusterAndDrawEvents();

  var daily_wrapper = JX.$N(
    'div',
    {style: {position: 'relative'}},
    [hourly_events_wrapper, table]);


  if (config.dragSelect) {
    var selection = null;
    var selection_label = JX.$N('span', {
      className: 'phui-calendar-drag-selection-label'
    });
    var highlight = JX.$N('div', {
      className: 'phui-calendar-drag-selection',
      style: {display: 'none'}
    }, selection_label);
    var slots_per_hour = 60 / config.dragStepMinutes;

    function slotAt(e) {
      var y = JX.$V(e).y;
      for (var index = 0; index < rows.length; index++) {
        var top = JX.Vector.getPos(rows[index]).y;
        var height = JX.Vector.getDim(rows[index]).y;
        if (y < top + height) {
          var fraction = Math.max(0, (y - top) / height);
          return index * slots_per_hour + Math.min(slots_per_hour - 1,
            Math.floor(fraction * slots_per_hour));
        }
      }
      return rows.length * slots_per_hour - 1;
    }

    function pointAt(slot) {
      var row = Math.min(rows.length - 1,
        Math.floor(slot / slots_per_hour));
      var part = slot - row * slots_per_hour;
      var point = visible_hours[row].slots[part];
      return {
        epoch: point.epoch,
        label: point.label,
        top: JX.Vector.getPos(rows[row]).y - JX.Vector.getPos(table).y +
          JX.Vector.getDim(rows[row]).y * part / slots_per_hour
      };
    }

    function updateSelection(e) {
      var last = slotAt(e);
      if (Math.abs(JX.$V(e).y - selection.originY) >= 3) {
        selection.moved = true;
      }
      selection.start = Math.min(selection.anchor, last);
      selection.end = Math.max(selection.anchor, last) + 1;
      var start = pointAt(selection.start);
      var end = pointAt(selection.end);
      highlight.style.top = start.top + 'px';
      highlight.style.height = (end.top - start.top) + 'px';
      highlight.style.display = 'block';
      JX.DOM.setContent(selection_label, start.label + ' – ' + end.label);
    }

    function cancelSelection() {
      selection = null;
      highlight.style.display = 'none';
    }

    JX.DOM.listen(table, 'mousedown', 'phui-calendar-day-event-cell',
      function(e) {
        if (!e.isNormalMouseEvent() || dragging ||
            e.getNode('phui-calendar-day-event')) {
          return;
        }
        e.kill();
        selection = {anchor: slotAt(e), originY: JX.$V(e).y, moved: false};
        updateSelection(e);
      });
    JX.Stratcom.listen('mousemove', null, function(e) {
      if (selection) {
        e.kill();
        updateSelection(e);
      }
    });
    JX.Stratcom.listen('mouseup', null, function(e) {
      if (!selection) {
        return;
      }
      e.kill();
      updateSelection(e);
      var start = pointAt(selection.start);
      var end = pointAt(selection.end);
      if (!selection.moved) {
        var time = visible_hours[
          Math.floor(selection.anchor / slots_per_hour)].displayTime;
        cancelSelection();
        new JX.Workflow('/calendar/event/edit/', {
          start_d: year + '-' + month + '-' + day,
          start_t: time,
          end_d: year + '-' + month + '-' + day,
          end_t: time + ' +1 hour'
        }).start();
        return;
      }
      cancelSelection();
      new JX.Workflow('/calendar/event/edit/', {
        start: start.epoch,
        end: end.epoch
      }).start();
    });
    JX.Stratcom.listen('keydown', null, function(e) {
      if (selection && e.getSpecialKey() === 'esc') {
        e.kill();
        cancelSelection();
      }
    });
    JX.DOM.appendContent(daily_wrapper, highlight);
  }

  JX.DOM.setContent(table_wrapper, [all_day_events, daily_wrapper]);

});
