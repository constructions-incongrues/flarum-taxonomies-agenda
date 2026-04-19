import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
import type { AgendaEvent } from '../models/AgendaEvent';
import { monthShort } from '../utils/dateLabels';

interface AgendaEventCardAttrs {
  event: AgendaEvent;
}

export default class AgendaEventCard extends Component<AgendaEventCardAttrs> {
  view(): Mithril.Children {
    const { event } = this.attrs;
    const a = event.attributes;

    return (
      <article className="AgendaEventCard">
        <div className="AgendaEventCard-date">
          <span className="AgendaEventCard-day">{a.date_display.jour || '—'}</span>
          <span className="AgendaEventCard-month">{monthShort(a.date_display.mois)}</span>
        </div>
        <div className="AgendaEventCard-body">
          <h3 className="AgendaEventCard-title">
            <a href={a.discussion_url}>{a.title}</a>
          </h3>
          {(a.ville || a.lieu) && (
            <div className="AgendaEventCard-tags">
              {a.ville && (
                <a
                  className="AgendaEventCard-tag AgendaEventCard-tag--ville"
                  href={m.buildPathname('/agenda', { ville: a.ville })}
                  onclick={(e: Event) => {
                    e.preventDefault();
                    m.route.set('/agenda', { ville: a.ville! });
                  }}
                >
                  {a.ville}
                </a>
              )}
              {a.lieu && (
                <span className="AgendaEventCard-tag">{a.lieu}</span>
              )}
            </div>
          )}
        </div>
      </article>
    );
  }
}
