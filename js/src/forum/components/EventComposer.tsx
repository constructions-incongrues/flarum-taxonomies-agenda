import app from 'flarum/forum/app';
import DiscussionComposer from 'flarum/forum/components/DiscussionComposer';
import type Mithril from 'mithril';

interface EventFields {
  tags?: any[];
  eventDate?: string;
  eventVille?: string;
  eventLieu?: string;
  eventPersonne?: string;
  [k: string]: unknown;
}

interface EventPrefillAttrs {
  prefillTitle?: string;
  prefillDate?: string;
  prefillVille?: string;
  prefillLieu?: string;
  prefillPersonne?: string;
}

interface FacetsCacheEntry {
  villes: string[];
  lieux: string[];
  expires: number;
}

export default class EventComposer extends DiscussionComposer {
  isLocating = false;
  suggestions: { villes: string[]; lieux: string[] } = { villes: [], lieux: [] };
  searchTimeout: ReturnType<typeof setTimeout> | null = null;
  private facetsCache = new Map<string, FacetsCacheEntry>();
  private static readonly CACHE_TTL_MS = 60_000;

  oninit(vnode: any) {
    super.oninit(vnode);

    this.loading = false;
    if (this.composer) {
      this.composer.loading = false;
    }

    if (this.composer && this.composer.fields) {
      const a: EventPrefillAttrs = (this.attrs as EventPrefillAttrs) || {};
      // Ensure agenda tag is selected (flarum/tags reads from fields.tags)
      const agendaTag = app.store.getBy('tags', 'slug', 'agenda');
      const current = this.composer.fields.tags || [];
      if (agendaTag && !current.some((t: any) => t.id() === agendaTag.id())) {
        this.composer.fields.tags = [...current, agendaTag];
      }
      this.composer.fields.eventDate = this.composer.fields.eventDate || a.prefillDate || new Date().toISOString().split('T')[0];
      this.composer.fields.eventVille = this.composer.fields.eventVille || a.prefillVille || '';
      this.composer.fields.eventLieu = this.composer.fields.eventLieu || a.prefillLieu || '';
      this.composer.fields.eventPersonne = this.composer.fields.eventPersonne || a.prefillPersonne || '';
      if (a.prefillTitle && typeof this.title === 'function') {
        this.title(a.prefillTitle);
      }
    }

    setTimeout(() => {
      this.forceVisibility();
      m.redraw();
    }, 10);
  }

  oncreate(vnode: any) {
    super.oncreate(vnode);
    this.forceVisibility();
    setTimeout(() => {
      const c: any = app.composer;
      if (c && typeof c.fullScreen === 'function' && !c.isFullScreen?.()) {
        c.fullScreen();
        m.redraw();
      }
    }, 350);
  }

  onupdate(vnode: any) {
    super.onupdate(vnode);
    this.forceVisibility();
  }

  forceVisibility() {
    const el = document.getElementById('composer');
    if (el) {
      el.classList.add('EventComposer', 'active', 'visible');
    }
  }

