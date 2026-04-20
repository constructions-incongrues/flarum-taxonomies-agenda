import type { AgendaEvent } from '../models/AgendaEvent';

/** RFC 5545 TEXT escaping */
function esc(s: string): string {
  return s
    .replace(/\\/g, '\\\\')
    .replace(/;/g, '\\;')
    .replace(/,/g, '\\,')
    .replace(/[\r\n]+/g, '\\n');
}

/** Returns YYYYMMDD for event_date + 1 day (Google Calendar end is exclusive) */
function endDateStr(dateStr: string): string {
  const d = new Date(dateStr + 'T12:00:00Z');
  d.setUTCDate(d.getUTCDate() + 1);
  return d.toISOString().slice(0, 10).replace(/-/g, '');
}

function locationStr(a: AgendaEvent['attributes']): string {
  return [a.lieu, a.ville].filter(Boolean).join(', ');
}

function descStr(a: AgendaEvent['attributes'], discussionUrl: string): string {
  const parts: string[] = [];
  if (a.artistes.length) parts.push(a.artistes.join(', '));
  const loc = locationStr(a);
  if (loc) parts.push(loc);
  if (a.excerpt) parts.push(a.excerpt);
  parts.push(discussionUrl);
  return parts.join('\n\n');
}

export function buildGoogleUrl(event: AgendaEvent): string {
  const a = event.attributes;
  const start = a.event_date.replace(/-/g, '');
  const params = new URLSearchParams({ action: 'TEMPLATE', text: a.title, dates: `${start}/${endDateStr(a.event_date)}` });
  const loc = locationStr(a);
  const desc = descStr(a, a.discussion_url);
  if (loc) params.set('location', loc);
  params.set('details', desc);
  return `https://calendar.google.com/calendar/render?${params}`;
}

export function buildOutlookUrl(event: AgendaEvent, service: 'live' | 'office'): string {
  const a = event.attributes;
  const base = service === 'live'
    ? 'https://outlook.live.com/calendar/0/deeplink/compose'
    : 'https://outlook.office.com/calendar/0/deeplink/compose';
  const params = new URLSearchParams({
    subject: a.title,
    startdt: a.event_date,
    enddt: a.event_date,
    allday: 'true',
    path: '/calendar/action/compose',
  });
  const loc = locationStr(a);
  const desc = descStr(a, a.discussion_url);
  if (loc) params.set('location', loc);
  params.set('body', desc);
  return `${base}?${params}`;
}

export function downloadIcs(event: AgendaEvent): void {
  const a = event.attributes;
  const date = a.event_date.replace(/-/g, '');
  const dtstamp = new Date().toISOString().replace(/[-:.]/g, '').slice(0, 15) + 'Z';
  const loc = locationStr(a);
  const desc = descStr(a, a.discussion_url);

  const lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//Musiques Incongrues//Agenda Timeline//FR',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    `UID:discussion-${event.id}@musiques-incongrues.net`,
    `DTSTAMP:${dtstamp}`,
    `DTSTART;VALUE=DATE:${date}`,
    `DTEND;VALUE=DATE:${date}`,
    `SUMMARY:${esc(a.title)}`,
    ...(loc ? [`LOCATION:${esc(loc)}`] : []),
    ...(desc ? [`DESCRIPTION:${esc(desc)}`] : []),
    `URL:${a.discussion_url}`,
    'END:VEVENT',
    'END:VCALENDAR',
  ];

  const blob = new Blob([lines.join('\r\n') + '\r\n'], { type: 'text/calendar;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const el = document.createElement('a');
  el.href = url;
  el.download = `agenda-${event.id}.ics`;
  document.body.appendChild(el);
  el.click();
  document.body.removeChild(el);
  URL.revokeObjectURL(url);
}
