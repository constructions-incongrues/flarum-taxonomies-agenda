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

export default class AgendaPage extends Page {
  loading = true;
  events: AgendaEvent[] = [];
  facets: AgendaFacetsAttributes = { villes: [], lieux: [], artistes: [] };
  error: string | null = null;

  oninit(vnode: any) {
    super.oninit(vnode);
    app.history.push('agenda');
    this.load().then(() => {
      if (m.route.get().split('?')[0].replace(/\/$/, '').endsWith('/agenda/new')) {
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
      placeholder: app.translator.trans('mi-agenda-timeline.forum.composer.description_placeholder'),
      submitLabel: app.translator.trans('mi-agenda-timeline.forum.composer.submit_label'),
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
            {canPost && (
              <Button
                className="Button Button--primary"
                icon="fas fa-plus"
                onclick={this.onPostEvent}
              >
                {app.translator.trans('mi-agenda-timeline.forum.agenda.post_event_button')}
              </Button>
            )}
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
            <AgendaTimeline events={this.events} />
          )}
        </div>
      </div>
    );
  }
}
