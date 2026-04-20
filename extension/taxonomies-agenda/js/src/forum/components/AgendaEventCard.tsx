import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Dropdown from 'flarum/common/components/Dropdown';
import type Mithril from 'mithril';
import type { AgendaEvent } from '../models/AgendaEvent';
import { monthShort } from '../utils/dateLabels';
import { buildGoogleUrl, buildOutlookUrl, downloadIcs } from '../utils/addToCalendar';
import AgendaEventModal from './AgendaEventModal';

interface AgendaEventCardAttrs {
  event: AgendaEvent;
  onDelete?: (id: string) => void;
}

export default class AgendaEventCard extends Component<AgendaEventCardAttrs> {
  private canEdit(): boolean {
    const user = app.session.user;
    if (!user) return false;
    const a = this.attrs.event.attributes;
    return user.isAdmin() || (a.user_id !== null && user.id() === String(a.user_id));
  }

  private doDelete() {
    const t = (k: string) => app.translator.trans('taxonomies-agenda.forum.card.' + k) as string;
    if (!window.confirm(t('delete_confirm'))) return;

    app.request({
      method: 'DELETE',
      url: app.forum.attribute('apiUrl') + '/discussions/' + this.attrs.event.id,
    }).then(
      () => this.attrs.onDelete?.(this.attrs.event.id),
      () => app.alerts.show({ type: 'error' }, t('delete_error'))
    );
  }

  view(): Mithril.Children {
    const { event } = this.attrs;
    const a = event.attributes;
    const t = (k: string) => app.translator.trans('taxonomies-agenda.forum.card.' + k);

    return (
      <article className="AgendaEventCard">
        <div className="AgendaEventCard-date">
          <span className="AgendaEventCard-day">{a.date_display.jour || '—'}</span>
          <span className="AgendaEventCard-month">{monthShort(a.date_display.mois)}</span>
        </div>
        <div className="AgendaEventCard-body">
          <h3 className="AgendaEventCard-title">
            <button
              className="AgendaEventCard-titleBtn"
              onclick={() => {
                window.history.pushState({}, '', '/agenda/' + event.id);
                app.modal.show(AgendaEventModal, {
                  event,
                  onhide: () => window.history.pushState({}, '', '/agenda'),
                });
              }}
            >
              {a.title}
            </button>
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

        {/* Add to calendar dropdown — visible to all users */}
        <div className="AgendaEventCard-calendar">
          <Dropdown
            buttonClassName="Button Button--icon Button--flat"
            icon="fas fa-calendar-plus"
            label=""
            accessibleToggleLabel={t('add_to_calendar') as string}
          >
            <a className="Dropdown-item" href={buildGoogleUrl(event)} target="_blank" rel="noopener noreferrer">
              <i className="fab fa-google" /> {t('google_calendar')}
            </a>
            <a className="Dropdown-item" href={buildOutlookUrl(event, 'live')} target="_blank" rel="noopener noreferrer">
              <i className="fas fa-envelope" /> {t('outlook_calendar')}
            </a>
            <a className="Dropdown-item" href={buildOutlookUrl(event, 'office')} target="_blank" rel="noopener noreferrer">
              <i className="fas fa-building" /> {t('office365_calendar')}
            </a>
            <Button icon="fas fa-download" onclick={() => downloadIcs(event)}>
              {t('ical_download')}
            </Button>
          </Dropdown>
        </div>

        {/* Edit / delete — visible only to author and admins */}
        {this.canEdit() && (
          <div className="AgendaEventCard-controls">
            <Dropdown
              buttonClassName="Button Button--icon Button--flat"
              icon="fas fa-ellipsis-h"
              label=""
              accessibleToggleLabel="Actions"
            >
              <Button
                icon="fas fa-pencil-alt"
                onclick={() => m.route.set('/agenda/:id/edit', { id: event.id })}
              >
                {t('edit_button')}
              </Button>
              <Button
                icon="fas fa-trash-alt"
                onclick={() => this.doDelete()}
              >
                {t('delete_button')}
              </Button>
            </Dropdown>
          </div>
        )}
      </article>
    );
  }
}