  locateMe() {
    if (!navigator.geolocation) {
      app.alerts.show({ type: 'error' }, "La géolocalisation n'est pas supportée.");
      return;
    }

    this.isLocating = true;
    m.redraw();

    navigator.geolocation.getCurrentPosition(
      (position) => {
        const { latitude, longitude } = position.coords;
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=10&addressdetails=1`)
          .then(res => res.json())
          .then(data => {
            const city = data.address.city || data.address.town || data.address.village;
            if (city && this.composer && this.composer.fields) {
              this.composer.fields.eventVille = city;
              this.searchSuggestions(city);
            }
          })
          .catch(() => app.alerts.show({ type: 'error' }, "Erreur de géolocalisation."))
          .finally(() => {
            this.isLocating = false;
            m.redraw();
          });
      },
      () => {
        this.isLocating = false;
        m.redraw();
        app.alerts.show({ type: 'error' }, "Accès position refusé.");
      }
    );
  }

  searchSuggestions(q: string) {
    if (this.searchTimeout) clearTimeout(this.searchTimeout);

    const key = (q || '').trim().toLowerCase();
    const cached = this.facetsCache.get(key);
    if (cached && cached.expires > Date.now()) {
      this.suggestions.villes = cached.villes;
      this.suggestions.lieux = cached.lieux;
      return;
    }

    this.searchTimeout = setTimeout(() => {
      app.request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/agenda/facets',
        params: { 'filter[q]': q }
      }).then((result: any) => {
        if (result && result.data && result.data.attributes) {
          const villes: string[] = result.data.attributes.villes || [];
          const lieux: string[] = result.data.attributes.lieux || [];
          this.suggestions.villes = villes;
          this.suggestions.lieux = lieux;
          this.facetsCache.set(key, {
            villes,
            lieux,
            expires: Date.now() + EventComposer.CACHE_TTL_MS,
          });
          m.redraw();
        }
      });
    }, 300);
  }

  private stripItems(items: any) {
    const junk = ['title', 'discussionTitle', 'tags', 'label'];
    junk.forEach(k => items.remove(k));
    // Remove every taxonomy-* item (flamarkt adds them dynamically)
    if (typeof items.toArray === 'function' && items.items) {
      Object.keys(items.items).forEach(k => {
        if (k.startsWith('taxonomy-')) items.remove(k);
      });
    }
  }

  headerItems() {
    const items = super.headerItems();
    this.stripItems(items);

    const t = (k: string) => app.translator.trans('mi-agenda-timeline.forum.composer.' + k);
    const titleValue = typeof this.title === 'function' ? this.title() : '';
    const busy = this.loading || this.composer?.loading;
    const setField = (name: string, v: string) => {
      if (this.composer?.fields) this.composer.fields[name] = v;
    };

    items.add('event-details', (
      <div className="EventComposer-details">
        <header className="EventComposer-brand">
          <i className="fas fa-calendar-alt" />
          <span>{t('brand_label')}</span>
        </header>

        <div className="EventComposer-field full-width agenda-event-title">
          <label><i className="fas fa-heading" /> {t('title_label')}</label>
          <input
            className="FormControl FormControl--lg"
            value={titleValue}
            oninput={(e: any) => typeof this.title === 'function' && this.title(e.target.value)}
            placeholder={t('title_placeholder')}
            disabled={busy}
          />
        </div>

        <div className="EventComposer-field">
          <label><i className="fas fa-calendar-day" /> {t('date_label')}</label>
          <input
            className="FormControl"
            type="date"
            value={this.composer?.fields?.eventDate || ''}
            oninput={(e: any) => setField('eventDate', e.target.value)}
            min={new Date().toISOString().split('T')[0]}
            required
            disabled={busy}
          />
        </div>

        <div className="EventComposer-field field-with-action">
          <label><i className="fas fa-city" /> {t('ville_label')}</label>
          <div className="InputWrapper">
            <input
              className="FormControl"
              value={this.composer?.fields?.eventVille || ''}
              oninput={(e: any) => { setField('eventVille', e.target.value); this.searchSuggestions(e.target.value); }}
              placeholder={t('ville_placeholder')}
              list="event-ville-list"
              disabled={busy}
            />
            <button
              className="Button Button--icon Button-locate"
              onclick={() => this.locateMe()}
              title="Localise-moi"
              disabled={this.isLocating || busy}
              type="button"
            >
              <i className={this.isLocating ? 'fas fa-spinner fa-spin' : 'fas fa-location-arrow'} />
            </button>
          </div>
          <datalist id="event-ville-list">
            {(this.suggestions.villes || []).map(name => <option value={name} />)}
          </datalist>
        </div>

        <div className="EventComposer-field full-width">
          <label><i className="fas fa-map-marker-alt" /> {t('lieu_label')}</label>
          <input
            className="FormControl"
            value={this.composer?.fields?.eventLieu || ''}
            oninput={(e: any) => { setField('eventLieu', e.target.value); this.searchSuggestions(e.target.value); }}
            placeholder={t('lieu_placeholder')}
            list="event-lieu-list"
            disabled={busy}
          />
          <datalist id="event-lieu-list">
            {(this.suggestions.lieux || []).map(name => <option value={name} />)}
          </datalist>
        </div>

        <div className="EventComposer-divider">
          <span><i className="fas fa-align-left" /> {t('description_label')}</span>
        </div>
      </div>
    ), 50);

    return items;
  }

  footerItems() {
    const items = super.footerItems();
    this.stripItems(items);
    return items;
  }

  data() {
    const data: any = super.data();
    const dateStr = this.composer.fields.eventDate;
    const taxonomyData: any[] = [];

    if (dateStr) {
      const date = new Date(dateStr);
      const day = date.getDate().toString();
      const monthNames = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
      const month = monthNames[date.getMonth()];
      const year = date.getFullYear().toString();
      taxonomyData.push({ slug: 'jour', term: day }, { slug: 'mois', term: month }, { slug: 'annee', term: year });
    }

    if (this.composer.fields.eventVille) taxonomyData.push({ slug: 'ville', term: this.composer.fields.eventVille });
    if (this.composer.fields.eventLieu) taxonomyData.push({ slug: 'lieu', term: this.composer.fields.eventLieu });

    data.taxonomies = taxonomyData;
    return data;
  }

  onsubmit() {
    const t = (k: string) => app.translator.trans('mi-agenda-timeline.forum.composer.' + k);

    if (!this.composer.fields.eventDate) {
      app.alerts.show({ type: 'error' }, t('error_missing_date'));
      this.loading = false;
      return;
    }

    const title = typeof this.title === 'function' ? (this.title() || '').trim() : '';
    if (!title) {
      app.alerts.show({ type: 'error' }, t('error_missing_title'));
      this.loading = false;
      return;
    }

    this.loading = true;

    app.store.createRecord('discussions').save(this.data()).then(
      (discussion: any) => {
        app.composer.hide();
        m.route.set(app.route.discussion(discussion));
      },
      (error: any) => {
        const errors = error?.response?.errors || [];
        if (errors.length) {
          errors.forEach((e: any) =>
            app.alerts.show({ type: 'error' }, e.detail || e.title || 'Erreur')
          );
        } else {
          app.alerts.show({ type: 'error' }, t('error_submit'));
        }
        this.loaded();
      }
    );
  }
}
