import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
import type { AgendaEvent } from '../models/AgendaEvent';
import { monthLabel } from '../utils/dateLabels';

interface AgendaEventModalAttrs {
  event: AgendaEvent;
  onhide?: () => void;
}

export default class AgendaEventModal extends Modal<AgendaEventModalAttrs> {
  className(): string {
    return 'AgendaEventModal Modal--large';
  }

  title(): Mithril.Children {
    return this.attrs.event.attributes.title;
  }

  content(): Mithril.Children {
    const a = this.attrs.event.attributes;
    const d = a.date_display;
    const dateStr = `${d.jour} ${monthLabel(d.mois)} ${d.annee}`;
    const location = [a.lieu, a.ville].filter(Boolean).join(', ');
    const t = (k: string) => app.translator.trans('taxonomies-agenda.forum.modal.' + k);

    return (
      <div className="Modal-body AgendaEventModal-body">
        <p className="AgendaEventModal-meta AgendaEventModal-date">
          <i className="fas fa-calendar-day" />
          {dateStr}
        </p>
        {location && (
          <p className="AgendaEventModal-meta AgendaEventModal-location">
            <i className="fas fa-map-marker-alt" />
            {location}
          </p>
        )}
        {a.artistes.length > 0 && (
          <p className="AgendaEventModal-meta AgendaEventModal-artists">
            <i className="fas fa-music" />
            {a.artistes.join(', ')}
          </p>
        )}
        {a.excerpt && (
          <div className="AgendaEventModal-excerpt">{a.excerpt}</div>
        )}
        <div className="AgendaEventModal-actions">
          <a
            className="Button Button--primary"
            href={a.discussion_url}
            onclick={() => app.modal.close()}
          >
            <i className="fas fa-comments" />
            {t('discuss_button')}
          </a>
        </div>
      </div>
    );
  }
}
