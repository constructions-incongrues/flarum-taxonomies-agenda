import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
import type { AgendaEvent } from '../models/AgendaEvent';
import AgendaEventCard from './AgendaEventCard';
import { monthLabel } from '../utils/dateLabels';

interface AgendaTimelineAttrs {
  events: AgendaEvent[];
}

interface Group {
  key: string;
  label: string;
  items: AgendaEvent[];
}

function groupByMonth(events: AgendaEvent[]): Group[] {
  const groups = new Map<string, Group>();
  for (const ev of events) {
    const d = ev.attributes.date_display;
    const key = `${d.annee}-${d.mois}`;
    if (!groups.has(key)) {
      groups.set(key, { key, label: `${monthLabel(d.mois)} ${d.annee}`.trim(), items: [] });
    }
    groups.get(key)!.items.push(ev);
  }
  return Array.from(groups.values());
}

export default class AgendaTimeline extends Component<AgendaTimelineAttrs> {
  view(): Mithril.Children {
    const groups = groupByMonth(this.attrs.events);

    if (groups.length === 0) {
      return <div className="AgendaTimeline-empty">Aucun événement.</div>;
    }

    return (
      <div className="AgendaTimeline">
        {groups.map((g) => (
          <section key={g.key} className="AgendaTimeline-group">
            <h2 className="AgendaTimeline-month">{g.label}</h2>
            <div className="AgendaTimeline-items">
              {g.items.map((ev) => (
                <AgendaEventCard key={ev.id} event={ev} />
              ))}
            </div>
          </section>
        ))}
      </div>
    );
  }
}
