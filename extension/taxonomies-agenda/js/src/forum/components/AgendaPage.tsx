import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';

import type { AgendaEvent } from '../models/AgendaEvent';
import type { AgendaFacetsAttributes } from '../models/AgendaFacets';
import { fetchEvents, fetchFacets, type AgendaFiltersState } from '../utils/agendaApi';
import AgendaFilters from './AgendaFilters';
import AgendaTimeline from './AgendaTimeline';
import EventComposer from './EventComposer';
import AgendaEventModal from './AgendaEventModal';

export default class AgendaPage extends Page {
  loading = true;
  events: AgendaEvent[] = [];
  facets: AgendaFacetsAttributes = { villes: [], lieux: [], artistes: [] };
  error: string | null = null;

  oninit(vnode: any) {
    super.oninit(vnode);
    app.history.push('agenda');
    this.load().then(() => {
      const path = m.route.get().split('?')[0].replace(/\/$/, '');
      const editMatch = path.match(/\/agenda\/(\d+)\/edit$/);
      const showMatch = path.match(/^\/agenda\/(\d+)$/);
      if (editMatch) {
        this.onEditEvent(editMatch[1]);
      } else if (showMatch) {
        this.onShowEvent(showMatch[1]);
      } else if (path.endsWith('/agenda/new')) {
        const p = (m.route.param() || {}) as Record<string, string | undefined>;
        this.onPostEvent({
          title: p.title,
          eventDate: p.date,
          eventVille: p.ville,
          eventLieu: p.lieu,
          eventPersonne: p.personne,
        });
      }
    });
  }

  onEditEvent = async (id: string) => {
    let result: any;
    try {
      result = await app.request<any>({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/agenda/events/' + id,
      });
    } catch {
      app.alerts.show({ type: 'error' }, "Impossible de charger l'événement.");
      return;
    }

    const attrs = result?.data?.attributes;
    if (!attrs) return;

    const tag = app.store.getBy('tags', 'slug', 'agenda');
    app.composer.load(EventComposer, {
      className: 'EventComposer',
      discussion: app.store.createRecord('discussions'),
      tags: tag ? [tag] : [],
      user: app.session.user,
      editingId: parseInt(id, 10),
      editingTitle: attrs.title,
      prefillDate: attrs.event_date,
      prefillVille: attrs.ville || '',
      prefillLieu: attrs.lieu || '',
      prefillPersonne: (attrs.artistes || [])[0] || '',
      submitLabel: app.translator.trans('taxonomies-agenda.forum.composer.edit_submit'),
    });
    app.composer.show();
  };

  currentFilters(): AgendaFiltersState {
    const p = (m.route.param() || {}) as Record<string, string | undefined>;
    const f: AgendaFiltersState = {};

    // Default window: today (local tz) → +1 year
    const today = new Date();
    const nextYear = new Date(today);
    nextYear.setFullYear(nextYear.getFullYear() + 1);
    const fmt = (d: Date) =>
      `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

    f.from = p.from || fmt(today);
    f.to   = p.to   || fmt(nextYear);
    if (p.ville) f.ville = p.ville;
    return f;
  }

  async load() {
    this.loading = true;
    this.error = null;
    m.redraw();
    try {
      const tagsPromise = (app.store.all('tags') || []).length === 0
        ? app.store.find('tags')
        : Promise.resolve(null);
      const [evResp, fcResp] = await Promise.all([
        fetchEvents(this.currentFilters()),
        fetchFacets(),
        tagsPromise,
      ]);
      this.events = evResp.data || [];
      this.facets = fcResp.data.attributes;
    } catch (e: any) {
      this.error = 'Impossible de charger l’agenda.';
      // eslint-disable-next-line no-console
      console.error(e);
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  onShowEvent = async (id: string) => {
    // Try to find the event in the already-loaded list first
    const found = this.events.find(e => e.id === id);
    if (found) {
      app.modal.show(AgendaEventModal, {
        event: found,
        onhide: () => m.route.set('/agenda'),
      });
      return;
    }

    // Not in the current window (e.g. past event via direct URL) — fetch from API
    try {
      const result = await app.request<any>({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/agenda/events/' + id,
      });
      const attrs = result?.data?.attributes;
      if (!attrs) return;
      const event: AgendaEvent = { type: 'agenda-events', id, attributes: attrs };
      app.modal.show(AgendaEventModal, {
        event,
        onhide: () => m.route.set('/agenda'),
      });
    } catch {
      app.alerts.show({ type: 'error' }, "Impossible de charger l'événement.");
    }
  };

  onDeleteEvent = (id: string) => {
    this.events = this.events.filter(e => e.id !== id);
    m.redraw();
  };

  onFilterChange = (next: AgendaFiltersState) => {
    m.route.set('/agenda', next);
    // Mithril will re-run oninit on this same component; trigger reload.
    setTimeout(() => this.load(), 0);
  };

  onPostEvent = (prefill: Record<string, string | undefined> = {}) => {
    const tag = app.store.getBy('tags', 'slug', 'agenda');

    if (!tag) {
      app.alerts.show({ type: 'error' }, "Tag 'agenda' introuvable. Rechargez la page.");
      return;
    }

    app.composer.load(EventComposer, {
      className: 'EventComposer',
      discussion: app.store.createRecord('discussions'),
      tags: tag ? [tag] : [],
      user: app.session.user,
      placeholder: app.translator.trans('taxonomies-agenda.forum.composer.description_placeholder'),
      submitLabel: app.translator.trans('taxonomies-agenda.forum.composer.submit_label'),
      prefillTitle: prefill.title,
      prefillDate: prefill.eventDate,
      prefillVille: prefill.eventVille,
      prefillLieu: prefill.eventLieu,
      prefillPersonne: prefill.eventPersonne,
    });

    app.composer.show();
  };

  view(): any {
    const tag = app.store.getBy('tags', 'slug', 'agenda');
    const canPost = tag ? tag.canStartDiscussion() : app.forum.attribute('canStartDiscussion');

    return (
      <div className="AgendaPage">
        <div className="container">
          <div className="AgendaPage-header flex-header">
            <h1 className="AgendaPage-title">Agenda</h1>
            <div className="AgendaPage-actions">
              <a
                className="Button Button--icon Button--flat AgendaPage-ical"
                href={app.forum.attribute('apiUrl') + '/agenda/ical'}
                title={app.translator.trans('taxonomies-agenda.forum.agenda.ical_subscribe') as string}
                target="_blank"
              >
                <i className="fas fa-calendar-plus" />
              </a>
              {canPost && (
                <Button
                  className="Button Button--primary"
                  icon="fas fa-plus"
                  onclick={this.onPostEvent}
                >
                  {app.translator.trans('taxonomies-agenda.forum.agenda.post_event_button')}
                </Button>
              )}
            </div>
          </div>
          <AgendaFilters
            filters={this.currentFilters()}
            villes={this.facets.villes}
            onChange={this.onFilterChange}
          />
          {this.loading ? (
            <LoadingIndicator />
          ) : this.error ? (
            <div className="AgendaPage-error Alert Alert--error">{this.error}</div>
          ) : (
            <AgendaTimeline events={this.events} onDelete={this.onDeleteEvent} />
          )}
        </div>
      </div>
    );
  }
}
